<?php

namespace App\Services;

use App\Models\Field;
use Illuminate\Support\Facades\DB;

/**
 * Угроза полю от термоточек: расстояние, идёт ли ветер от очага к полю,
 * ориентировочное время подхода огня и деньги под угрозой.
 */
class FieldThreatService
{
    public const RADIUS_KM = 50;

    // Направление ветра в пределах ±45° от линии «очаг → поле» считаем ветром на поле
    private const DOWNWIND_TOLERANCE = 45;

    // Скорость распространения ≈ 10 % скорости ветра (Cruz & Alexander, 2019) — грубая оценка
    private const SPREAD_SHARE_OF_WIND = 0.10;

    private const LEVEL_RANK = ['safe' => 0, 'watch' => 1, 'high' => 2, 'critical' => 3];

    public function __construct(
        private NasaFirmsService $firms,
        private OpenWeatherMapService $weather,
    ) {}

    public function assess(Field $field): array
    {
        $dLat = self::RADIUS_KM / 111.0;
        $dLon = self::RADIUS_KM / (111.0 * max(0.2, cos(deg2rad($field->lat))));

        $spots   = $this->firms->getHotspots($field->lon - $dLon, $field->lat - $dLat, $field->lon + $dLon, $field->lat + $dLat);
        $weather = $this->weather->getForecast($field->lat, $field->lon);

        $wind   = $weather['wind_now'] ?? ['speed' => 0, 'deg' => null];
        $danger = $weather['fire_danger'] ?? ['level' => 'unknown', 'label' => 'нет данных', 'index' => null];

        $threats = [];
        foreach ($spots ?? [] as $s) {
            $distance = $this->distanceKm($s['lat'], $s['lon'], $field->lat, $field->lon);
            if ($distance > self::RADIUS_KM) {
                continue;
            }
            $bearing  = $this->bearing($s['lat'], $s['lon'], $field->lat, $field->lon); // куда идти от очага к полю
            $downwind = $this->isDownwind($wind, $bearing);
            $level    = $this->levelFor($distance, $downwind, $danger['level']);

            $spreadKmh = $wind['speed'] * 3.6 * self::SPREAD_SHARE_OF_WIND;
            $threats[] = [
                'key'         => $this->hotspotKey($s),
                'lat'         => $s['lat'],
                'lon'         => $s['lon'],
                'distance_km' => round($distance, 1),
                'direction'   => $this->compass($this->bearing($field->lat, $field->lon, $s['lat'], $s['lon'])), // где очаг относительно поля
                'downwind'    => $downwind,
                'eta_hours'   => $downwind && $spreadKmh > 0.1 ? round($distance / $spreadKmh, 1) : null,
                'frp'         => $s['frp'],
                'severity'    => $s['severity'],
                'detected_at' => trim(($s['acq_date'] ?? '') . ' ' . $this->formatTime($s['acq_time'] ?? '')) . ' UTC',
                'level'       => $level,
            ];
        }

        usort($threats, fn($a, $b) => [self::LEVEL_RANK[$b['level']], $a['distance_km']] <=> [self::LEVEL_RANK[$a['level']], $b['distance_km']]);

        $level = $threats ? $threats[0]['level'] : 'safe';

        return [
            'field_id'    => $field->id,
            'level'       => $level,
            'firms_ok'    => $spots !== null,
            'weather_ok'  => $weather !== null,
            'radius_km'   => self::RADIUS_KM,
            'hotspots'    => count($threats),
            'threats'     => array_slice($threats, 0, 10),
            'nearest'     => $threats ? min(array_column($threats, 'distance_km')) : null,
            'wind'        => ['speed' => $wind['speed'], 'deg' => $wind['deg'], 'from' => $wind['deg'] === null ? null : $this->compass($wind['deg'])],
            'fire_danger' => $danger,
            'damage'      => $this->damage($field),
            'checked_at'  => now()->toIso8601String(),
        ];
    }

    /** Деньги под угрозой: площадь × урожайность × цена. Урожайность — средняя по РК из базы. */
    public function damage(Field $field): array
    {
        $crop = $field->crop_code ? DB::table('crops')->where('code', $field->crop_code)->first() : null;
        $yield = $crop ? DB::table('national_yield_summary')
            ->where('crop_id', $crop->id)
            ->whereNotNull('yield_centner_ha')
            ->orderByDesc('harvest_year')
            ->first(['yield_centner_ha', 'harvest_year']) : null;

        $tons = $yield ? $field->area_ha * $yield->yield_centner_ha / 10 : null;

        return [
            'crop'          => $crop?->name_ru,
            'yield_c_ha'    => $yield ? (float) $yield->yield_centner_ha : null,
            'yield_year'    => $yield?->harvest_year,
            'tons'          => $tons === null ? null : round($tons, $tons < 100 ? 1 : 0),
            'price_per_ton' => $field->price_per_ton,
            'tenge'         => $tons !== null && $field->price_per_ton ? (int) round($tons * $field->price_per_ton) : null,
        ];
    }

    public static function levelLabel(string $level): string
    {
        return ['safe' => 'угрозы нет', 'watch' => 'наблюдение', 'high' => 'высокая угроза', 'critical' => 'критическая угроза'][$level] ?? $level;
    }

    public static function levelRank(string $level): int
    {
        return self::LEVEL_RANK[$level] ?? 0;
    }

    private function levelFor(float $distance, bool $downwind, string $danger): string
    {
        $dryWindy = in_array($danger, ['high', 'extreme'], true);

        if ($distance <= 5 || ($distance <= 20 && $downwind)) {
            return 'critical';
        }
        if ($distance <= 20 || ($downwind && $dryWindy)) {
            return 'high';
        }

        return 'watch';
    }

    private function isDownwind(array $wind, float $bearingToField): bool
    {
        if ($wind['deg'] === null || $wind['speed'] < 1) {
            return false;
        }
        $windTo = fmod($wind['deg'] + 180, 360); // метео-направление — откуда дует
        $diff   = abs(fmod($windTo - $bearingToField + 540, 360) - 180);

        return $diff <= self::DOWNWIND_TOLERANCE;
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function bearing(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $y = sin(deg2rad($lon2 - $lon1)) * cos(deg2rad($lat2));
        $x = cos(deg2rad($lat1)) * sin(deg2rad($lat2)) - sin(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon2 - $lon1));

        return fmod(rad2deg(atan2($y, $x)) + 360, 360);
    }

    private function compass(float $deg): string
    {
        $names = ['С', 'СВ', 'В', 'ЮВ', 'Ю', 'ЮЗ', 'З', 'СЗ'];

        return $names[(int) round($deg / 45) % 8];
    }

    private function hotspotKey(array $s): string
    {
        return sprintf('%.4f,%.4f,%s,%s', $s['lat'], $s['lon'], $s['acq_date'] ?? '', $s['acq_time'] ?? '');
    }

    private function formatTime(string $t): string
    {
        $t = str_pad($t, 4, '0', STR_PAD_LEFT);

        return substr($t, 0, 2) . ':' . substr($t, 2, 2);
    }
}
