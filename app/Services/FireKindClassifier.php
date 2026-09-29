<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * «Вероятный пал стерни» или «возможный пожар» — по признакам, а не по факту: со спутника они выглядят одинаково.
 * Пал: точка на пашне (ESA WorldCover), в сезон палов, небольшая мощность огня и не держится несколько пролётов.
 */
class FireKindClassifier
{
    // После уборки (август–ноябрь) и перед севом (март–май)
    private const BURN_MONTHS = [3, 4, 5, 8, 9, 10, 11];
    private const MAX_BURN_FRP = 20;      // МВт; крупнее — ведём себя как с пожаром
    private const CLUSTER_KM = 1.5;       // соседние пиксели одного очага
    private const PASS_GAP_HOURS = 3;     // разные пролёты спутника

    public function __construct(private LandCoverService $land) {}

    public function classify(array $spots): array
    {
        if (! $spots) {
            return $spots;
        }

        try {
            $classes = $this->land->classes(array_map(fn($s) => [$s['lat'], $s['lon']], $spots));
        } catch (\Throwable $e) {
            Log::warning('Land cover lookup failed', ['message' => $e->getMessage()]);
            $classes = array_fill_keys(array_keys($spots), null);
        }

        $times = array_map(fn($s) => $this->time($s), $spots);

        foreach ($spots as $i => &$s) {
            $landClass = $classes[$i] ?? null;
            [$maxFrp, $persistent] = $this->cluster($spots, $times, $i);
            $month = $times[$i] ? (int) date('n', $times[$i]) : null;

            $reasons = [];
            $isCrop = $landClass === 40;
            $inSeason = $month !== null && in_array($month, self::BURN_MONTHS, true);
            $small = $maxFrp < self::MAX_BURN_FRP;

            if ($landClass !== null) {
                $reasons[] = 'под точкой: ' . (LandCoverService::LABELS[$landClass] ?? 'другое');
            }
            if ($isCrop) {
                $reasons[] = $inSeason ? 'сезон палов стерни' : 'не сезон палов';
                $reasons[] = $small ? 'небольшая мощность огня' : 'мощный огонь';
                $reasons[] = $persistent ? 'держится несколько пролётов' : 'не держится между пролётами';
            }

            $s['land'] = $landClass;
            $s['land_label'] = $landClass !== null ? (LandCoverService::LABELS[$landClass] ?? 'другое') : null;
            $s['kind'] = ($isCrop && $inSeason && $small && ! $persistent) ? 'stubble' : 'fire';
            $s['kind_reasons'] = $reasons;
        }

        return $spots;
    }

    /** Максимальная мощность в очаге и держится ли он в разных пролётах спутника. */
    private function cluster(array $spots, array $times, int $i): array
    {
        $maxFrp = $spots[$i]['frp'];
        $passes = [];
        foreach ($spots as $j => $o) {
            if ($this->km($spots[$i]['lat'], $spots[$i]['lon'], $o['lat'], $o['lon']) > self::CLUSTER_KM) {
                continue;
            }
            $maxFrp = max($maxFrp, $o['frp']);
            if ($times[$j]) {
                $passes[] = $times[$j];
            }
        }
        sort($passes);
        $persistent = $passes && (end($passes) - $passes[0]) >= self::PASS_GAP_HOURS * 3600;

        return [$maxFrp, $persistent];
    }

    private function time(array $s): ?int
    {
        if (empty($s['acq_date'])) {
            return null;
        }
        $t = str_pad((string) ($s['acq_time'] ?? ''), 4, '0', STR_PAD_LEFT);

        return strtotime("{$s['acq_date']} " . substr($t, 0, 2) . ':' . substr($t, 2, 2) . ' UTC') ?: null;
    }

    private function km(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = ($lat2 - $lat1) * 111.0;
        $dLon = ($lon2 - $lon1) * 111.0 * cos(deg2rad(($lat1 + $lat2) / 2));

        return sqrt($dLat * $dLat + $dLon * $dLon);
    }
}
