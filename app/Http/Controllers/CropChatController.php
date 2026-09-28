<?php

namespace App\Http\Controllers;

use App\Models\CropChatMessage;
use App\Models\CropChatSession;
use App\Models\User;
use App\Models\Zone;
use App\Services\AgronomistContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CropChatController extends Controller
{
    private const HISTORY_LIMIT      = 10;
    private const HISTORY_TEXT_LIMIT = 1500;

    public function __construct(private AgronomistContextService $context) {}

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
            // До 5 МБ исходного изображения, передача в JSON как base64.
            'image'        => ['nullable', 'string', 'max:7000000'],
            'mediaType'    => ['nullable', 'string', 'in:image/jpeg,image/png,image/webp,image/gif', 'max:50'],
            'sessionId'    => ['nullable', 'integer'],
            'fieldId'      => ['nullable', 'integer', 'min:1'],
            'field' => ['nullable', 'array:lat,lon,crop,growth_stage,irrigation', 'min:2'],
            'field.lat' => ['required_with:field', 'numeric', 'between:-90,90'],
            'field.lon' => ['required_with:field', 'numeric', 'between:-180,180'],
            'field.crop' => ['nullable', 'string', 'max:100'],
            'field.growth_stage' => ['nullable', 'string', 'max:100'],
            'field.irrigation' => ['nullable', 'in:rainfed,irrigated,unknown'],
            'region'       => ['nullable', 'array:name,west,south,east,north', 'min:5'],
            'region.name'  => ['required_with:region', 'string', 'max:100'],
            'region.west'  => ['required_with:region', 'numeric', 'between:-180,180'],
            'region.south' => ['required_with:region', 'numeric', 'between:-90,90'],
            'region.east'  => ['required_with:region', 'numeric', 'between:-180,180'],
            'region.north' => ['required_with:region', 'numeric', 'between:-90,90'],
        ]);

        if (isset($data['region']) && ($data['region']['west'] >= $data['region']['east'] || $data['region']['south'] >= $data['region']['north'])) {
            return response()->json(['error' => 'Неверные границы региона'], 422);
        }
        if (! empty($data['image'])) {
            $bytes = base64_decode($data['image'], true);
            $info = $bytes !== false ? @getimagesizefromstring($bytes) : false;
            if (! $info || strlen($bytes) > 5 * 1024 * 1024 || $info[0] > 8000 || $info[1] > 8000
                || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)
                || $info['mime'] !== ($data['mediaType'] ?? 'image/jpeg')) {
                return response()->json(['error' => 'Нужно корректное фото JPEG, PNG, WebP или GIF до 5 МБ и 8000 px по стороне'], 422);
            }
        }

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

        $field = $this->fieldFor($data, $session, $user);
        $context = $this->context->build($field, $this->regionFor($data['region'] ?? null, $user->id));
        $payload = [
            'schema_version' => 2,
            'message' => $message ?: 'Проанализируй фото посевов: состояние, возможные проблемы и следующие действия.',
            'image' => $image,
            'mediaType' => $mediaType,
            'context' => $context,
            'history' => $history->map(fn ($m) => [
                'role' => $m->role === 'ai' ? 'assistant' : 'user',
                'text' => mb_substr($m->message ?: '(фото в предыдущем сообщении; сейчас оно недоступно)', 0, self::HISTORY_TEXT_LIMIT),
            ])->values()->all(),
            'sessionId' => $session?->id,
            'user_id' => $user->id,
        ];
        $aiText = $this->askN8n($payload);
        if ($aiText === null) {
            return response()->json(['error' => 'ИИ-агроном сейчас недоступен, попробуйте позже'], 502);
        }

        return DB::transaction(function () use ($session, $user, $message, $image, $mediaType, $aiText, $field) {
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
            $session->field_context = $field;
            $session->save();
            $session->touch();

            return response()->json([
                'response'     => $aiText,
                'sessionId'    => $session->id,
                'sessionTitle' => $session->title,
                'field' => $field,
            ]);
        });
    }

    /** Saved map fields are resolved on the server, including their current coordinates. */
    private function fieldFor(array $data, ?CropChatSession $session, User $user): ?array
    {
        if (array_key_exists('fieldId', $data)) {
            // Explicit null means the map selection was cleared; never restore an old chat's field.
            $fieldId = $data['fieldId'];
            if ($fieldId === null) {
                return null;
            }
        } else {
            // Retain compatibility with older clients that sent a manually selected point.
            $field = array_key_exists('field', $data) ? $data['field'] : $session?->field_context;
            $fieldId = $field['id'] ?? null;
            if ($fieldId === null) {
                return $field;
            }
        }

        $saved = $user->fields()->find($fieldId);
        if (! $saved) {
            throw ValidationException::withMessages([
                'fieldId' => 'Поле больше недоступно. Выберите своё поле в разделе «Мои поля».',
            ]);
        }

        return [
            'id' => $saved->id,
            'name' => $saved->name,
            'lat' => $saved->lat,
            'lon' => $saved->lon,
            'area_ha' => $saved->area_ha,
            'crop_code' => $saved->crop_code,
            'crop' => $saved->crop_code
                ? DB::table('crops')->where('code', $saved->crop_code)->value('name_ru')
                : null,
        ];
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

    private function askN8n(array $payload): ?string
    {
        $url = config('services.n8n.crop_webhook');
        $secret = config('services.n8n.crop_secret');
        if (! $url) {
            Log::error('Настройте N8N_CROP_WEBHOOK_URL');
            return null;
        }
        // Protocol selection must not depend on optional webhook authentication.
        // v2 sends coordinates and images as JSON even when no secret is configured.
        if (config('services.n8n.crop_legacy')) {
            return $this->askLegacyN8n($url, $payload);
        }
        try {
            $http = Http::connectTimeout(10)->timeout(90)->acceptJson();
            if ($secret) {
                $http = $http->withHeaders(['X-AgroMind-Secret' => $secret]);
            }
            $resp = $http->post($url, $payload);
            $json = $resp->json();
            $text = is_array($json) ? ($json['response'] ?? null) : null;
            if (! $resp->successful() || ! is_string($text) || trim($text) === '') {
                Log::warning('n8n crop chat failed', ['status' => $resp->status()]);
                return null;
            }
            return $text;
        } catch (\Throwable $e) {
            Log::warning('n8n crop chat unavailable', ['exception' => get_class($e)]);
            return null;
        }
    }

    /**
     * Воркфлоу v1 (crop-chat) читает только поле message и фото в multipart,
     * поэтому контекст и историю передаём внутри текста.
     */
    private function askLegacyN8n(string $url, array $payload): ?string
    {
        $http = Http::connectTimeout(10)->timeout(90)->asMultipart();
        if (! empty($payload['image'])) {
            $ext  = explode('/', $payload['mediaType'])[1] ?? 'jpg';
            $http = $http->attach('image', base64_decode($payload['image']), "photo.{$ext}", ['Content-Type' => $payload['mediaType']]);
        }

        try {
            $resp = $http->post($url, [
                'message'   => $this->legacyPrompt($payload),
                'mediaType' => $payload['mediaType'],
                'sessionId' => (string) ($payload['sessionId'] ?? ''),
                'user_id'   => (string) $payload['user_id'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('n8n v1 crop chat unavailable', ['exception' => get_class($e)]);
            return null;
        }

        $json = $resp->json();
        $text = is_string($json) ? $json : (is_array($json) ? ($json['output'] ?? $json['response'] ?? $json['text'] ?? null) : null);
        // v1 превращает ошибку Anthropic в обычный текст со статусом 200
        if (! $resp->successful() || ! is_string($text) || trim($text) === '' || str_starts_with($text, 'Ошибка API')) {
            Log::warning('n8n v1 crop chat failed', ['status' => $resp->status(), 'body' => mb_substr($resp->body(), 0, 300)]);
            return null;
        }

        return $text;
    }

    private function legacyPrompt(array $payload): string
    {
        $ctx   = $payload['context'];
        $lines = [];

        $loc = $ctx['location'];
        if ($loc['point']) {
            $lines[] = $loc['scope'] === 'selected_point'
                ? sprintf('Участок пользователя: %.5f, %.5f.', $loc['point']['lat'], $loc['point']['lon'])
                : "Регион пользователя: {$loc['region_name']} (центр региона, приблизительно).";
        }
        if ($field = $ctx['field']) {
            $irrigation = ['rainfed' => 'богара', 'irrigated' => 'орошаемое'][$field['irrigation'] ?? ''] ?? null;
            $lines[] = 'Культура: ' . (($field['crop'] ?? '') ?: 'не указана')
                . (($field['growth_stage'] ?? '') ? ", фаза: {$field['growth_stage']}" : '')
                . ($irrigation ? ", {$irrigation}" : '') . '.';
        }

        $spots = $ctx['thermal_anomalies'];
        if ($spots['search_bbox']) {
            $lines[] = $spots['status'] === 'available'
                ? "Термоточки NASA FIRMS (VIIRS, 48 ч) в зоне поиска: {$spots['count']}."
                : 'Термоточки NASA FIRMS: данные сейчас недоступны.';
        }

        if ($w = $ctx['weather']['data']) {
            $s = $w['summary'];
            $lines[] = "Прогноз на 5 дней (OpenWeatherMap): {$s['temp_min']}…{$s['temp_max']} °C, осадки {$s['precip_total']} мм, ветер до {$s['wind_max']} м/с.";
            $risks = array_map(fn($r) => (self::RISK_NAMES[$r['type']] ?? $r['type']) . ($r['detail'] ? " ({$r['detail']})" : ''), $w['risks']);
            $lines[] = 'Угрозы по прогнозу: ' . ($risks ? implode('; ', $risks) : 'не выявлены') . '.';
        }

        $parts = [];
        if ($lines) {
            $parts[] = "[Контекст — данные платформы AgroMind KZ на " . now()->format('d.m.Y H:i') . "]\n" . implode("\n", $lines);
        }
        if ($payload['history']) {
            $parts[] = "[Предыдущая переписка]\n" . implode("\n", array_map(
                fn($m) => ($m['role'] === 'assistant' ? 'Агроном' : 'Пользователь') . ': ' . $m['text'],
                $payload['history']
            ));
        }
        $parts[] = "[Вопрос]\n" . $payload['message'];
        if ($lines) {
            $parts[] = 'Опирайся на контекст выше и называй регион пользователя, а не другой. Термоточки — не подтверждённые пожары. Если данных не хватает — скажи об этом, не выдумывай цифры.';
        }

        return implode("\n\n", $parts);
    }

    private const RISK_NAMES = [
        'heavy_rain'  => 'ливень',
        'hail_storm'  => 'град/гроза',
        'strong_wind' => 'сильный ветер',
        'drought'     => 'жара/засуха',
        'frost'       => 'заморозки',
        'fire'        => 'пожары',
    ];
}
