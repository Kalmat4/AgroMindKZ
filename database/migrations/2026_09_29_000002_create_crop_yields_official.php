<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Официальная урожайность Бюро национальной статистики АСПиР РК, ц/га, все категории хозяйств.
 * Сборники «Валовый сбор сельскохозяйственных культур в Республике Казахстан за N год. Том III. Урожайность».
 * Заменяет для расчёта ущерба national_yield_summary, где числа были собраны из СМИ и в основном не совпадали.
 */
return new class extends Migration
{
    private const SOURCES = [
        2022 => 'https://stat.gov.kz/upload/iblock/739/7bauy1hjwwgiib5qsr76avda4xgm9rbz/3-%D1%82%D0%BE%D0%BC%202022%20%D1%80%D1%83%D1%81.xlsx',
        2023 => 'https://stat.gov.kz/api/iblock/element/119388/file/ru/',
        2024 => 'https://stat.gov.kz/api/iblock/element/301856/file/ru/',
        2025 => 'https://stat.gov.kz/api/iblock/element/474891/file/ru/',
    ];

    // По стране: [код культуры => [таблица тома III, [2022, 2023, 2024, 2025]]]
    private const NATIONAL = [
        'wheat'      => ['4.7.2 пшеница яровая', [12.6, 9.0, 14.0, 16.0]],
        'barley'     => ['4.9 ячмень', [15.1, 10.8, 16.8, 15.7]],
        'sunflower'  => ['4.19.2.1 подсолнечник, вес после доработки', [12.0, 11.0, 14.6, 13.9]],
        'potato'     => ['5.3 картофель', [205.4, 220.1, 219.1, 222.5]],
        'vegetable'  => ['5.1 овощи открытого грунта', [271.3, 271.9, 284.2, 286.9]],
        'corn'       => ['4.8 кукуруза на зерно', [58.3, 62.5, 62.0, 57.0]],
        'rice'       => ['4.18 рис', [49.1, 48.5, 52.2, 51.9]],
        'flax'       => ['4.19.6 лён-кудряш', [6.3, 5.0, 8.7, 10.0]],
        'canola'     => ['4.19.4 рапс', [14.2, 13.3, 19.2, 19.5]],
        'buckwheat'  => ['4.14 гречиха', [7.5, 7.3, 9.5, 9.8]],
        'sugar_beet' => ['5.4.1 сахарная свёкла', [341.4, 379.0, 507.3, 414.3]],
    ];

    // По областям. Яровая пшеница в KOS, AKM, PAV, KAR, ULY совпадает со всей пшеницей (табл. 4.7);
    // по южным областям, где сеют в основном озимую, яровая отдельно не выгружалась — там берётся среднее по РК.
    private const REGIONAL = [
        'wheat' => ['4.7.2 пшеница яровая', [
            'KOS' => [13.7, 10.5, 13.0, 16.0],
            'AKM' => [11.5, 6.9, 12.4, 15.4],
            'SKO' => [14.7, 11.4, 18.6, 20.7],
            'PAV' => [9.5, 4.1, 11.2, 13.7],
            'KAR' => [8.8, 6.8, 14.5, 12.5],
            'AKT' => [13.6, 9.9, 11.9, 10.7],
            'ZKO' => [14.1, 9.6, 7.2, 7.6],
            'VKO' => [17.0, 12.1, 22.2, 20.4],
            'ABI' => [9.8, 10.2, 14.4, 10.0],
            'ULY' => [10.0, 8.5, 9.6, 9.2],
        ]],
        'barley' => ['4.9 ячмень', [
            'KOS' => [15.0, 12.1, 15.5, 15.5],
            'AKM' => [12.8, 7.4, 18.1, 16.7],
            'SKO' => [16.1, 11.2, 18.6, 21.2],
            'PAV' => [9.9, 4.5, 11.2, 12.4],
            'KAR' => [9.0, 6.4, 14.6, 9.8],
            'AKT' => [17.5, 11.8, 15.0, 11.2],
            'ZKO' => [15.2, 7.5, 8.1, 8.5],
            'VKO' => [23.0, 17.3, 23.8, 25.0],
            'ABI' => [10.0, 10.9, 14.5, 9.7],
            'ULY' => [7.3, 15.8, 7.4, 3.7],
        ]],
    ];

    public function up(): void
    {
        Schema::create('crop_yields', function (Blueprint $table) {
            $table->id();
            $table->string('crop_code', 20);
            $table->string('region_code', 10)->nullable(); // null — в среднем по Казахстану
            $table->smallInteger('harvest_year');
            $table->decimal('yield_c_ha', 6, 1);
            $table->string('source', 255);
            $table->string('source_url', 255);
            $table->unique(['crop_code', 'region_code', 'harvest_year']);
        });

        Schema::table('fields', function (Blueprint $table) {
            // null — область ещё не определяли, '' — определяли, но не нашли
            $table->string('region_code', 10)->nullable()->after('lon');
        });

        $rows = [];
        foreach (self::NATIONAL as $crop => [$table, $values]) {
            $rows = array_merge($rows, $this->rows($crop, null, $table, $values));
        }
        foreach (self::REGIONAL as $crop => [$table, $regions]) {
            foreach ($regions as $region => $values) {
                $rows = array_merge($rows, $this->rows($crop, $region, $table, $values));
            }
        }
        DB::table('crop_yields')->insert($rows);
    }

    private function rows(string $crop, ?string $region, string $table, array $values): array
    {
        $rows = [];
        foreach ([2022, 2023, 2024, 2025] as $i => $year) {
            $rows[] = [
                'crop_code'    => $crop,
                'region_code'  => $region,
                'harvest_year' => $year,
                'yield_c_ha'   => $values[$i],
                'source'       => "Бюро национальной статистики АСПиР РК, «Валовый сбор с/х культур за {$year} год», т. III, табл. {$table}",
                'source_url'   => self::SOURCES[$year],
            ];
        }

        return $rows;
    }

    public function down(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            $table->dropColumn('region_code');
        });
        Schema::dropIfExists('crop_yields');
    }
};
