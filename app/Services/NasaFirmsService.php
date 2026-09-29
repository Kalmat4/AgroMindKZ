<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NasaFirmsService
{
    private const SOURCE    = 'VIIRS_SNPP_NRT';
    private const DAYS      = 2;
    private const CACHE_TTL = 600; // FIRMS обновляется несколько раз в сутки, 10 минут хватает

    public function __construct(private FireKindClassifier $kinds) {}

    /**
     * Очаги за 48 часов в bbox. null — FIRMS не ответил (это не то же самое, что «очагов нет»).
     */
    public function getHotspots(float $west, float $south, float $east, float $north): ?array
    {
        $area     = implode(',', [$west, $south, $east, $north]);
        $cacheKey = 'firms:v2:' . self::SOURCE . ':' . $area; // v2 — с типом земли и признаком пала

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $url = sprintf(
            'https://firms.modaps.eosdis.nasa.gov/api/area/csv/%s/%s/%s/%d/',
            config('services.firms.key'),
            self::SOURCE,
            $area,
            self::DAYS
        );

        try {
            $response = Http::timeout(15)->get($url);

            if (! $response->successful()) {
                Log::warning('NASA FIRMS non-200', ['status' => $response->status()]);
                return null;
            }

            $body = $response->body();
            // При неверном ключе или превышении лимита FIRMS отвечает 200 с текстом ошибки вместо CSV
            if (! str_starts_with(ltrim($body), 'latitude')) {
                Log::warning('NASA FIRMS unexpected body', ['body' => mb_substr($body, 0, 200)]);
                return null;
            }

            $hotspots = $this->kinds->classify($this->parseCsv($body));
            // Пока плитки карты земель догружаются, часть точек без типа земли — такой ответ держим недолго
            $complete = ! array_filter($hotspots, fn($s) => $s['land'] === null);
            Cache::put($cacheKey, $hotspots, $complete ? self::CACHE_TTL : 60);

            return $hotspots;
        } catch (\Throwable $e) {
            Log::error('NASA FIRMS request failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    private function parseCsv(string $csv): array
    {
        $lines = array_filter(
            explode("\n", trim($csv)),
            fn(string $line) => $line !== ''
        );

        if (count($lines) < 2) {
            return [];
        }

        $header = array_map('trim', str_getcsv(array_shift($lines)));
        $result = [];

        foreach ($lines as $line) {
            $row = array_combine($header, array_map('trim', str_getcsv($line)));

            if ($row === false) {
                continue;
            }

            $frp = (float) ($row['frp'] ?? 0);
            $brightness = (float) ($row['bright_ti4'] ?? 0);

            $result[] = [
                'lat'        => (float) $row['latitude'],
                'lon'        => (float) $row['longitude'],
                'brightness' => $brightness,
                'confidence' => $row['confidence'] ?? '',
                'frp'        => $frp,
                'severity'   => self::classifySeverity($frp, $brightness),
                'daynight'   => $row['daynight'] ?? '',
                'acq_date'   => $row['acq_date'] ?? '',
                'acq_time'   => $row['acq_time'] ?? '',
                'satellite'  => $row['satellite'] ?? '',
            ];
        }

        return $result;
    }

    private static function classifySeverity(float $frp, float $brightness): string
    {
        if ($frp >= 25 || $brightness >= 400) {
            return 'high';
        }

        if ($frp >= 8 || $brightness >= 340) {
            return 'nominal';
        }

        return 'low';
    }
}
