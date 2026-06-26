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

        return response()->json([
            'zone'     => $zone,
            'hotspots' => $this->firms->getHotspots(
                $zone->bbox_west,
                $zone->bbox_south,
                $zone->bbox_east,
                $zone->bbox_north,
            ),
        ]);
    }

    public function getFires(): JsonResponse
    {
        $zone = auth()->user()->zone;

        if (! $zone) {
            return response()->json(['hotspots' => [], 'zone' => null]);
        }

        return response()->json([
            'zone'     => $zone,
            'hotspots' => $this->firms->getHotspots(
                $zone->bbox_west,
                $zone->bbox_south,
                $zone->bbox_east,
                $zone->bbox_north,
            ),
        ]);
    }

    public function hotspots(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bbox_west'  => ['required', 'numeric', 'between:-180,180'],
            'bbox_south' => ['required', 'numeric', 'between:-90,90'],
            'bbox_east'  => ['required', 'numeric', 'between:-180,180'],
            'bbox_north' => ['required', 'numeric', 'between:-90,90'],
        ]);

        return response()->json([
            'hotspots' => $this->firms->getHotspots(
                $data['bbox_west'],
                $data['bbox_south'],
                $data['bbox_east'],
                $data['bbox_north'],
            ),
        ]);
    }
}
