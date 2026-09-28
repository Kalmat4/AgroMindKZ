<?php

namespace App\Http\Controllers;

use App\Models\CropChatMessage;
use App\Models\CropChatSession;
use App\Models\Zone;
use App\Services\NasaFirmsService;
use App\Services\OpenWeatherMapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CropChatController extends Controller
{
    private const HISTORY_LIMIT      = 10;
    private const HISTORY_TEXT_LIMIT = 1500;

    private const RISK_NAMES = [
        'heavy_rain'  => 'ливень',
        'hail_storm'  => 'град/гроза',
        'strong_wind' => 'сильный ветер',
        'drought'     => 'жара/засуха',
        'frost'       => 'заморозки',
        'fire'        => 'пожары',
    ];

    private const SEVERITY_NAMES = ['high' => 'высокий', 'nominal' => 'средний', 'low' => 'низкий'];

    public function __construct(
        private NasaFirmsService $firms,
        private OpenWeatherMapService $weather,
    ) {}

    public function sessions()
    {
        $sessions = CropChatSession::where('user_id', Auth::id())
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'created_at', 'updated_at']);

        return response()->json($sessions);
    }

    public function sessionMessages(int $id)
    {
        $session = CropChatSession::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $messages = CropChatMessage::where('crop_chat_session_id', $id)
            ->orderBy('id')
            ->get()
            ->map(fn($m) => [
                'id'      => $m->id,
                'role'    => $m->role,
                'text'    => $m->message,
                'preview' => $m->image_base64
                    ? "data:{$m->image_media_type};base64,{$m->image_base64}"
                    : null,
            ]);

        return response()->json(['session' => $session, 'messages' => $messages]);
    }

    public function deleteSession(int $id)
    {
        CropChatSession::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail()
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function chat(Request $request)
    {
        $data = $request->validate([
            'message'      => ['nullable', 'string', 'max:4000'],
            // base64 до ~8 МБ исходного файла
            'image'        => ['nullable', 'string', 'max:11000000'],
            'mediaType'    => ['nullable', 'string', 'starts_with:image/', 'max:50'],
            'sessionId'    => ['nullable', 'integer'],
            'region'       => ['nullable', 'array'],
            'region.name'  => ['required_with:region', 'string', 'max:100'],
            'region.west'  => ['required_with:region', 'numeric', 'between:-180,180'],
            'region.south' => ['required_with:region', 'numeric', 'between:-90,90'],
            'region.east'  => ['required_with:region', 'numeric', 'between:-180,180'],
            'region.north' => ['required_with:region', 'numeric', 'between:-90,90'],
        ]);

        $user      = Auth::user();
        $message   = trim($data['message'] ?? '');
        $image     = $data['image'] ?? null;
        $mediaType = $data['mediaType'] ?? 'image/jpeg';

        if ($message === '' && ! $image) {
            return response()->json(['error' => 'Пустое сообщение'], 422);
        }

        $session = ! empty($data['sessionId'])
            ? CropChatSession::where('id', $data['sessionId'])->where('user_id', $user->id)->firstOrFail()
            : null;

        $history = $session
            ? CropChatMessage::where('crop_chat_session_id', $session->id)
                ->orderByDesc('id')
                ->limit(self::HISTORY_LIMIT)
                ->get()
                ->reverse()
            : collect();

        $prompt = $this->buildPrompt($message, $image !== null, $history, $this->regionFor($data['region'] ?? null, $user->id));

        $aiText = $this->askN8n($prompt, $image, $mediaType, $session?->id, $user->id);
        if ($aiText === null) {
            return response()->json(['error' => 'ИИ-агроном сейчас недоступен, попробуйте позже'], 502);
        }

        // Сохраняем пару «вопрос — ответ» только после успешного ответа, чтобы в истории не оставалось вопросов без ответа
        if (! $session) {
            $session = CropChatSession::create([
                'user_id' => $user->id,
                'title'   => $message !== '' ? mb_substr($message, 0, 60) : 'Фото-анализ ' . now()->format('d.m H:i'),
            ]);
        }

        CropChatMessage::create([
            'crop_chat_session_id' => $session->id,
            'role'                 => 'user',
            'message'              => $message,
            'image_base64'         => $image,
            'image_media_type'     => $image ? $mediaType : null,
        ]);
        CropChatMessage::create([
            'crop_chat_session_id' => $session->id,
            'role'                 => 'ai',
            'message'              => $aiText,
        ]);
        $session->touch();

        return response()->json([
            'response'     => $aiText,
            'sessionId'    => $session->id,
            'sessionTitle' => $session->title,
        ]);
    }

    /** Регион, о котором спрашивают: выбранный на карте, иначе сохранённая зона пользователя. */
    private function regionFor(?array $region, int $userId): ?array
    {
        if ($region) {
            return $region;
        }

        $zone = Zone::where('user_id', $userId)->first();

        return $zone ? [
            'name'  => $zone->oblast_name,
            'west'  => $zone->bbox_west,
            'south' => $zone->bbox_south,
            'east'  => $zone->bbox_east,
            'north' => $zone->bbox_north,
        ] : null;
    }

    /**
     * Воркфлоу n8n принимает только поле message: историю и регион он не читает,
     * поэтому живые данные и переписку передаём внутри текста запроса.
     */
    private function buildPrompt(string $message, bool $hasImage, $history, ?array $region): string
    {
        $parts = [];

        if ($region) {
            $ctx   = ["Регион пользователя: {$region['name']} (данные платформы AgroMind KZ на " . now()->format('d.m.Y H:i') . ')'];
            $spots = $this->firms->getHotspots($region['west'], $region['south'], $region['east'], $region['north']);
            if ($spots === null) {
                $ctx[] = 'Пожары (NASA FIRMS): данные сейчас недоступны.';
            } else {
                $strong = count(array_filter($spots, fn($s) => $s['severity'] === 'high'));
                $ctx[]  = 'Пожары (NASA FIRMS, спутник VIIRS, 48 ч): ' . count($spots) . " термоточек, из них сильных: {$strong}.";
            }

            $lat      = ($region['south'] + $region['north']) / 2;
            $lon      = ($region['west'] + $region['east']) / 2;
            $forecast = $this->weather->getForecast($lat, $lon);
            if ($forecast) {
                $s     = $forecast['summary'];
                $ctx[] = "Прогноз на 5 дней (OpenWeatherMap): {$s['temp_min']}…{$s['temp_max']} °C, осадки {$s['precip_total']} мм, ветер до {$s['wind_max']} м/с.";
                $risks = array_map(
                    fn($r) => (self::RISK_NAMES[$r['type']] ?? $r['type']) . ' — ' . (self::SEVERITY_NAMES[$r['severity']] ?? $r['severity']) . ($r['detail'] ? " ({$r['detail']})" : ''),
                    $forecast['risks']
                );
                $ctx[] = 'Угрозы урожаю по прогнозу: ' . ($risks ? implode('; ', $risks) : 'не выявлены') . '.';
            }
            $parts[] = "[Контекст]\n" . implode("\n", $ctx);
        }

        if ($history->isNotEmpty()) {
            $lines = $history->map(fn($m) => ($m->role === 'ai' ? 'Агроном' : 'Пользователь') . ': '
                . mb_substr($m->message ?: '(фото)', 0, self::HISTORY_TEXT_LIMIT));
            $parts[] = "[Предыдущая переписка]\n" . $lines->implode("\n");
        }

        $question = $message !== '' ? $message : 'Проанализируй фото посевов: состояние, проблемы, рекомендации.';
        if ($parts) {
            $question = "[Вопрос]\n" . $question
                . "\n\nОпирайся на контекст выше, называй регион пользователя, а не другой. Если данных не хватает — скажи об этом, не выдумывай цифры.";
        }
        $parts[] = $question;

        return implode("\n\n", $parts);
    }

    private function askN8n(string $prompt, ?string $image, string $mediaType, ?int $sessionId, int $userId): ?string
    {
        $url = config('services.n8n.crop_webhook');
        if (! $url) {
            Log::error('N8N_CROP_WEBHOOK_URL не задан');
            return null;
        }

        // Воркфлоу ждёт multipart: фото приходит в n8n бинарным файлом
        $http = Http::timeout(90)->asMultipart();
        if ($image) {
            $ext  = explode('/', $mediaType)[1] ?? 'jpg';
            $http = $http->attach('image', base64_decode($image), "photo.{$ext}", ['Content-Type' => $mediaType]);
        }

        try {
            $resp = $http->post($url, [
                'message'   => $prompt,
                'mediaType' => $mediaType,
                'sessionId' => (string) ($sessionId ?? ''),
                'user_id'   => (string) $userId,
            ]);
        } catch (\Throwable $e) {
            Log::error('n8n crop chat request failed', ['message' => $e->getMessage()]);
            return null;
        }

        if (! $resp->successful()) {
            Log::warning('n8n crop chat non-200', ['status' => $resp->status(), 'body' => mb_substr($resp->body(), 0, 300)]);
            return null;
        }

        $json = $resp->json();
        $text = is_string($json) ? $json : ($json['output'] ?? $json['response'] ?? $json['text'] ?? null);

        // Воркфлоу превращает ошибку Anthropic в обычный текст со статусом 200
        if (! is_string($text) || trim($text) === '' || str_starts_with($text, 'Ошибка API')) {
            Log::warning('n8n crop chat bad payload', ['body' => mb_substr($resp->body(), 0, 300)]);
            return null;
        }

        return $text;
    }
}
