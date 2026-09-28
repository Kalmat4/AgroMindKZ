<?php

namespace App\Http\Controllers;

use App\Models\Zone;
use App\Services\NasaFirmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    public function __construct(private NasaFirmsService $firms) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'oblast_name' => ['required', 'string', 'max:100'],
            'bbox_west'   => ['required', 'numeric', 'between:-180,180'],
            'bbox_south'  => ['required', 'numeric', 'between:-90,90'],
            'bbox_east'   => ['required', 'numeric', 'between:-180,180'],
            'bbox_north'  => ['required', 'numeric', 'between:-90,90'],
        ]);

        $zone = Zone::updateOrCreate(
            ['user_id' => auth()->id()],
            $data
        );

        return response()->json(['zone' => $zone] + $this->hotspotsFor(
            $zone->bbox_west, $zone->bbox_south, $zone->bbox_east, $zone->bbox_north,
        ));
    }

    public function getFires(): JsonResponse
    {
        $zone = auth()->user()->zone;

        if (! $zone) {
            return response()->json(['hotspots' => [], 'firms_ok' => true, 'zone' => null]);
        }

        return response()->json(['zone' => $zone] + $this->hotspotsFor(
            $zone->bbox_west, $zone->bbox_south, $zone->bbox_east, $zone->bbox_north,
        ));
    }

    public function hotspots(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bbox_west'  => ['required', 'numeric', 'between:-180,180'],
            'bbox_south' => ['required', 'numeric', 'between:-90,90'],
            'bbox_east'  => ['required', 'numeric', 'between:-180,180'],
            'bbox_north' => ['required', 'numeric', 'between:-90,90'],
        ]);

        return response()->json($this->hotspotsFor(
            $data['bbox_west'], $data['bbox_south'], $data['bbox_east'], $data['bbox_north'],
        ));
    }

    /** firms_ok=false — спутниковые данные не получены, фронтенд не должен показывать «пожаров нет». */
    private function hotspotsFor(float $west, float $south, float $east, float $north): array
    {
        $hotspots = $this->firms->getHotspots($west, $south, $east, $north);

        return [
            'hotspots' => $hotspots ?? [],
            'firms_ok' => $hotspots !== null,
        ];
    }
}
