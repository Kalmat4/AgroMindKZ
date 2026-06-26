<?php

namespace App\Http\Controllers;

use App\Services\NasaFirmsService;
use App\Services\OpenWeatherMapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForecastController extends Controller
{
    public function __construct(
        private OpenWeatherMapService $weather,
        private NasaFirmsService $firms,
    ) {}

    public function risks(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat'        => ['required', 'numeric', 'between:-90,90'],
            'lon'        => ['required', 'numeric', 'between:-180,180'],
            'bbox_west'  => ['required', 'numeric', 'between:-180,180'],
            'bbox_south' => ['required', 'numeric', 'between:-90,90'],
            'bbox_east'  => ['required', 'numeric', 'between:-180,180'],
            'bbox_north' => ['required', 'numeric', 'between:-90,90'],
        ]);

        $forecast = $this->weather->getForecast($data['lat'], $data['lon']);

        $hotspots = $this->firms->getHotspots(
            $data['bbox_west'],
            $data['bbox_south'],
            $data['bbox_east'],
            $data['bbox_north'],
        );

        $fireCount = count($hotspots);
        if ($fireCount > 0 && $forecast) {
            $fireSeverity = $fireCount >= 10 ? 'high' : ($fireCount >= 4 ? 'nominal' : 'low');
            $forecast['risks'][] = [
                'type'     => 'fire',
                'severity' => $fireSeverity,
                'date'     => '',
                'detail'   => $fireCount . ' очаг(ов) за 48ч',
            ];
            if ($this->rank($fireSeverity) > $this->rank($forecast['severity'])) {
                $forecast['severity'] = $fireSeverity;
            }
        }

        return response()->json([
            'forecast'   => $forecast,
            'fire_count' => $fireCount,
        ]);
    }

    private function rank(string $s): int
    {
        return match ($s) {
            'high'    => 3,
            'nominal' => 2,
            'low'     => 1,
            default   => 0,
        };
    }
}
