<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NasaFirmsService
{
    private const MAP_KEY = 'd445b78e682ba7959cbf75bd1c3dbc8d';
    private const SOURCE  = 'VIIRS_SNPP_NRT';
    private const DAYS    = 2;

    public function getHotspots(float $west, float $south, float $east, float $north): array
    {
        $area = implode(',', [$west, $south, $east, $north]);
        $url  = sprintf(
            'https://firms.modaps.eosdis.nasa.gov/api/area/csv/%s/%s/%s/%d/',
            self::MAP_KEY,
            self::SOURCE,
            $area,
            self::DAYS
        );

        try {
            $response = Http::timeout(15)->get($url);

            if (! $response->successful()) {
                Log::warning('NASA FIRMS non-200', ['status' => $response->status()]);
                return [];
            }

            return $this->parseCsv($response->body());
        } catch (\Throwable $e) {
            Log::error('NASA FIRMS request failed', ['message' => $e->getMessage()]);
            return [];
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

            $result[] = [
                'lat'        => (float) $row['latitude'],
                'lon'        => (float) $row['longitude'],
                'brightness' => (float) ($row['bright_ti4'] ?? 0),
                'confidence' => $row['confidence'] ?? '',
                'frp'        => (float) ($row['frp'] ?? 0),
                'daynight'   => $row['daynight'] ?? '',
            ];
        }

        return $result;
    }
}
