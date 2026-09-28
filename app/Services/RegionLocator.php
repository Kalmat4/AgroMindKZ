<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Область Казахстана по координатам — через обратное геокодирование OpenStreetMap (Nominatim).
 * Своих границ областей в базе нет (в regions только центры), а прямоугольники на карте пересекаются.
 * Правила Nominatim: не чаще 1 запроса в секунду и с User-Agent — вызываем только при создании поля.
 */
class RegionLocator
{
    // Ключевые части названий из OSM → коды таблицы regions (в OSM «Улутауская», «Абайская область»)
    private const STEMS = [
        'костанай' => 'KOS', 'акмол' => 'AKM', 'актюб' => 'AKT', 'алматинск' => 'ALM', 'атырау' => 'ATY',
        'восточно-казах' => 'VKO', 'жамбыл' => 'ZHM', 'западно-казах' => 'ZKO', 'караганд' => 'KAR',
        'кызылорд' => 'KYZ', 'мангист' => 'MAN', 'мангыст' => 'MAN', 'павлодар' => 'PAV',
        'северо-казах' => 'SKO', 'туркестан' => 'TUR', 'улытау' => 'ULY', 'улутау' => 'ULY',
        'жетысу' => 'ZHT', 'абай' => 'ABI',
    ];

    /** Код области или null (вне Казахстана, город республиканского значения, сервис недоступен). */
    public function codeFor(float $lat, float $lon): ?string
    {
        $key = sprintf('region:%.3f,%.3f', $lat, $lon);
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached ?: null;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'AgroMindKZ/1.0 (' . config('app.url') . ')'])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'jsonv2', 'lat' => $lat, 'lon' => $lon, 'zoom' => 8, 'accept-language' => 'ru',
                ]);
        } catch (\Throwable $e) {
            Log::warning('Nominatim unavailable', ['message' => $e->getMessage()]);
            return null;
        }

        if (! $response->successful()) {
            Log::warning('Nominatim non-200', ['status' => $response->status()]);
            return null;
        }

        $code = null;
        if ($response->json('address.country_code') === 'kz') {
            $state = mb_strtolower((string) $response->json('address.state'));
            foreach (self::STEMS as $stem => $c) {
                if (str_contains($state, $stem)) {
                    $code = $c;
                    break;
                }
            }
        }

        // Пустая строка в кэше — «спросили, области нет», чтобы не дёргать сервис повторно
        Cache::put($key, $code ?? '', now()->addDays(30));

        return $code;
    }
}
