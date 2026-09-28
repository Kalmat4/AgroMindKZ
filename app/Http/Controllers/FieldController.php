<?php

namespace App\Http\Controllers;

use App\Models\Field;
use App\Services\FieldThreatService;
use App\Services\FireAlertNotifier;
use App\Services\RegionLocator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FieldController extends Controller
{
    public function __construct(private FieldThreatService $threats, private RegionLocator $regions) {}

    /** Поля пользователя с текущей оценкой угрозы (FIRMS и погода из кэша). */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'fields'   => $user->fields()->orderBy('id')->get()
                ->map(function (Field $f) {
                    // Поля, созданные до определения области, дополняем один раз
                    if ($f->region_code === null) {
                        $f->update(['region_code' => $this->regions->codeFor($f->lat, $f->lon) ?? '']);
                    }

                    return $f->toArray() + ['assessment' => $this->threats->assess($f)];
                }),
            'crops'    => DB::table('crops')->orderBy('id')->get(['code', 'name_ru']),
            'telegram' => (bool) $user->telegram_chat_id,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'lat'           => ['required', 'numeric', 'between:-90,90'],
            'lon'           => ['required', 'numeric', 'between:-180,180'],
            'area_ha'       => ['required', 'numeric', 'min:0.1', 'max:1000000'],
            'crop_code'     => ['nullable', 'string', 'exists:crops,code'],
            'price_per_ton' => ['nullable', 'integer', 'min:1', 'max:100000000'],
        ]);

        $data['region_code'] = $this->regions->codeFor($data['lat'], $data['lon']) ?? '';
        $field = Auth::user()->fields()->create($data);

        return response()->json($field->toArray() + ['assessment' => $this->threats->assess($field)], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        Auth::user()->fields()->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    /** Ручная проверка: свежая оценка и сообщение в Telegram независимо от того, слали ли раньше. */
    public function check(int $id, FireAlertNotifier $notifier): JsonResponse
    {
        $field      = Auth::user()->fields()->findOrFail($id);
        $assessment = $this->threats->assess($field);

        return response()->json([
            'assessment'    => $assessment,
            'telegram_sent' => $notifier->notify($field, $assessment, onlyNew: false),
        ]);
    }

    /** Ссылка на бота: /start <токен> привяжет чат к пользователю. */
    public function telegramLink(): JsonResponse
    {
        $user = Auth::user();
        $user->telegram_link_token = Str::random(32);
        $user->save();

        return response()->json([
            'url' => 'https://t.me/' . config('services.telegram.bot_username') . '?start=' . $user->telegram_link_token,
        ]);
    }

    public function telegramStatus(): JsonResponse
    {
        return response()->json(['connected' => (bool) Auth::user()->telegram_chat_id]);
    }
}
