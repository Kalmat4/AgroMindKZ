<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
import { Chart, registerables } from 'chart.js'
Chart.register(...registerables)

const props = defineProps({
    national_summary: { type: Array,  default: () => [] },
    by_region_year:   { type: Array,  default: () => [] },
    wheat_top3:       { type: Array,  default: () => [] },
    stats:            { type: Object, default: () => ({}) },
})

function fmtNum(val, decimals = 0) {
    if (val == null) return '—'
    return Number(val).toLocaleString('ru-RU', { maximumFractionDigits: decimals })
}

function fmtMln(val) {
    if (!val) return '—'
    return (val / 1_000_000).toFixed(1) + ' млн т'
}

function qualityLabel(grade) {
    const map = { '1': '1 класс', '2': '2 класс', '3': '3 класс', '4': '4 класс (фураж)' }
    return map[grade] ?? '—'
}

function qualityClass(grade) {
    if (grade === '1') return 'badge badge--green'
    if (grade === '2') return 'badge badge--blue'
    if (grade === '3') return 'badge badge--yellow'
    if (grade === '4') return 'badge badge--orange'
    return 'badge badge--gray'
}

// ── Wheat chart ───────────────────────────────────────────────────────────────
const chartEl = ref(null)
let chartInstance = null

const CHART_YEARS = ['2022', '2023', '2024']
const CHART_REGIONS = [
    { name: 'Акмолинская',          color: '#ff6b35' },
    { name: 'Костанайская',         color: '#4db8ff' },
    { name: 'Северо-Казахстанская', color: '#4ade80' },
]

onMounted(() => {
    const lookup = {}
    props.wheat_top3.forEach(row => {
        if (!lookup[row.region_name]) lookup[row.region_name] = {}
        lookup[row.region_name][String(row.harvest_year)] = row.yield_centner_ha
    })

    const datasets = CHART_REGIONS.map(r => ({
        label: r.name,
        data: CHART_YEARS.map(y => lookup[r.name]?.[y] ?? null),
        borderColor: r.color,
        backgroundColor: r.color + '18',
        tension: 0.35,
        pointRadius: 4,
        pointHoverRadius: 6,
        borderWidth: 2,
        fill: false,
        spanGaps: true,
    }))

    chartInstance = new Chart(chartEl.value, {
        type: 'line',
        data: { labels: CHART_YEARS, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: '#888', font: { size: 12 }, boxWidth: 16, padding: 20 },
                },
                tooltip: { mode: 'index', intersect: false },
            },
            scales: {
                x: {
                    ticks: { color: '#888', font: { size: 12 } },
                    grid: { color: '#2d2d2d' },
                },
                y: {
                    ticks: { color: '#888', font: { size: 12 } },
                    grid: { color: '#2d2d2d' },
                    title: { display: true, text: 'ц/га', color: '#888', font: { size: 12 } },
                },
            },
        },
    })
})

onBeforeUnmount(() => {
    chartInstance?.destroy()
})
</script>

<template>
    <AppLayout>
        <Head title="Урожайность — AgroMind KZ" />

        <div class="yld-bg">
        <div class="yld-page">

            <div class="yld-page-title">Урожайность Казахстана</div>

            <!-- Section 1: Stats cards -->
            <div class="yld-cards">
                <div class="yld-card">
                    <div class="yld-card__label">Валовый сбор пшеницы {{ stats.latest_year }}</div>
                    <div class="yld-card__value">{{ fmtMln(stats.wheat_gross_harvest) }}</div>
                </div>
                <div class="yld-card yld-card--green">
                    <div class="yld-card__label">Ср. урожайность пшеницы {{ stats.latest_year }}</div>
                    <div class="yld-card__value yld-card__value--green">{{ stats.avg_wheat_yield }} ц/га</div>
                </div>
                <div class="yld-card">
                    <div class="yld-card__label">Экспорт пшеницы {{ stats.latest_year }}</div>
                    <div class="yld-card__value">{{ fmtMln(stats.wheat_export) }}</div>
                </div>
                <div class="yld-card yld-card--orange">
                    <div class="yld-card__label">Аномалий урожайности</div>
                    <div class="yld-card__value">
                        <span class="badge badge--orange">{{ stats.anomalies_count }}</span>
                    </div>
                </div>
            </div>

            <!-- Chart: Wheat yield dynamics -->
            <div class="yld-section">
                <div class="yld-section__title">Динамика урожайности пшеницы — топ регионы</div>
                <div class="yld-chart-wrap">
                    <canvas ref="chartEl" class="yld-chart"></canvas>
                </div>
            </div>

            <!-- Section 2: National summary -->
            <div class="yld-section">
                <div class="yld-section__title">Национальная сводка урожайности</div>
                <div class="yld-table-wrap">
                    <table class="yld-table">
                        <thead>
                            <tr>
                                <th>Культура</th>
                                <th>Год</th>
                                <th>Урожайность (ц/га)</th>
                                <th>Валовый сбор (т)</th>
                                <th>Посевная площадь (га)</th>
                                <th>Объём экспорта (т)</th>
                                <th>Доля экспорта %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in national_summary" :key="i">
                                <td class="yld-crop-name">{{ row.crop_name }}</td>
                                <td>{{ row.harvest_year }}</td>
                                <td>{{ fmtNum(row.yield_centner_ha, 2) }}</td>
                                <td>{{ fmtNum(row.gross_harvest_ton) }}</td>
                                <td>{{ fmtNum(row.total_sown_area_ha) }}</td>
                                <td>{{ fmtNum(row.export_volume_ton) }}</td>
                                <td>
                                    <span v-if="row.export_share_pct != null">
                                        {{ Number(row.export_share_pct).toFixed(1) }} %
                                    </span>
                                    <span v-else class="yld-none">—</span>
                                </td>
                            </tr>
                            <tr v-if="!national_summary.length">
                                <td colspan="7" class="yld-table__empty">Нет данных</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Section 3: Wheat Top-3 dynamics -->
            <div class="yld-section">
                <div class="yld-section__title">Динамика урожайности пшеницы — Топ-3 региона (AKM, KOS, SKO)</div>
                <div class="yld-table-wrap">
                    <table class="yld-table">
                        <thead>
                            <tr>
                                <th>Регион</th>
                                <th>Год</th>
                                <th>Урожайность (ц/га)</th>
                                <th>Валовый сбор (т)</th>
                                <th>Класс качества</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in wheat_top3" :key="i">
                                <td>{{ row.region_name }}</td>
                                <td>{{ row.harvest_year }}</td>
                                <td :class="{ 'yld-low': row.yield_centner_ha < 9, 'yld-high': row.yield_centner_ha >= 14 }">
                                    {{ fmtNum(row.yield_centner_ha, 2) }}
                                </td>
                                <td>{{ fmtNum(row.gross_harvest_ton) }}</td>
                                <td>
                                    <span :class="qualityClass(row.quality_grade)">
                                        {{ qualityLabel(row.quality_grade) }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!wheat_top3.length">
                                <td colspan="5" class="yld-table__empty">Нет данных</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Section 4: By region and year -->
            <div class="yld-section">
                <div class="yld-section__title">Урожайность по регионам и культурам</div>
                <div class="yld-table-wrap">
                    <table class="yld-table">
                        <thead>
                            <tr>
                                <th>Регион</th>
                                <th>Культура</th>
                                <th>Год</th>
                                <th>Урожайность (ц/га)</th>
                                <th>Валовый сбор (т)</th>
                                <th>Посевная пл. (га)</th>
                                <th>Убранная пл. (га)</th>
                                <th>Класс</th>
                                <th>Примечания</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in by_region_year" :key="i">
                                <td>{{ row.region_name }}</td>
                                <td class="yld-crop-name">{{ row.crop_name }}</td>
                                <td>{{ row.harvest_year }}</td>
                                <td :class="{ 'yld-low': row.yield_centner_ha < 9, 'yld-high': row.yield_centner_ha >= 16 }">
                                    {{ fmtNum(row.yield_centner_ha, 2) }}
                                </td>
                                <td>{{ fmtNum(row.gross_harvest_ton) }}</td>
                                <td>{{ fmtNum(row.sown_area_ha) }}</td>
                                <td>{{ fmtNum(row.harvested_area_ha) }}</td>
                                <td>
                                    <span v-if="row.quality_grade" :class="qualityClass(row.quality_grade)">
                                        {{ row.quality_grade }}
                                    </span>
                                    <span v-else class="yld-none">—</span>
                                </td>
                                <td class="yld-notes">{{ row.notes ?? '—' }}</td>
                            </tr>
                            <tr v-if="!by_region_year.length">
                                <td colspan="9" class="yld-table__empty">Нет данных</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* ── Background & page ────────────────────────────────────────────────────── */
.yld-bg {
    background: #111;
    min-height: calc(100vh - 64px);
}
.yld-page {
    padding: 28px 32px;
    max-width: 1600px;
    margin: 0 auto;
}
.yld-page-title {
    font-size: 22px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 24px;
    letter-spacing: 0.3px;
}

/* ── Stat cards ───────────────────────────────────────────────────────────── */
.yld-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 32px;
}
.yld-card {
    background: #1e1e1e;
    border: 1px solid #2d2d2d;
    border-radius: 12px;
    padding: 20px 22px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.yld-card--green  { border-color: rgba(34,197,94,0.35);  background: #101a12; }
.yld-card--orange { border-color: rgba(234,88,12,0.35);  background: #1a1208; }
.yld-card__label { font-size: 12px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
.yld-card__value { font-size: 24px; font-weight: 700; color: #fff; }
.yld-card__value--green { color: #4ade80; }

/* ── Badges ───────────────────────────────────────────────────────────────── */
.badge {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}
.badge--green  { background: rgba(34,197,94,0.15);  color: #4ade80; border: 1px solid rgba(34,197,94,0.35); }
.badge--blue   { background: rgba(59,130,246,0.15); color: #60a5fa; border: 1px solid rgba(59,130,246,0.35); }
.badge--yellow { background: rgba(202,138,4,0.15);  color: #facc15; border: 1px solid rgba(202,138,4,0.35); }
.badge--orange { background: rgba(234,88,12,0.15);  color: #fb923c; border: 1px solid rgba(234,88,12,0.35); }
.badge--gray   { background: rgba(100,100,100,0.15);color: #888;    border: 1px solid #333; }

/* ── Sections ─────────────────────────────────────────────────────────────── */
.yld-section { margin-bottom: 36px; }
.yld-section__title {
    font-size: 16px;
    font-weight: 700;
    color: #e0d6cc;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 2px solid #0b2014;
}

/* ── Table ────────────────────────────────────────────────────────────────── */
.yld-table-wrap {
    overflow-x: auto;
    border-radius: 10px;
    border: 1px solid #2d2d2d;
}
.yld-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.yld-table thead tr { background: #1a1a1a; }
.yld-table th {
    padding: 11px 14px;
    text-align: left;
    color: #888;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    white-space: nowrap;
    border-bottom: 1px solid #2d2d2d;
}
.yld-table tbody tr {
    background: #161616;
    border-bottom: 1px solid #222;
    transition: background 0.12s;
}
.yld-table tbody tr:hover { background: #1e1e1e; }
.yld-table td {
    padding: 10px 14px;
    color: #c8bfb5;
    vertical-align: middle;
}
.yld-table__empty {
    text-align: center;
    padding: 28px;
    color: #555;
    font-style: italic;
}

/* ── Cell highlights ──────────────────────────────────────────────────────── */
.yld-crop-name { color: #e0d6cc; font-weight: 600; }
.yld-low  { color: #f87171 !important; font-weight: 600; }
.yld-high { color: #4ade80 !important; font-weight: 600; }
.yld-none { color: #555; }
.yld-notes {
    color: #777;
    font-size: 12px;
    max-width: 280px;
    white-space: normal;
    line-height: 1.4;
}

/* ── Chart ────────────────────────────────────────────────────────────────── */
.yld-chart-wrap {
    background: #1e1e1e;
    border: 1px solid #2d2d2d;
    border-radius: 10px;
    padding: 20px 24px;
    height: 280px;
    position: relative;
}

.yld-chart {
    width: 100% !important;
    height: 100% !important;
}
</style>

<style>
html[data-theme="light"] .yld-bg           { background: var(--afs-bg) !important; }
html[data-theme="light"] .yld-page-title   { color: var(--afs-text); }
html[data-theme="light"] .yld-card         { background: #ffffff; border-color: var(--afs-border); box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
html[data-theme="light"] .yld-card--green  { background: #f0faf4 !important; border-color: rgba(34,197,94,0.3) !important; }
html[data-theme="light"] .yld-card--orange { background: #fff8f4 !important; border-color: rgba(234,88,12,0.3) !important; }
html[data-theme="light"] .yld-card__label  { color: var(--afs-muted); }
html[data-theme="light"] .yld-card__value  { color: var(--afs-text); }
html[data-theme="light"] .yld-section__title   { color: var(--afs-text); border-bottom-color: var(--afs-border); }
html[data-theme="light"] .yld-table-wrap       { border-color: var(--afs-border); }
html[data-theme="light"] .yld-table            { background: #ffffff; }
html[data-theme="light"] .yld-table thead tr   { background: var(--afs-bg5); }
html[data-theme="light"] .yld-table th         { color: var(--afs-text2); border-bottom-color: var(--afs-border); }
html[data-theme="light"] .yld-table tbody tr   { background: #ffffff; border-bottom-color: #eaf3eb; }
html[data-theme="light"] .yld-table tbody tr:hover { background: var(--afs-bg5); }
html[data-theme="light"] .yld-table td         { color: var(--afs-text2); }
html[data-theme="light"] .yld-table__empty     { color: var(--afs-muted); }
html[data-theme="light"] .yld-crop-name        { color: var(--afs-text); }
html[data-theme="light"] .yld-none             { color: var(--afs-muted2) !important; }
html[data-theme="light"] .yld-notes            { background: var(--afs-bg3); border-color: var(--afs-border); color: var(--afs-text2); }
html[data-theme="light"] .yld-chart-wrap       { background: #ffffff; border-color: var(--afs-border); }
html[data-theme="light"] .badge--gray          { background: rgba(100,100,100,0.08); color: #6b8570; border-color: var(--afs-border); }
</style>
