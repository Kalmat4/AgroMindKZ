<script setup>
// «Мои поля»: фермер отмечает поля на карте, система оценивает угрозу огня
// (термоточки NASA FIRMS + ветер и пожароопасность погоды) и шлёт тревоги в Telegram.
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import axios from 'axios'
import L from 'leaflet'
import { createWindLayer, windBadge } from './windLayer.js'

const props = defineProps({
    map: { type: Object, required: true },
    active: { type: Boolean, default: false },
})

const LEVELS = {
    critical: { label: 'Критическая угроза', color: '#ef4444', icon: '🚨' },
    high:     { label: 'Высокая угроза',     color: '#f97316', icon: '🔥' },
    watch:    { label: 'Наблюдение',         color: '#eab308', icon: '👁️' },
    safe:     { label: 'Угрозы нет',         color: '#22c55e', icon: '✅' },
}
const DANGER_COLORS = { low: '#22c55e', moderate: '#eab308', high: '#f97316', extreme: '#ef4444', unknown: '#888' }

const fields = ref([])
const crops = ref([])
const telegram = ref(false)
const loading = ref(false)
const loaded = ref(false)
const error = ref('')
const selectedId = ref(null)
const checking = ref(null)
const checkResult = ref('')
const addMode = ref(false)
const saving = ref(false)
const form = ref(emptyForm())
let linkPoll = null

function emptyForm() {
    return { name: '', lat: null, lon: null, area_ha: '', crop_code: 'wheat', price_per_ton: '' }
}

const layer = L.layerGroup()
const draftLayer = L.layerGroup()
const wind = createWindLayer(props.map)

const selected = computed(() => fields.value.find(f => f.id === selectedId.value) ?? null)

const totals = computed(() => {
    const atRisk = fields.value.filter(f => ['critical', 'high'].includes(f.assessment?.level))
    return {
        atRisk: atRisk.length,
        tenge: atRisk.reduce((s, f) => s + (f.assessment?.damage?.tenge ?? 0), 0),
    }
})

watch(() => props.active, on => {
    if (on) {
        layer.addTo(props.map)
        draftLayer.addTo(props.map)
        props.map.on('click', onMapClick)
        if (!loaded.value) load()
        else render()
    } else {
        layer.remove()
        draftLayer.remove()
        wind.hide()
        props.map.off('click', onMapClick)
        addMode.value = false
    }
}, { immediate: true })

onBeforeUnmount(() => {
    props.map.off('click', onMapClick)
    layer.remove()
    draftLayer.remove()
    wind.destroy()
    clearInterval(linkPoll)
})

async function load() {
    loading.value = true
    error.value = ''
    try {
        const { data } = await axios.get('/fields')
        fields.value = data.fields
        crops.value = data.crops
        telegram.value = data.telegram
        loaded.value = true
        render()
        fitToFields()
    } catch {
        error.value = 'Не удалось загрузить поля.'
    } finally {
        loading.value = false
    }
}

function fitToFields() {
    if (!fields.value.length) return
    const bounds = L.latLngBounds(fields.value.map(f => [f.lat, f.lon]))
    props.map.fitBounds(bounds.pad(0.5), { maxZoom: 9 })
}

function render() {
    layer.clearLayers()
    if (!selected.value) wind.hide()
    for (const f of fields.value) {
        const lvl = LEVELS[f.assessment?.level] ?? LEVELS.safe
        const isSel = f.id === selectedId.value

        if (isSel) {
            L.circle([f.lat, f.lon], {
                radius: (f.assessment?.radius_km ?? 50) * 1000,
                color: lvl.color, weight: 1, dashArray: '6 6', fillOpacity: 0.04,
            }).addTo(layer)

            for (const t of f.assessment?.threats ?? []) {
                const c = (LEVELS[t.level] ?? LEVELS.watch).color
                L.polyline([[t.lat, t.lon], [f.lat, f.lon]], {
                    color: c, weight: t.downwind ? 3 : 1.5, dashArray: t.downwind ? '10 8' : '4 6', opacity: 0.85,
                    className: t.downwind ? 'fp-fire-flow' : '',
                }).addTo(layer)
                L.circleMarker([t.lat, t.lon], { radius: 6, color: '#fff', weight: 1, fillColor: '#ff2200', fillOpacity: 0.95 })
                    .bindTooltip(`🔥 ${t.distance_km} км · ${t.downwind ? 'ветер на поле' : 'ветер не на поле'}${t.eta_hours ? ` · ~${t.eta_hours} ч` : ''}`)
                    .addTo(layer)
            }
            drawWindArrow(f)
        }

        L.circleMarker([f.lat, f.lon], {
            radius: isSel ? 12 : 9, color: '#fff', weight: 2, fillColor: lvl.color, fillOpacity: 1,
        })
            .bindTooltip(`${lvl.icon} ${f.name}`, { permanent: true, direction: 'right', offset: [10, 0], className: 'fp-tooltip' })
            .on('click', e => { L.DomEvent.stopPropagation(e); select(f.id) })
            .addTo(layer)
    }
}

// Поток ветра частицами в круге поля и компас со скоростью на самом поле
function drawWindArrow(f) {
    const w = f.assessment?.wind
    if (!w || w.deg === null || w.deg === undefined || w.speed < 0.3) {
        wind.hide()
        return
    }
    wind.show(f.lat, f.lon, f.assessment.radius_km ?? 50, w.speed, w.deg)
    L.marker([f.lat, f.lon], { icon: windBadge(w.speed, w.deg, w.from), interactive: false, keyboard: false }).addTo(layer)
}

function select(id) {
    selectedId.value = selectedId.value === id ? null : id
    checkResult.value = ''
    render()
    const f = selected.value
    if (f) props.map.setView([f.lat, f.lon], 9)
}

function startAdd() {
    addMode.value = true
    selectedId.value = null
    form.value = emptyForm()
    draftLayer.clearLayers()
    render()
}

function cancelAdd() {
    addMode.value = false
    form.value = emptyForm()
    draftLayer.clearLayers()
}

function onMapClick(e) {
    if (!addMode.value) return
    form.value.lat = Number(e.latlng.lat.toFixed(6))
    form.value.lon = Number(e.latlng.wrap().lng.toFixed(6))
    draftLayer.clearLayers()
    L.circleMarker([form.value.lat, form.value.lon], { radius: 10, color: '#fff', weight: 2, fillColor: '#38bdf8', fillOpacity: 1 }).addTo(draftLayer)
}

async function saveField() {
    const f = form.value
    if (!f.lat || !f.name.trim() || !(Number(f.area_ha) > 0)) {
        error.value = 'Укажите точку на карте, название и площадь.'
        return
    }
    saving.value = true
    error.value = ''
    try {
        const { data } = await axios.post('/fields', {
            name: f.name.trim(),
            lat: f.lat,
            lon: f.lon,
            area_ha: Number(f.area_ha),
            crop_code: f.crop_code || null,
            price_per_ton: f.price_per_ton ? Number(f.price_per_ton) : null,
        })
        fields.value.push(data)
        cancelAdd()
        select(data.id)
    } catch (e) {
        error.value = Object.values(e.response?.data?.errors ?? {})[0]?.[0] ?? 'Не удалось сохранить поле.'
    } finally {
        saving.value = false
    }
}

async function removeField(f) {
    if (!confirm(`Удалить поле «${f.name}»?`)) return
    await axios.delete(`/fields/${f.id}`)
    fields.value = fields.value.filter(x => x.id !== f.id)
    if (selectedId.value === f.id) selectedId.value = null
    render()
}

async function checkNow(f) {
    checking.value = f.id
    checkResult.value = ''
    try {
        const { data } = await axios.post(`/fields/${f.id}/check`)
        f.assessment = data.assessment
        render()
        checkResult.value = data.telegram_sent
            ? '📨 Сводка отправлена в Telegram'
            : (telegram.value ? 'Telegram не ответил, попробуйте ещё раз' : 'Подключите Telegram, чтобы получать тревоги')
    } catch {
        checkResult.value = 'Проверка не удалась, попробуйте позже'
    } finally {
        checking.value = null
    }
}

async function connectTelegram() {
    const win = window.open('about:blank', '_blank')
    try {
        const { data } = await axios.post('/telegram/link')
        if (win) win.location = data.url
        else window.location = data.url
    } catch {
        win?.close()
        error.value = 'Не удалось получить ссылку на бота.'
        return
    }
    // Ждём, пока пользователь нажмёт Start в боте
    clearInterval(linkPoll)
    let tries = 0
    linkPoll = setInterval(async () => {
        if (++tries > 60) return clearInterval(linkPoll)
        try {
            const { data } = await axios.get('/telegram/status')
            if (data.connected) {
                telegram.value = true
                clearInterval(linkPoll)
            }
        } catch { /* повторим */ }
    }, 3000)
}

function tenge(n) {
    return n ? Number(n).toLocaleString('ru-RU') + ' ₸' : '—'
}
</script>

<template>
    <div v-if="active" class="fp-panel">
        <div class="fp-header">
            <span class="fp-title">🌾 МОИ ПОЛЯ</span>
            <button class="fp-btn fp-btn--ghost" :disabled="loading" @click="load" title="Обновить">↻</button>
        </div>

        <!-- Итог по хозяйству -->
        <div v-if="fields.length" class="fp-section fp-totals">
            <div>
                <div class="fp-totals__num" :class="{ 'fp-red': totals.atRisk }">{{ totals.atRisk }} / {{ fields.length }}</div>
                <div class="fp-muted">полей под угрозой</div>
            </div>
            <div>
                <div class="fp-totals__num" :class="{ 'fp-red': totals.tenge }">{{ tenge(totals.tenge) }}</div>
                <div class="fp-muted">урожай под угрозой</div>
            </div>
        </div>

        <!-- Telegram -->
        <div class="fp-section">
            <div v-if="telegram" class="fp-ok">✅ Telegram подключён — тревоги приходят автоматически каждые 30 минут</div>
            <button v-else class="fp-btn fp-btn--tg" @click="connectTelegram">📨 Подключить Telegram для тревог</button>
        </div>

        <!-- Добавление поля -->
        <div class="fp-section">
            <button v-if="!addMode" class="fp-btn fp-btn--primary" @click="startAdd">+ Добавить поле</button>
            <div v-else class="fp-form">
                <div class="fp-hint">{{ form.lat ? `📍 ${form.lat}, ${form.lon}` : '👆 Нажмите на карте, где находится поле' }}</div>
                <input v-model="form.name" maxlength="100" placeholder="Название поля" />
                <input v-model="form.area_ha" type="number" min="0.1" step="0.1" placeholder="Площадь, га" />
                <select v-model="form.crop_code">
                    <option value="">Культура не указана</option>
                    <option v-for="c in crops" :key="c.code" :value="c.code">{{ c.name_ru }}</option>
                </select>
                <input v-model="form.price_per_ton" type="number" min="1" step="1" placeholder="Цена урожая, ₸ за тонну" />
                <div class="fp-row">
                    <button class="fp-btn fp-btn--primary" :disabled="saving" @click="saveField">{{ saving ? 'Сохранение…' : 'Сохранить' }}</button>
                    <button class="fp-btn fp-btn--ghost" @click="cancelAdd">Отмена</button>
                </div>
            </div>
            <div v-if="error" class="fp-error">{{ error }}</div>
        </div>

        <div v-if="loading" class="fp-section fp-muted">Проверяем спутниковые данные и погоду…</div>
        <div v-else-if="loaded && !fields.length && !addMode" class="fp-section fp-muted">
            Отметьте свои поля — система будет следить за огнём в радиусе 50 км, учитывать ветер и предупреждать в Telegram.
        </div>

        <!-- Список полей -->
        <div v-for="f in fields" :key="f.id" class="fp-field" :class="{ 'fp-field--sel': f.id === selectedId }">
            <div class="fp-field__head" @click="select(f.id)">
                <span class="fp-dot" :style="{ background: (LEVELS[f.assessment?.level] ?? LEVELS.safe).color }"></span>
                <div class="fp-field__name">
                    {{ f.name }}
                    <div class="fp-muted">{{ f.area_ha }} га · {{ f.assessment?.damage?.crop || 'культура не указана' }}</div>
                </div>
                <span class="fp-badge" :style="{ color: (LEVELS[f.assessment?.level] ?? LEVELS.safe).color }">
                    {{ (LEVELS[f.assessment?.level] ?? LEVELS.safe).label }}
                </span>
            </div>

            <div v-if="f.id === selectedId && f.assessment" class="fp-field__body">
                <div v-if="!f.assessment.firms_ok" class="fp-warn">⚠️ Спутник NASA FIRMS сейчас не ответил — оценка неполная</div>

                <template v-if="f.assessment.threats.length">
                    <div class="fp-line">
                        🔥 Очагов в радиусе {{ f.assessment.radius_km }} км: <b>{{ f.assessment.hotspots }}</b>,
                        ближайший <b>{{ f.assessment.nearest }} км</b>
                    </div>
                    <div v-for="(t, i) in f.assessment.threats.slice(0, 3)" :key="i" class="fp-threat" :style="{ borderColor: LEVELS[t.level].color }">
                        <b>{{ t.distance_km }} км</b> к {{ t.direction }}
                        <span v-if="t.downwind" class="fp-red"> · ветер на поле<template v-if="t.eta_hours"> · подход ~{{ t.eta_hours }} ч</template></span>
                        <span v-else class="fp-muted"> · ветер не на поле</span>
                        <div class="fp-muted">{{ t.detected_at }} · FRP {{ t.frp }} МВт</div>
                    </div>
                </template>
                <div v-else class="fp-line">✅ Термоточек в радиусе {{ f.assessment.radius_km }} км за 48 ч нет</div>

                <div class="fp-line">
                    💨 Ветер сейчас {{ f.assessment.wind.speed }} м/с<template v-if="f.assessment.wind.from">, с {{ f.assessment.wind.from }}</template>
                </div>
                <div class="fp-line">
                    🌡️ Пожароопасность погоды:
                    <b :style="{ color: DANGER_COLORS[f.assessment.fire_danger.level] }">{{ f.assessment.fire_danger.label }}</b>
                    <span v-if="f.assessment.fire_danger.index !== null" class="fp-muted"> (HDW {{ f.assessment.fire_danger.index }})</span>
                </div>

                <div class="fp-money">
                    <template v-if="f.assessment.damage.tenge">
                        💰 Урожай поля: ~{{ f.assessment.damage.tons.toLocaleString('ru-RU') }} т · <b>{{ tenge(f.assessment.damage.tenge) }}</b>
                        <div class="fp-muted">{{ f.assessment.damage.yield_c_ha }} ц/га (средняя по РК, {{ f.assessment.damage.yield_year }}) × {{ tenge(f.assessment.damage.price_per_ton) }}/т</div>
                    </template>
                    <span v-else class="fp-muted">Укажите культуру и цену за тонну, чтобы видеть сумму под угрозой</span>
                </div>

                <div class="fp-row">
                    <button class="fp-btn fp-btn--primary" :disabled="checking === f.id" @click="checkNow(f)">
                        {{ checking === f.id ? 'Проверяем…' : '📡 Проверить и отправить в Telegram' }}
                    </button>
                    <button class="fp-btn fp-btn--ghost" @click="removeField(f)" title="Удалить поле">🗑</button>
                </div>
                <div v-if="checkResult" class="fp-muted">{{ checkResult }}</div>
            </div>
        </div>

        <div class="fp-section fp-legend fp-muted">
            Термоточки — NASA FIRMS (VIIRS), 48 ч. Ветер и пожароопасность — прогноз OpenWeatherMap, индекс Hot-Dry-Windy.
            Время подхода огня — грубая оценка (≈10 % скорости ветра). <a href="/data#fields" target="_blank">Как считаем</a>
        </div>
    </div>
</template>

<style scoped>
.fp-panel {
    position: absolute; top: 12px; right: 12px; width: 330px; max-height: calc(100% - 24px);
    background: #1a1a1a; border: 1px solid #2d2d2d; border-top: 2px solid #4ade80; border-radius: 12px;
    overflow-y: auto; z-index: 20; box-shadow: 0 4px 24px rgba(0, 0, 0, 0.5); color: #e5e5e5; font-size: 13px;
}
.fp-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-bottom: 1px solid #2d2d2d; }
.fp-title { font-size: 11px; font-weight: 700; color: #4ade80; letter-spacing: 0.6px; }
.fp-section { padding: 10px 14px; border-bottom: 1px solid #222; }
.fp-totals { display: flex; gap: 16px; }
.fp-totals__num { font-size: 18px; font-weight: 800; }
.fp-muted { color: #888; font-size: 11px; }
.fp-red { color: #ef4444; }
.fp-ok { color: #4ade80; font-size: 12px; }
.fp-warn { color: #eab308; font-size: 12px; margin-bottom: 6px; }
.fp-error { color: #ef4444; font-size: 12px; margin-top: 6px; }
.fp-hint { font-size: 12px; color: #38bdf8; }
.fp-form { display: flex; flex-direction: column; gap: 6px; }
.fp-form input, .fp-form select {
    background: #111; color: #e5e5e5; border: 1px solid #333; border-radius: 6px; padding: 7px 8px; font-size: 13px;
}
.fp-row { display: flex; gap: 6px; margin-top: 6px; }
.fp-btn { border: none; border-radius: 8px; padding: 8px 12px; font-size: 12px; font-weight: 700; cursor: pointer; }
.fp-btn:disabled { opacity: 0.6; cursor: default; }
.fp-btn--primary { background: #4ade80; color: #000; flex: 1; }
.fp-btn--tg { background: #229ed9; color: #fff; width: 100%; }
.fp-btn--ghost { background: transparent; color: #aaa; border: 1px solid #333; }
.fp-field { border-bottom: 1px solid #222; }
.fp-field--sel { background: #202420; }
.fp-field__head { display: flex; align-items: center; gap: 8px; padding: 10px 14px; cursor: pointer; }
.fp-field__name { flex: 1; font-weight: 700; }
.fp-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.fp-badge { font-size: 11px; font-weight: 700; white-space: nowrap; }
.fp-field__body { padding: 0 14px 12px; display: flex; flex-direction: column; gap: 6px; }
.fp-line { font-size: 12px; }
.fp-threat { border-left: 3px solid; padding: 2px 8px; font-size: 12px; }
.fp-money { background: #111; border-radius: 8px; padding: 8px; font-size: 12px; }
.fp-legend { line-height: 1.5; border-bottom: none; }
.fp-legend a { color: #4ade80; }

:global(html[data-theme="light"]) .fp-panel { background: #fff; color: #1a2e1d; border-color: #c4ddc8; border-top-color: #00a550; box-shadow: 0 4px 24px rgba(0,0,0,0.12); }
:global(html[data-theme="light"]) .fp-header,
:global(html[data-theme="light"]) .fp-section,
:global(html[data-theme="light"]) .fp-field { border-color: #eaf3eb; }
:global(html[data-theme="light"]) .fp-title { color: #007a3a; }
:global(html[data-theme="light"]) .fp-field--sel { background: #f0f8f1; }
:global(html[data-theme="light"]) .fp-form input,
:global(html[data-theme="light"]) .fp-form select,
:global(html[data-theme="light"]) .fp-money { background: #f4f7f4; color: #1a2e1d; border-color: #c4ddc8; }
:global(html[data-theme="light"]) .fp-muted { color: #6b8570; }
:global(.fp-tooltip) { font-weight: 700; }

:global(.afs-wind-badge) { position: relative; pointer-events: none; }
:global(.afs-wind-badge__arrow) {
    position: absolute; left: -20px; top: 16px; width: 40px; height: 40px;
    transform: rotate(var(--wind-rot)); filter: drop-shadow(0 2px 4px rgba(0,0,0,0.45));
}
:global(.afs-wind-badge__arrow svg) { animation: afs-wind-sway 2.4s ease-in-out infinite; }
:global(.afs-wind-badge__arrow circle) { fill: rgba(15, 23, 42, 0.78); stroke: #7dd3fc; stroke-width: 1.5; }
:global(.afs-wind-badge__arrow path) { fill: #7dd3fc; }
:global(.afs-wind-badge__text) {
    position: absolute; left: -50px; top: 58px; width: 100px; text-align: center;
    font: 700 11px/1.2 -apple-system, 'Segoe UI', Roboto, sans-serif; color: #0c4a6e;
    background: rgba(224, 242, 254, 0.92); border-radius: 10px; padding: 2px 6px; white-space: nowrap;
}
:global(.fp-fire-flow) { animation: afs-fire-flow 1s linear infinite; }
@keyframes afs-wind-sway {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-3px); }
}
@keyframes afs-fire-flow { to { stroke-dashoffset: -18; } }
@media (prefers-reduced-motion: reduce) {
    :global(.afs-wind-badge__arrow svg), :global(.fp-fire-flow) { animation: none; }
}
</style>
