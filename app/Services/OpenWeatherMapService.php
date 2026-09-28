<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenWeatherMapService
{
    private const CACHE_TTL = 1800; // прогноз 5 дней по 3 часа, чаще обновлять смысла нет

    public function getForecast(float $lat, float $lon): ?array
    {
        $lat      = round($lat, 5);
        $lon      = round($lon, 5);
        $cacheKey = "owm:forecast:v2:{$lat},{$lon}"; // v2 — с индексом пожароопасности

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = Http::timeout(10)->get('https://api.openweathermap.org/data/2.5/forecast', [
                'lat'   => $lat,
                'lon'   => $lon,
                'appid' => config('services.openweather.key'),
                'units' => 'metric',
                'lang'  => 'ru',
            ]);

            if (! $response->successful()) {
                Log::warning('OpenWeatherMap non-200', ['status' => $response->status()]);
                return null;
            }

            $result = $this->analyze($response->json());
            Cache::put($cacheKey, $result, self::CACHE_TTL);

            return $result;
        } catch (\Throwable $e) {
            Log::error('OpenWeatherMap request failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    private function analyze(array $data): array
    {
        $list = $data['list'] ?? [];
        if (empty($list)) {
            return $this->emptyResult();
        }

        $risks  = [];
        $maxTemp = -100;
        $minTemp = 100;
        $totalPrecip = 0;
        $maxWind = 0;
        $periods = [];

        foreach ($list as $item) {
            $dt   = $item['dt'] ?? 0;
            $main = $item['main'] ?? [];
            $wind = $item['wind'] ?? [];
            $rain = $item['rain']['3h'] ?? 0;
            $snow = $item['snow']['3h'] ?? 0;
            $weatherArr = $item['weather'] ?? [];
            $weather = $weatherArr[0] ?? [];
            $weatherId = (int) ($weather['id'] ?? 0);

            $temp = $main['temp'] ?? 0;
            $maxTemp = max($maxTemp, $temp);
            $minTemp = min($minTemp, $temp);
            $totalPrecip += $rain + $snow;
            $windSpeed = $wind['speed'] ?? 0;
            $windGust  = $wind['gust'] ?? $windSpeed;
            $maxWind = max($maxWind, $windGust);

            $humidity = $main['humidity'] ?? null;
            $period = [
                'dt'       => $dt,
                'date'     => $item['dt_txt'] ?? '',
                'temp'     => round($temp, 1),
                'humidity' => $humidity,
                'rain'     => round($rain, 1),
                'wind'     => round($windSpeed, 1),
                'wind_deg' => $wind['deg'] ?? null,
                'gust'     => round($windGust, 1),
                'hdw'      => $humidity === null ? null : round(FireDanger::hdw($temp, $humidity, $windSpeed)),
                'weather'  => $weather['description'] ?? '',
                'icon'     => $weather['icon'] ?? '',
                'code'     => $weatherId,
            ];

            // Heavy rain: >15mm per 3h
            if ($rain > 15) {
                $risks[] = [
                    'type'     => 'heavy_rain',
                    'severity' => $rain > 30 ? 'high' : 'nominal',
                    'date'     => $period['date'],
                    'detail'   => round($rain, 1) . ' мм за 3ч',
                ];
            }

            // Thunderstorm / hail: weather codes 2xx
            if ($weatherId >= 200 && $weatherId < 300) {
                $severity = $weatherId >= 230 ? 'nominal' : 'high';
                if (in_array($weatherId, [202, 212, 221, 232])) {
                    $severity = 'high';
                }
                $risks[] = [
                    'type'     => 'hail_storm',
                    'severity' => $severity,
                    'date'     => $period['date'],
                    'detail'   => $weather['description'] ?? 'гроза',
                ];
            }

            // Strong wind: gusts > 15 m/s
            if ($windGust > 15) {
                $risks[] = [
                    'type'     => 'strong_wind',
                    'severity' => $windGust > 25 ? 'high' : 'nominal',
                    'date'     => $period['date'],
                    'detail'   => 'порывы до ' . round($windGust, 0) . ' м/с',
                ];
            }

            $periods[] = $period;
        }

        // Drought risk: 5-day precip < 2mm AND max temp > 30
        if ($totalPrecip < 2 && $maxTemp > 30) {
            $risks[] = [
                'type'     => 'drought',
                'severity' => $maxTemp > 35 ? 'high' : 'nominal',
                'date'     => $periods[0]['date'] ?? '',
                'detail'   => round($totalPrecip, 1) . ' мм за 5 дней, до +' . round($maxTemp, 0) . '°C',
            ];
        }

        // Frost: min temp < 2
        if ($minTemp < 2) {
            $risks[] = [
                'type'     => 'frost',
                'severity' => $minTemp < -2 ? 'high' : 'nominal',
                'date'     => '',
                'detail'   => 'минимум ' . round($minTemp, 1) . '°C',
            ];
        }

        $fireDanger = FireDanger::fromPeriods(array_slice($periods, 0, 16));
        if (in_array($fireDanger['level'], ['high', 'extreme'], true)) {
            $risks[] = [
                'type'     => 'fire_weather',
                'severity' => $fireDanger['level'] === 'extreme' ? 'high' : 'nominal',
                'date'     => $fireDanger['peak_at'] ?? '',
                'detail'   => 'индекс HDW ' . $fireDanger['index'] . ' — ' . $fireDanger['label'],
            ];
        }

        // Deduplicate by type — keep highest severity per type
        $byType = [];
        foreach ($risks as $r) {
            $t = $r['type'];
            if (! isset($byType[$t]) || $this->severityRank($r['severity']) > $this->severityRank($byType[$t]['severity'])) {
                $byType[$t] = $r;
            }
        }

        $overallSeverity = 'low';
        foreach ($byType as $r) {
            if ($this->severityRank($r['severity']) > $this->severityRank($overallSeverity)) {
                $overallSeverity = $r['severity'];
            }
        }

        return [
            'risks'      => array_values($byType),
            'severity'   => $overallSeverity,
            'summary'    => [
                'temp_min'     => round($minTemp, 1),
                'temp_max'     => round($maxTemp, 1),
                'precip_total' => round($totalPrecip, 1),
                'wind_max'     => round($maxWind, 1),
            ],
            'periods'    => array_slice($periods, 0, 16), // 48h of 3h periods
            'fire_danger' => $fireDanger,
            'wind_now'   => ['speed' => $periods[0]['wind'] ?? 0, 'deg' => $periods[0]['wind_deg'] ?? null],
        ];
    }

    private function severityRank(string $s): int
    {
        return match ($s) {
            'high'    => 3,
            'nominal' => 2,
            'low'     => 1,
            default   => 0,
        };
    }

    private function emptyResult(): array
    {
        return [
            'risks'    => [],
            'severity' => 'low',
            'summary'  => ['temp_min' => 0, 'temp_max' => 0, 'precip_total' => 0, 'wind_max' => 0],
            'periods'  => [],
        ];
    }
}
