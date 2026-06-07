<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
    subsidy_flags: { type: Array, default: () => [] },
    anomalies:     { type: Array, default: () => [] },
    stats:         { type: Object, default: () => ({}) },
})

function formatBillion(val) {
    if (!val) return '0 млрд ₸'
    return (val / 1_000_000_000).toFixed(1) + ' млрд ₸'
}

function formatNum(val) {
    if (val == null) return '—'
    return Number(val).toLocaleString('ru-RU')
}

function rowClass(deviation) {
    if (deviation > 10)  return 'subs-row--red'
    if (deviation > 2)   return 'subs-row--yellow'
    return ''
}

function severityClass(severity) {
    if (severity === 'critical') return 'badge badge--red'
    if (severity === 'medium')   return 'badge badge--orange'
    return 'badge badge--yellow'
}

function anomalyLabel(type) {
    if (type === 'overreport')    return t('subsidies.overreport')
    if (type === 'subsidy_fraud') return t('subsidies.fraud_type')
    return type
}
</script>

<template>
    <AppLayout>
        <Head title="Субсидии — AgroMind KZ" />

        <div class="subs-bg">
        <div class="subs-page">

            <div class="subs-page-title">{{ t('subsidies.title') }}</div>

            <!-- Section 1: Stats cards -->
            <div class="subs-cards">
                <div class="subs-card">
                    <div class="subs-card__label">{{ t('subsidies.total') }}</div>
                    <div class="subs-card__value">{{ formatBillion(stats.total_subsidy_kzt) }}</div>
                </div>
                <div class="subs-card subs-card--red">
                    <div class="subs-card__label">{{ t('subsidies.losses') }}</div>
                    <div class="subs-card__value subs-card__value--red">{{ formatBillion(stats.total_potential_loss_kzt) }}</div>
                </div>
                <div class="subs-card">
                    <div class="subs-card__label">{{ t('subsidies.critical') }}</div>
                    <div class="subs-card__value">
                        <span class="badge badge--red">{{ stats.critical_count ?? 0 }}</span>
                    </div>
                </div>
                <div class="subs-card">
                    <div class="subs-card__label">{{ t('subsidies.fraud') }}</div>
                    <div class="subs-card__value">
                        <span class="badge badge--orange">{{ stats.fraud_count ?? 0 }}</span>
                    </div>
                </div>
            </div>

            <!-- Section 2: Subsidy flags table -->
            <div class="subs-section">
                <div class="subs-section__title">{{ t('subsidies.flags_title') }}</div>
                <div class="subs-table-wrap">
                    <table class="subs-table">
                        <thead>
                            <tr>
                                <th>{{ t('subsidies.col_region') }}</th>
                                <th>{{ t('subsidies.col_year') }}</th>
                                <th>{{ t('subsidies.col_declared') }}</th>
                                <th>{{ t('subsidies.col_satellite') }}</th>
                                <th>{{ t('subsidies.col_gap') }}</th>
                                <th>{{ t('subsidies.col_deviation') }}</th>
                                <th>{{ t('subsidies.col_amount') }}</th>
                                <th>{{ t('subsidies.col_loss') }}</th>
                                <th>{{ t('subsidies.col_notes') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, i) in subsidy_flags"
                                :key="i"
                                :class="rowClass(row.deviation_pct)"
                            >
                                <td>{{ row.region_name }}</td>
                                <td>{{ row.harvest_year }}</td>
                                <td>{{ formatNum(row.declared_area_ha) }}</td>
                                <td>{{ formatNum(row.satellite_area_ha) }}</td>
                                <td>{{ formatNum(row.area_gap_ha) }}</td>
                                <td>{{ row.deviation_pct != null ? Number(row.deviation_pct).toFixed(1) + ' %' : '—' }}</td>
                                <td>{{ formatNum(row.subsidy_amount_kzt) }} ₸</td>
                                <td>{{ formatNum(row.potential_loss_kzt) }} ₸</td>
                                <td>{{ row.notes ?? '—' }}</td>
                            </tr>
                            <tr v-if="!subsidy_flags.length">
                                <td colspan="9" class="subs-table__empty">{{ t('subsidies.no_data') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Section 3: Anomalies table -->
            <div class="subs-section">
                <div class="subs-section__title">{{ t('subsidies.anomalies_title') }}</div>
                <div class="subs-table-wrap">
                    <table class="subs-table">
                        <thead>
                            <tr>
                                <th>{{ t('subsidies.col_year') }}</th>
                                <th>{{ t('subsidies.col_region') }}</th>
                                <th>{{ t('subsidies.col_crop') }}</th>
                                <th>{{ t('subsidies.col_declared_yield') }}</th>
                                <th>{{ t('subsidies.col_satellite_yield') }}</th>
                                <th>{{ t('subsidies.col_deviation') }}</th>
                                <th>{{ t('subsidies.col_type') }}</th>
                                <th>{{ t('subsidies.col_severity') }}</th>
                                <th>{{ t('subsidies.col_desc') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in anomalies" :key="i">
                                <td>{{ row.harvest_year }}</td>
                                <td>{{ row.region_name }}</td>
                                <td>{{ row.crop_name }}</td>
                                <td>{{ formatNum(row.declared_yield) }}</td>
                                <td>{{ formatNum(row.satellite_yield) }}</td>
                                <td>{{ row.deviation_pct != null ? Number(row.deviation_pct).toFixed(1) + ' %' : '—' }}</td>
                                <td>{{ anomalyLabel(row.anomaly_type) }}</td>
                                <td><span :class="severityClass(row.severity)">{{ row.severity }}</span></td>
                                <td>{{ row.description ?? '—' }}</td>
                            </tr>
                            <tr v-if="!anomalies.length">
                                <td colspan="9" class="subs-table__empty">{{ t('subsidies.no_anomalies') }}</td>
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
/* ── Dark green background ────────────────────────────────────────────────── */
.subs-bg {
    background: #0d1208;
    min-height: calc(100vh - 64px);
}

/* ── Page wrapper ─────────────────────────────────────────────────────────── */
.subs-page {
    padding: 28px 32px;
    max-width: 1400px;
    margin: 0 auto;
}

.subs-page-title {
    font-size: 22px;
    font-weight: 700;
    color: #e0d6cc;
    margin-bottom: 24px;
    letter-spacing: 0.3px;
    border-left: 3px solid #00c853;
    padding-left: 12px;
}

/* ── Stat cards ───────────────────────────────────────────────────────────── */
.subs-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 32px;
}
.subs-card {
    background: #111c14;
    border: 1px solid rgba(0, 180, 80, 0.2);
    border-radius: 12px;
    padding: 20px 22px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.subs-card--red {
    border-color: rgba(0, 230, 118, 0.55);
    background: #0a1f0e;
    box-shadow: 0 0 20px rgba(0, 230, 118, 0.12), 0 0 40px rgba(0, 230, 118, 0.05);
}
.subs-card__label { font-size: 12px; font-weight: 600; color: #6aaa80; text-transform: uppercase; letter-spacing: 0.5px; }
.subs-card__value { font-size: 24px; font-weight: 700; color: #00e676; }
.subs-card__value--red {
    color: #00e676;
    text-shadow: 0 0 14px rgba(0, 230, 118, 0.6);
}

/* ── Badges ───────────────────────────────────────────────────────────────── */
.badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
}
.badge--red    { background: rgba(220,38,38,0.2);  color: #f87171; border: 1px solid rgba(220,38,38,0.4); }
.badge--orange { background: rgba(234,88,12,0.2);  color: #fb923c; border: 1px solid rgba(234,88,12,0.4); }
.badge--yellow { background: rgba(202,138,4,0.2);  color: #facc15; border: 1px solid rgba(202,138,4,0.4); }

/* ── Section ──────────────────────────────────────────────────────────────── */
.subs-section { margin-bottom: 36px; }
.subs-section__title {
    font-size: 16px;
    font-weight: 700;
    color: #a0d6b0;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 2px solid #0b2014;
}

/* ── Table ────────────────────────────────────────────────────────────────── */
.subs-table-wrap {
    overflow-x: auto;
    border-radius: 10px;
    border: 1px solid rgba(0, 180, 80, 0.18);
}
.subs-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.subs-table thead tr {
    background: #0b1a10;
}
.subs-table th {
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
.subs-table tbody tr {
    background: #0f1610;
    border-bottom: 1px solid #1a2618;
    transition: background 0.12s;
}
.subs-table tbody tr:hover { background: #1e2a20; }
.subs-table td {
    padding: 10px 14px;
    color: #c8bfb5;
    vertical-align: middle;
}
.subs-row--red  { background: rgba(220, 38, 38, 0.12) !important; }
.subs-row--red:hover { background: rgba(220, 38, 38, 0.18) !important; }
.subs-row--yellow  { background: rgba(202, 138, 4, 0.1) !important; }
.subs-row--yellow:hover { background: rgba(202, 138, 4, 0.16) !important; }
.subs-table__empty {
    text-align: center;
    padding: 28px;
    color: #555;
    font-style: italic;
}
</style>

<style>
html[data-theme="light"] .subs-bg          { background: var(--afs-bg) !important; }
html[data-theme="light"] .subs-page-title  { color: var(--afs-text); }
html[data-theme="light"] .subs-card        { background: #ffffff; border-color: var(--afs-border); box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
html[data-theme="light"] .subs-card__label { color: var(--afs-muted); }
html[data-theme="light"] .subs-card__value { color: var(--afs-accent2); }
html[data-theme="light"] .subs-section__title  { color: var(--afs-text); border-bottom-color: var(--afs-border); }
html[data-theme="light"] .subs-table-wrap      { border-color: var(--afs-border); }
html[data-theme="light"] .subs-table           { background: #ffffff; }
html[data-theme="light"] .subs-table thead tr  { background: var(--afs-bg5); }
html[data-theme="light"] .subs-table th        { color: var(--afs-text2); border-bottom-color: var(--afs-border); }
html[data-theme="light"] .subs-table tbody tr  { background: #ffffff; border-bottom-color: #eaf3eb; }
html[data-theme="light"] .subs-table tbody tr:hover { background: var(--afs-bg5); }
html[data-theme="light"] .subs-table td        { color: var(--afs-text2); }
html[data-theme="light"] .subs-table__empty    { color: var(--afs-muted); }
</style>
