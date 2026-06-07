<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
    eco_stats:   { type: Object, default: () => ({}) },
    regions_eco: { type: Array,  default: () => [] },
})

// ── CO2 Calculator ────────────────────────────────────────────────────────────
const calcRegion = ref('')
const calcHotspots = ref(null)

const calcCo2 = computed(() => {
    const n = Number(calcHotspots.value)
    if (!n || n <= 0) return null
    return (n * 0.45).toFixed(2)
})

// ── Badge helpers ─────────────────────────────────────────────────────────────
function riskClass(degradation_index) {
    if (degradation_index > 4) return 'badge badge--red'
    if (degradation_index > 3) return 'badge badge--orange'
    return 'badge badge--yellow'
}

function riskLabel(degradation_index) {
    if (degradation_index > 4) return t('ecology.risk_critical')
    if (degradation_index > 3) return t('ecology.risk_high')
    return t('ecology.risk_moderate')
}

function waterClass(stress) {
    if (stress === 'критический') return 'badge badge--red'
    if (stress === 'высокий')     return 'badge badge--orange'
    if (stress === 'средний')     return 'badge badge--yellow'
    return 'badge badge--green'
}
</script>

<template>
    <AppLayout>
        <Head title="Экология — AgroMind KZ" />

        <div class="eco-bg">
        <div class="eco-page">

            <div class="eco-page-title">Экологический мониторинг</div>

            <!-- Section 1: Stat cards -->
            <div class="eco-cards">
                <div class="eco-card">
                    <div class="eco-card__label">Деградировано земель</div>
                    <div class="eco-card__value">{{ eco_stats.degraded_area_mln_ha }} млн га</div>
                </div>
                <div class="eco-card eco-card--orange">
                    <div class="eco-card__label">Регионов с водным стрессом</div>
                    <div class="eco-card__value eco-card__value--orange">{{ eco_stats.water_stress_regions }}</div>
                </div>
                <div class="eco-card eco-card--red">
                    <div class="eco-card__label">CO₂ от пожаров (этот год)</div>
                    <div class="eco-card__value eco-card__value--red">{{ eco_stats.co2_from_fires_kton }} Кт</div>
                </div>
                <div class="eco-card eco-card--red">
                    <div class="eco-card__label">Регионов с высоким риском</div>
                    <div class="eco-card__value eco-card__value--red">
                        <span class="badge badge--red">{{ eco_stats.soil_risk_high }}</span>
                    </div>
                </div>
            </div>

            <!-- Section 2: Region table -->
            <div class="eco-section">
                <div class="eco-section__title">Экологический рейтинг регионов</div>
                <div class="eco-table-wrap">
                    <table class="eco-table">
                        <thead>
                            <tr>
                                <th>Регион</th>
                                <th>Индекс деградации</th>
                                <th>Водный стресс</th>
                                <th>CO₂ от пожаров (Кт)</th>
                                <th>Риск</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in regions_eco" :key="i">
                                <td class="eco-region-name">{{ row.region }}</td>
                                <td>
                                    <span :class="row.degradation_index > 3.5 ? 'eco-val--high' : 'eco-val--normal'">
                                        {{ row.degradation_index.toFixed(1) }}
                                    </span>
                                </td>
                                <td><span :class="waterClass(row.water_stress)">{{ row.water_stress }}</span></td>
                                <td>{{ row.co2_kton }}</td>
                                <td><span :class="riskClass(row.degradation_index)">{{ riskLabel(row.degradation_index) }}</span></td>
                            </tr>
                            <tr v-if="!regions_eco.length">
                                <td colspan="5" class="eco-table__empty">Нет данных</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Section 3: CO2 calculator -->
            <div class="eco-section">
                <div class="eco-section__title">CO₂ калькулятор</div>
                <div class="eco-calc">
                    <div class="eco-calc__inputs">
                        <div class="eco-calc__field">
                            <label class="eco-calc__label">Регион</label>
                            <select v-model="calcRegion" class="eco-calc__select">
                                <option value="">— выберите регион —</option>
                                <option v-for="row in regions_eco" :key="row.region" :value="row.region">
                                    {{ row.region }}
                                </option>
                            </select>
                        </div>
                        <div class="eco-calc__field">
                            <label class="eco-calc__label">Количество очагов пожара</label>
                            <input
                                v-model.number="calcHotspots"
                                type="number"
                                min="0"
                                placeholder="Введите число очагов"
                                class="eco-calc__input"
                            />
                        </div>
                    </div>

                    <div class="eco-calc__result" :class="{ 'eco-calc__result--active': calcCo2 !== null }">
                        <div class="eco-calc__result-label">Расчётный выброс CO₂</div>
                        <div class="eco-calc__result-value">
                            <span v-if="calcCo2 !== null">{{ calcCo2 }} тонн</span>
                            <span v-else class="eco-calc__result-placeholder">—</span>
                        </div>
                        <div class="eco-calc__formula">
                            Формула: FRP × коэффициент биомассы степи КЗ (0.45)
                        </div>
                    </div>
                </div>
            </div>

        </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* ── Dark green background ────────────────────────────────────────────────── */
.eco-bg {
    background: #0d1208;
    min-height: calc(100vh - 64px);
}

.eco-page {
    padding: 28px 32px;
    max-width: 1400px;
    margin: 0 auto;
}

.eco-page-title {
    font-size: 22px;
    font-weight: 700;
    color: #e0d6cc;
    margin-bottom: 24px;
    letter-spacing: 0.3px;
    border-left: 3px solid #00c853;
    padding-left: 12px;
}

/* ── Stat cards ───────────────────────────────────────────────────────────── */
.eco-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 32px;
}

.eco-card {
    background: #111c14;
    border: 1px solid rgba(0, 180, 80, 0.2);
    border-radius: 12px;
    padding: 20px 22px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.eco-card--red {
    border-color: rgba(0, 230, 118, 0.55);
    background: #0a1f0e;
    box-shadow: 0 0 20px rgba(0, 230, 118, 0.12), 0 0 40px rgba(0, 230, 118, 0.05);
}
.eco-card--orange {
    border-color: rgba(0, 230, 118, 0.55);
    background: #0a1f0e;
    box-shadow: 0 0 20px rgba(0, 230, 118, 0.12), 0 0 40px rgba(0, 230, 118, 0.05);
}

.eco-card__label {
    font-size: 12px;
    font-weight: 600;
    color: #6aaa80;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.eco-card__value {
    font-size: 24px;
    font-weight: 700;
    color: #00e676;
}

.eco-card__value--red {
    color: #00e676;
    text-shadow: 0 0 14px rgba(0, 230, 118, 0.6);
}
.eco-card__value--orange {
    color: #00e676;
    text-shadow: 0 0 14px rgba(0, 230, 118, 0.6);
}

/* ── Badges ───────────────────────────────────────────────────────────────── */
.badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.badge--red    { background: rgba(220, 38, 38, 0.2);  color: #f87171; border: 1px solid rgba(220, 38, 38, 0.4); }
.badge--orange { background: rgba(234, 88, 12, 0.2);  color: #fb923c; border: 1px solid rgba(234, 88, 12, 0.4); }
.badge--yellow { background: rgba(202, 138, 4, 0.2);  color: #facc15; border: 1px solid rgba(202, 138, 4, 0.4); }
.badge--green  { background: rgba(0, 180, 80, 0.15);  color: #4ade80; border: 1px solid rgba(0, 180, 80, 0.35); }

/* ── Section ──────────────────────────────────────────────────────────────── */
.eco-section { margin-bottom: 36px; }

.eco-section__title {
    font-size: 16px;
    font-weight: 700;
    color: #a0d6b0;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 2px solid #0b2014;
}

/* ── Table ────────────────────────────────────────────────────────────────── */
.eco-table-wrap {
    overflow-x: auto;
    border-radius: 10px;
    border: 1px solid rgba(0, 180, 80, 0.18);
}

.eco-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.eco-table thead tr { background: #0b1a10; }

.eco-table th {
    padding: 11px 14px;
    text-align: left;
    color: #6aaa80;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    white-space: nowrap;
    border-bottom: 1px solid #1a3020;
}

.eco-table tbody tr {
    background: #0f1610;
    border-bottom: 1px solid #1a2618;
    transition: background 0.12s;
}

.eco-table tbody tr:hover { background: #1e2a20; }

.eco-table td {
    padding: 10px 14px;
    color: #c8bfb5;
    vertical-align: middle;
}

.eco-table__empty {
    text-align: center;
    padding: 28px;
    color: #555;
    font-style: italic;
}

.eco-region-name { color: #a0d6b0; font-weight: 600; }
.eco-val--high   { color: #f87171; font-weight: 600; }
.eco-val--normal { color: #c8bfb5; }

/* ── CO2 Calculator ───────────────────────────────────────────────────────── */
.eco-calc {
    display: flex;
    gap: 32px;
    align-items: flex-start;
    background: #0f1610;
    border: 1px solid rgba(0, 180, 80, 0.18);
    border-radius: 12px;
    padding: 24px 28px;
}

.eco-calc__inputs {
    display: flex;
    flex-direction: column;
    gap: 16px;
    flex: 1;
    min-width: 0;
}

.eco-calc__field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.eco-calc__label {
    font-size: 11px;
    font-weight: 700;
    color: #6aaa80;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.eco-calc__select,
.eco-calc__input {
    background: #111c14;
    border: 1px solid #1a3020;
    border-radius: 8px;
    color: #e0d6cc;
    font-size: 13px;
    padding: 9px 12px;
    outline: none;
    transition: border-color 0.15s;
    font-family: inherit;
    width: 100%;
    max-width: 360px;
}

.eco-calc__select:focus,
.eco-calc__input:focus {
    border-color: #00e676;
    box-shadow: 0 0 0 3px rgba(0, 180, 80, 0.12);
}

.eco-calc__select option { background: #111c14; }

.eco-calc__result {
    flex-shrink: 0;
    min-width: 240px;
    background: #0b1a10;
    border: 1px solid #1a3020;
    border-radius: 10px;
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    transition: border-color 0.2s;
}

.eco-calc__result--active {
    border-color: rgba(0, 230, 118, 0.5);
    box-shadow: 0 0 16px rgba(0, 180, 80, 0.08);
}

.eco-calc__result-label {
    font-size: 11px;
    font-weight: 700;
    color: #6aaa80;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.eco-calc__result-value {
    font-size: 28px;
    font-weight: 700;
    color: #00e676;
    text-shadow: 0 0 16px rgba(0, 230, 118, 0.4);
}

.eco-calc__result-placeholder {
    color: #2a4030;
    font-size: 24px;
}

.eco-calc__formula {
    font-size: 11px;
    color: #4a7060;
    line-height: 1.5;
    border-top: 1px solid #1a3020;
    padding-top: 8px;
    margin-top: 4px;
}
</style>

<style>
html[data-theme="light"] .eco-bg           { background: var(--afs-bg) !important; }
html[data-theme="light"] .eco-page-title   { color: var(--afs-text); }
html[data-theme="light"] .eco-card         { background: #ffffff; border-color: var(--afs-border); box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
html[data-theme="light"] .eco-card__label  { color: var(--afs-muted); }
html[data-theme="light"] .eco-card__value  { color: var(--afs-text); }
html[data-theme="light"] .eco-section__title   { color: var(--afs-text); border-bottom-color: var(--afs-border); }
html[data-theme="light"] .eco-table-wrap       { border-color: var(--afs-border); }
html[data-theme="light"] .eco-table            { background: #ffffff; }
html[data-theme="light"] .eco-table thead tr   { background: var(--afs-bg5); }
html[data-theme="light"] .eco-table th         { color: var(--afs-text2); border-bottom-color: var(--afs-border); }
html[data-theme="light"] .eco-table tbody tr   { background: #ffffff; border-bottom-color: #eaf3eb; }
html[data-theme="light"] .eco-table tbody tr:hover { background: var(--afs-bg5); }
html[data-theme="light"] .eco-table td         { color: var(--afs-text2); }
html[data-theme="light"] .eco-table__empty     { color: var(--afs-muted); }
html[data-theme="light"] .eco-region-name      { color: var(--afs-accent2); }
html[data-theme="light"] .eco-val--normal      { color: var(--afs-text2); }
html[data-theme="light"] .eco-calc             { background: #ffffff; border-color: var(--afs-border); }
html[data-theme="light"] .eco-calc__label      { color: var(--afs-text2); }
html[data-theme="light"] .eco-calc__select,
html[data-theme="light"] .eco-calc__input      { background: var(--afs-bg3); color: var(--afs-text); border-color: var(--afs-border); }
html[data-theme="light"] .eco-calc__select option { background: #ffffff; color: #1a2e1d; }
html[data-theme="light"] .eco-calc__result     { background: var(--afs-bg3); border-color: var(--afs-border); color: var(--afs-muted); }
html[data-theme="light"] .eco-calc__result--active { background: var(--afs-bg5); border-color: var(--afs-accent2); color: var(--afs-text); }
</style>
