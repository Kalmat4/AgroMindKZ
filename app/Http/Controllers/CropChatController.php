<?php

namespace App\Http\Controllers;

use App\Models\CropChatSession;
use App\Models\CropChatMessage;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class CropChatController extends Controller
{
    private const CROP_WEBHOOK_URL = 'http://77.243.80.191:5678/webhook-test/crop-chat';

    private const DEMO_DELAY_SECONDS = 3;

    // Базовый сценарий погоды — Костанайская область, конец июня:
    // Воздух +27–31°C, за 30 дней выпало ~15 мм при норме 45 мм (дефицит),
    // прогноз на 7 дней: без существенных осадков (до 5 мм), ветер 4–6 м/с,
    // NASA FIRMS: 3 слабых очага за 48ч в регионе.
    private const DEMO_RESPONSES = [
        'corn_damage' => <<<'MD'
## 🌽 Анализ кукурузного поля
**Диагноз:** критическое повреждение — засуха и полегание стеблей.
### Проблемы:
- **Полегание ~70–80% стеблей** — порывы ветра до 12 м/с при ослабленных засухой стеблях
- **Листья полностью высохли** — дефицит влаги на стадии молочно-восковой спелости
- **Початки недозревшие** — зерно сформировалось, но влажность критически низкая
### Рекомендации:
1. **Уборка немедленно** — каждый день задержки = потери 3–5%
2. **Жатка с адаптером** для полёглых стеблей
3. **Оформить страховой случай** — зафиксировать ущерб фото с GPS-привязкой
### Источники:
- **Визуальный анализ:** степень полегания, состояние листовой массы и початков
- **OpenWeatherMap:** за последние 30 дней — 15 мм осадков при норме 45 мм; ветер 4–6 м/с, порывы до 12 м/с
- **NASA FIRMS:** 3 слабых очага за 48ч в радиусе 50 км — высокий риск пожара сухой биомассы
> ⚠️ **Прогноз потерь:** 40–60% от планового объёма.
MD,

        'wheat_field' => <<<'MD'
## 🌾 Анализ пшеничного поля
**Диагноз:** полная спелость, поле готово к уборке.
### Состояние:
- **Колосья сформированы** — зерно в фазе полной спелости
- **Стебли золотисто-жёлтые** — влажность зерна ~13–14%
- **Густота нормальная** — ~350–400 раст./м², признаков болезней нет
### Рекомендации:
1. **Начать уборку в ближайшие 2–3 дня** — оптимальная влажность зерна
2. **Убирать утром** (до 11:00) — меньше потерь от осыпания
3. **Подготовить хранилище** — обеспечить вентиляцию, влажность <14%
### Источники:
- **Визуальный анализ:** цвет колоса, стадия зрелости, плотность стояния
- **OpenWeatherMap:** ближайшие 7 дней без существенных осадков (до 5 мм) — окно для уборки открыто
- **NASA FIRMS:** 3 слабых очага за 48ч в Костанайской обл. — не в непосредственной близости, риск минимальный
> ✅ **Оценка урожайности:** 18–22 ц/га — соответствует среднему по региону.
MD,

        'sowing_advice' => <<<'MD'
## 🌱 Прогноз на посев — Костанайская область
**Рекомендация: сеять можно, но с осторожностью.**
### Текущие условия:
- **Температура почвы:** +18–20°C на глубине 10 см (порог для яровой пшеницы — +12°C) ✓
- **Влажность почвы:** 22–28 мм в метровом слое — ниже оптимума 35–40 мм
- **Осадки за 30 дней:** ~15 мм при норме 45 мм — выраженный дефицит
- **Прогноз на 7 дней:** до 5 мм, существенного пополнения влаги не ожидается
### Рекомендации:
1. **Засевать сначала** тяжёлые почвы на ровном рельефе — лучше держат влагу
2. **Норму высева снизить на 10–15%** (до 130–140 кг/га) — разрежённый посев эффективнее при дефиците
3. **Глубина заделки 6–7 см** — для контакта с влажным горизонтом
4. **Сорта:** засухоустойчивые — Астана-2, Шортандинская-95
### Источники:
- **OpenWeatherMap:** воздух +27–31°C, ветер 4–6 м/с, без заморозков на 14 дней
- **NASA FIRMS:** 3 слабых очага за 48ч в Костанайской обл. — прямой угрозы посевам нет
- **Исторические данные:** оптимальные сроки сева — 15 мая – 5 июня
> 📊 **Вывод:** условия допустимые, но не идеальные. Ожидаемая урожайность при соблюдении рекомендаций — 12–16 ц/га.
MD,

        'ecology_fire' => <<<'MD'
## 🌿 Экология и пожарные риски — Костанайская область
### Пожарная обстановка:
- **3 очага за последние 48ч** — все степени «слабый» (FRP < 8 МВт)
- Очаги локализованы в степной зоне, угрозы населённым пунктам и посевам нет
- **Индекс пожароопасности: ВЫСОКИЙ** — из-за дефицита осадков (15 мм за 30 дней при норме 45 мм) и температуры +27–31°C сухая биомасса легко воспламеняется
### Экологическая ситуация:
- **Деградация почв:** индекс 3.2 из 5 — умеренная, связана с ветровой эрозией на распаханных участках
- **Водный стресс:** высокий — уровень грунтовых вод снизился, малые реки обмелели
- **CO₂ от пожаров:** ~45 Кт с начала года — в пределах среднегодовой нормы для региона
### Рекомендации:
1. **Опахать границы полей** — создать противопожарные полосы шириной 4–6 м
2. **Контролировать стерню** — не оставлять сухие остатки без заделки в почву
3. **Мониторинг NASA FIRMS** — проверять карту пожаров ежедневно, особенно при ветре >8 м/с
### Источники:
- **NASA FIRMS:** 3 термальных аномалии за 48ч, спутник VIIRS SNPP
- **OpenWeatherMap:** +27–31°C, осадки 15 мм/30 дней, ветер 4–6 м/с — условия для распространения огня
- **Экологический мониторинг АгроМинд:** индексы деградации и водного стресса по региону
> ⚠️ **Вывод:** прямой угрозы нет, но условия для возникновения пожаров благоприятные. Рекомендуется усилить профилактику.
MD,
    ];

    private function matchDemoScenario(?string $fileName, string $message): ?string
    {
        if ($fileName) {
            $lower = mb_strtolower($fileName);
            foreach (['corn', 'кукуруз', 'damage', 'drought', 'засух'] as $kw) {
                if (str_contains($lower, $kw)) {
                    return self::DEMO_RESPONSES['corn_damage'];
                }
            }
            foreach (['wheat', 'пшениц', 'barley', 'ячмень', 'grain', 'зерн', 'поле'] as $kw) {
                if (str_contains($lower, $kw)) {
                    return self::DEMO_RESPONSES['wheat_field'];
                }
            }
        }

        if ($message) {
            $lower = mb_strtolower($message);

            if (str_contains($lower, 'экологическ') || (str_contains($lower, 'пожарн') && str_contains($lower, 'риск'))) {
                return self::DEMO_RESPONSES['ecology_fire'];
            }

            $sowingKeywords = ['засеива', 'засея', 'посев', 'сеять', 'сеить', 'посеять', 'сажать', 'посадк'];
            foreach ($sowingKeywords as $kw) {
                if (str_contains($lower, $kw)) {
                    return self::DEMO_RESPONSES['sowing_advice'];
                }
            }
        }

        return null;
    }

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
        $user      = Auth::user();
        $sessionId = $request->input('sessionId');
        $message   = $request->input('message', '');
        $image     = $request->input('image');
        $mediaType = $request->input('mediaType', 'image/jpeg');
        $fileName  = $request->input('fileName');

        // Get or create session
        if ($sessionId) {
            $session = CropChatSession::where('id', $sessionId)
                ->where('user_id', $user->id)
                ->firstOrFail();
        } else {
            $title = $message
                ? mb_substr($message, 0, 60)
                : 'Фото-анализ ' . now()->format('d.m H:i');

            $session = CropChatSession::create([
                'user_id' => $user->id,
                'title'   => $title,
            ]);
        }

        // Save user message
        CropChatMessage::create([
            'crop_chat_session_id' => $session->id,
            'role'                 => 'user',
            'message'              => $message,
            'image_base64'         => $image,
            'image_media_type'     => $image ? $mediaType : null,
        ]);

        // Check for demo scenario before calling external AI
        $demoResponse = $this->matchDemoScenario($fileName, $message);
        if ($demoResponse) {
            sleep(self::DEMO_DELAY_SECONDS);

            CropChatMessage::create([
                'crop_chat_session_id' => $session->id,
                'role'                 => 'ai',
                'message'              => $demoResponse,
            ]);
            $session->touch();

            return response()->json([
                'response'     => $demoResponse,
                'sessionId'    => $session->id,
                'sessionTitle' => $session->title,
            ]);
        }

        // Build history from DB (all saved messages before the current one)
        $historyForN8n = CropChatMessage::where('crop_chat_session_id', $session->id)
            ->orderBy('id')
            ->get()
            ->slice(0, -1)
            ->map(fn($m) => [
                'role' => $m->role === 'ai' ? 'assistant' : 'user',
                'text' => $m->message,
            ])
            ->values()
            ->toArray();

        // Attach user's zone context so AI knows the region
        $zone = Zone::where('user_id', $user->id)->first();
        $zoneContext = $zone ? [
            'region'     => $zone->oblast_name,
            'bbox_west'  => $zone->bbox_west,
            'bbox_east'  => $zone->bbox_east,
            'bbox_south' => $zone->bbox_south,
            'bbox_north' => $zone->bbox_north,
        ] : null;

        // Call n8n webhook — image as binary multipart so n8n gets a native binary item;
        // history/zone are JSON-encoded strings since multipart can't carry nested arrays.
        $url  = env('N8N_CROP_WEBHOOK_URL', self::CROP_WEBHOOK_URL);
        $http = Http::timeout(60)->withoutVerifying();

        if ($image) {
            $ext  = explode('/', $mediaType)[1] ?? 'jpg';
            $http = $http->attach('image', base64_decode($image), "photo.{$ext}", ['Content-Type' => $mediaType]);
        }

        $n8nResp = $http->post($url, [
            'message'   => $message,
            'mediaType' => $mediaType,
            'history'   => json_encode($historyForN8n, JSON_UNESCAPED_UNICODE),
            'sessionId' => (string) $session->id,
            'zone'      => $zoneContext,
            'user_id'   => $user->id,
        ]);

        $n8nData = $n8nResp->json();
        $aiText  = is_string($n8nData)
            ? $n8nData
            : ($n8nData['response'] ?? $n8nData['output'] ?? $n8nData['text']
                ?? json_encode($n8nData, JSON_UNESCAPED_UNICODE));

        // Save AI response
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
}
