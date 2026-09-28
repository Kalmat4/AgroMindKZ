<?php

namespace App\Services;

/**
 * Пожароопасность погоды по индексу Hot-Dry-Windy (Srock et al., 2018):
 * дефицит упругости водяного пара (гПа) × скорость ветра (м/с).
 * Считается только из прогноза, без истории осадков, поэтому это оценка погодных условий,
 * а не официальный класс пожарной опасности (в РК — индекс Нестерова).
 */
class FireDanger
{
    // Пороги подобраны под степь: сухой ветреный день +30 °C, 20 %, 8 м/с даёт ~270
    private const LEVELS = [
        ['max' => 50,  'level' => 'low',      'label' => 'низкая'],
        ['max' => 120, 'level' => 'moderate', 'label' => 'умеренная'],
        ['max' => 250, 'level' => 'high',     'label' => 'высокая'],
        ['max' => INF, 'level' => 'extreme',  'label' => 'чрезвычайная'],
    ];

    public static function hdw(float $tempC, float $humidity, float $windMs): float
    {
        $es  = 6.112 * exp(17.67 * $tempC / ($tempC + 243.5)); // насыщение, гПа (формула Магнуса)
        $vpd = max(0.0, $es * (1 - $humidity / 100));

        return $vpd * max(0.0, $windMs);
    }

    /** Максимум индекса по периодам прогноза; осадки ≥ 5 мм снижают уровень на ступень. */
    public static function fromPeriods(array $periods): array
    {
        $peak = null;
        $rain = 0.0;
        foreach ($periods as $p) {
            $rain += $p['rain'] ?? 0;
            if (($p['hdw'] ?? null) !== null && ($peak === null || $p['hdw'] > $peak['hdw'])) {
                $peak = $p;
            }
        }

        if ($peak === null) {
            return ['index' => null, 'level' => 'unknown', 'label' => 'нет данных', 'peak_at' => null, 'rain_mm' => round($rain, 1)];
        }

        $i = 0;
        while ($peak['hdw'] > self::LEVELS[$i]['max']) {
            $i++;
        }
        if ($rain >= 5 && $i > 0) {
            $i--;
        }

        return [
            'index'   => (int) $peak['hdw'],
            'level'   => self::LEVELS[$i]['level'],
            'label'   => self::LEVELS[$i]['label'],
            'peak_at' => $peak['date'] ?? null,
            'rain_mm' => round($rain, 1),
        ];
    }
}
