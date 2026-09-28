<?php

namespace App\Services;

class AgronomistContextService
{
    public function __construct(private NasaFirmsService $firms, private OpenWeatherMapService $weather) {}

    public function build(?array $field, ?array $region): array
    {
        $point = $field ? ['lat' => (float) $field['lat'], 'lon' => (float) $field['lon']] : null;
        $bbox = null;
        if ($point) {
            // Search rectangle, not a field boundary or a fixed distance radius.
            $bbox = [
                'west' => max(-180, $point['lon'] - 0.1),
                'south' => max(-90, $point['lat'] - 0.1),
                'east' => min(180, $point['lon'] + 0.1),
                'north' => min(90, $point['lat'] + 0.1),
            ];
        } elseif ($region) {
            $point = ['lat' => ($region['south'] + $region['north']) / 2, 'lon' => ($region['west'] + $region['east']) / 2];
            $bbox = $region;
        }

        $forecast = $point ? $this->weather->getForecast($point['lat'], $point['lon']) : null;
        // The existing weather service returns zeroes for an empty response. They are not observations.
        if (empty($forecast['periods'])) {
            $forecast = null;
        }
        $spots = $bbox ? $this->firms->getHotspots($bbox['west'], $bbox['south'], $bbox['east'], $bbox['north']) : null;
        return [
            'assembled_at' => now()->toIso8601String(),
            'location' => [
                'scope' => $field ? 'selected_point' : ($region ? 'region_center_approximation' : 'unknown'),
                'point' => $point,
                // A selected point may be outside the currently displayed oblast.
                'region_name' => $field ? null : ($region['name'] ?? null),
            ],
            'field' => $field,
            'weather' => [
                'source' => 'OpenWeatherMap',
                'status' => $forecast ? 'available' : 'unavailable',
                'cache_ttl_seconds' => 1800,
                'units' => ['temperature' => 'C', 'precipitation' => 'mm', 'wind' => 'm/s'],
                'summary_horizon' => 'provider forecast, up to 5 days',
                'periods_horizon' => 'up to 48 hours; dates in UTC',
                'data' => $forecast,
            ],
            'thermal_anomalies' => [
                'source' => 'NASA FIRMS VIIRS SNPP NRT',
                'status' => $spots === null ? 'unavailable' : 'available',
                'lookback_hours' => 48,
                'cache_ttl_seconds' => 600,
                'search_bbox' => $bbox,
                'count' => $spots === null ? null : count($spots),
                'sample' => $spots === null ? null : array_slice($spots, 0, 20),
                'note' => 'Thermal anomalies are not confirmed fires. Search rectangle is not a field boundary. Zero detections does not guarantee safety.',
            ],
            'unavailable_measurements' => ['soil_moisture', 'soil_analysis', 'NDVI', 'confirmed_diagnosis'],
        ];
    }
}
