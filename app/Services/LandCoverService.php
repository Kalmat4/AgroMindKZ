<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Тип земли под точкой по карте ESA WorldCover 2021 (10 м), CC BY 4.0.
 * Читаем исходные GeoTIFF с S3 кусками: файл 3×3°, берём уменьшенную копию 4500 px (~74 м на пиксель),
 * плитка 1024 px ≈ 75 км — один запрос на район. Плитки кэшируются на диске навсегда: земля не меняется.
 */
class LandCoverService
{
    private const BASE = 'https://esa-worldcover.s3.eu-central-1.amazonaws.com/v200/2021/map/';
    private const LEVEL_WIDTH = 4500;
    private const TILE = 1024;
    private const MAX_FETCH_PER_CALL = 40; // не держать запрос: недостающие плитки догрузятся следующими вызовами

    public const LABELS = [
        10 => 'лес', 20 => 'кустарник', 30 => 'степь, луг', 40 => 'пашня', 50 => 'застройка',
        60 => 'голая земля', 70 => 'снег, лёд', 80 => 'вода', 90 => 'болото', 95 => 'мангры', 100 => 'мох, лишайник',
    ];

    /** @param array<int, array{0: float, 1: float}> $points [lat, lon] @return array<int, int|null> класс WorldCover */
    public function classes(array $points): array
    {
        $result = array_fill_keys(array_keys($points), null);
        $byFile = [];
        foreach ($points as $i => [$lat, $lon]) {
            $byFile[$this->fileName($lat, $lon)][$i] = [$lat, $lon];
        }

        $headers = $this->headers(array_keys($byFile));

        // Какие плитки нужны каждой точке
        $need = [];
        foreach ($byFile as $file => $pts) {
            $h = $headers[$file] ?? null;
            if (! $h) {
                continue;
            }
            foreach ($pts as $i => [$lat, $lon]) {
                $px  = 3 / self::LEVEL_WIDTH;
                $row = (int) floor(($h['lat0'] - $lat) / $px);
                $col = (int) floor(($lon - $h['lon0']) / $px);
                if ($row < 0 || $col < 0 || $row >= self::LEVEL_WIDTH || $col >= self::LEVEL_WIDTH) {
                    continue;
                }
                $tile = intdiv($row, self::TILE) * $h['across'] + intdiv($col, self::TILE);
                $need["{$file}#{$tile}"][] = [$i, $row % self::TILE, $col % self::TILE, $file, $tile];
            }
        }

        $this->fetchTiles($need, $headers);

        foreach ($need as $key => $items) {
            $path = $this->tilePath($items[0][3], $items[0][4]);
            if (! Storage::exists($path)) {
                continue;
            }
            $raw = @gzuncompress(Storage::get($path));
            if ($raw === false) {
                continue;
            }
            foreach ($items as [$i, $r, $c]) {
                $v = ord($raw[$r * self::TILE + $c] ?? "\0");
                $result[$i] = $v ?: null;
            }
        }

        return $result;
    }

    private function fileName(float $lat, float $lon): string
    {
        $la = (int) floor($lat / 3) * 3;
        $lo = (int) floor($lon / 3) * 3;

        return sprintf('ESA_WorldCover_10m_2021_v200_%s%02d%s%03d_Map.tif', $la >= 0 ? 'N' : 'S', abs($la), $lo >= 0 ? 'E' : 'W', abs($lo));
    }

    private function tilePath(string $file, int $tile): string
    {
        return 'worldcover/' . substr($file, 29, 7) . "_{$tile}.bin";
    }

    /** Заголовки файлов: начало координат и смещения плиток нужного уровня. Кэш навсегда. */
    private function headers(array $files): array
    {
        $out = [];
        $missing = [];
        foreach ($files as $f) {
            $cached = Cache::get("worldcover:hdr:{$f}");
            if ($cached !== null) {
                $out[$f] = $cached ?: null;
            } else {
                $missing[] = $f;
            }
        }
        if (! $missing) {
            return $out;
        }

        $responses = Http::pool(fn($pool) => array_map(
            fn($f) => $pool->as($f)->timeout(20)->withHeaders(['Range' => 'bytes=0-65535'])->get(self::BASE . $f),
            $missing
        ));

        foreach ($missing as $f) {
            $r = $responses[$f] ?? null;
            if (! $r instanceof \Illuminate\Http\Client\Response) {
                continue; // сеть — попробуем в следующий раз, не кэшируем
            }
            if ($r->status() === 404 || $r->status() === 403) {
                Cache::forever("worldcover:hdr:{$f}", false); // файла нет (море, вне покрытия)
                $out[$f] = null;
                continue;
            }
            $parsed = $r->successful() ? $this->parseHeader($r->body()) : null;
            if ($parsed) {
                Cache::forever("worldcover:hdr:{$f}", $parsed);
            } else {
                Log::warning('WorldCover header unreadable', ['file' => $f, 'status' => $r->status()]);
            }
            $out[$f] = $parsed;
        }

        return $out;
    }

    /** Минимальный разбор классического little-endian TIFF: ищем IFD шириной 4500 и геопривязку первого IFD. */
    private function parseHeader(string $b): ?array
    {
        if (substr($b, 0, 4) !== "II*\0") {
            return null;
        }
        $u16 = fn($o) => unpack('v', substr($b, $o, 2))[1];
        $u32 = fn($o) => unpack('V', substr($b, $o, 4))[1];
        $f64 = fn($o) => unpack('e', substr($b, $o, 8))[1];

        $lat0 = $lon0 = null;
        $level = null;
        $off = $u32(4);
        for ($n = 0; $off && $off + 2 <= strlen($b) && $n < 12; $n++) {
            $count = $u16($off);
            $tags = [];
            for ($i = 0; $i < $count; $i++) {
                $e = $off + 2 + $i * 12;
                $type = $u16($e + 2);
                $cnt = $u32($e + 4);
                $inline = $cnt * ([3 => 2, 4 => 4, 12 => 8][$type] ?? 1) <= 4;
                $tags[$u16($e)] = ['count' => $cnt, 'type' => $type, 'value' => $inline ? ($type === 3 ? $u16($e + 8) : $u32($e + 8)) : null, 'at' => $inline ? null : $u32($e + 8)];
            }
            if ($n === 0 && isset($tags[33922]['at'])) {
                $lon0 = $f64($tags[33922]['at'] + 24);
                $lat0 = $f64($tags[33922]['at'] + 32);
            }
            if (($tags[256]['value'] ?? null) === self::LEVEL_WIDTH && ($tags[259]['value'] ?? null) === 8 && ($tags[317]['value'] ?? 1) === 1) {
                $cnt = $tags[324]['count'];
                if (isset($tags[324]['at'], $tags[325]['at']) && $tags[325]['at'] + $cnt * 4 <= strlen($b)) {
                    $level = [
                        'offsets' => array_values(unpack("V{$cnt}", substr($b, $tags[324]['at'], $cnt * 4))),
                        'sizes'   => array_values(unpack("V{$cnt}", substr($b, $tags[325]['at'], $cnt * 4))),
                        'across'  => (int) ceil(self::LEVEL_WIDTH / ($tags[322]['value'] ?? self::TILE)),
                    ];
                }
            }
            $off = $u32($off + 2 + $count * 12);
        }

        return ($level && $lat0 !== null) ? $level + ['lat0' => $lat0, 'lon0' => $lon0] : null;
    }

    private function fetchTiles(array $need, array $headers): void
    {
        $todo = [];
        foreach ($need as $key => $items) {
            [, , , $file, $tile] = $items[0];
            if (! Storage::exists($this->tilePath($file, $tile))) {
                $todo[$key] = [$file, $tile];
            }
        }
        $todo = array_slice($todo, 0, self::MAX_FETCH_PER_CALL, true);
        if (! $todo) {
            return;
        }

        $responses = Http::pool(function ($pool) use ($todo, $headers) {
            $reqs = [];
            foreach ($todo as $key => [$file, $tile]) {
                $h = $headers[$file];
                $from = $h['offsets'][$tile];
                $to = $from + $h['sizes'][$tile] - 1;
                $reqs[] = $pool->as($key)->timeout(20)->withHeaders(['Range' => "bytes={$from}-{$to}"])->get(self::BASE . $file);
            }

            return $reqs;
        });

        foreach ($todo as $key => [$file, $tile]) {
            $r = $responses[$key] ?? null;
            if ($r instanceof \Illuminate\Http\Client\Response && $r->status() === 206 && @gzuncompress($r->body()) !== false) {
                Storage::put($this->tilePath($file, $tile), $r->body());
            }
        }
    }
}
