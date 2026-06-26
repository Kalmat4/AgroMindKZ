<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import axios from 'axios'
import MarkdownIt from 'markdown-it'
import { useI18n } from 'vue-i18n'

const md = new MarkdownIt({ breaks: true, linkify: true })
const { t } = useI18n()
import 'leaflet/dist/leaflet.css'
import L from 'leaflet'

// Fix Leaflet default marker icons broken by Vite bundling
delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
    iconRetinaUrl: new URL('leaflet/dist/images/marker-icon-2x.png', import.meta.url).href,
    iconUrl: new URL('leaflet/dist/images/marker-icon.png', import.meta.url).href,
    shadowUrl: new URL('leaflet/dist/images/marker-shadow.png', import.meta.url).href,
})

const props = defineProps({
    currentZone: { type: Object, default: null },
    sessionId: { type: String, default: '' },
})

// ── Central Asia data ─────────────────────────────────────────────────────────
const COUNTRIES = [
    { name: 'Казахстан',    flag: '🇰🇿', west: 49.5, south: 40.5, east: 87.5, north: 55.4 },
    { name: 'Узбекистан',   flag: '🇺🇿', west: 55.9, south: 37.1, east: 73.2, north: 45.6 },
    { name: 'Туркменистан', flag: '🇹🇲', west: 52.4, south: 35.1, east: 66.6, north: 42.8 },
    { name: 'Кыргызстан',   flag: '🇰🇬', west: 69.2, south: 39.2, east: 80.3, north: 43.3 },
    { name: 'Таджикистан',  flag: '🇹🇯', west: 67.3, south: 36.7, east: 75.2, north: 41.1 },
]

const REGIONS = {
    'Казахстан': [
        { name: 'Жамбылская',            west: 67.5, south: 42.5, east: 75.5, north: 46.5 },
        { name: 'Кызылординская',        west: 57.0, south: 42.5, east: 67.5, north: 47.5 },
        { name: 'Костанайская',          west: 60.0, south: 51.0, east: 67.5, north: 55.4 },
        { name: 'Западно-Казахстанская', west: 49.5, south: 49.5, east: 55.5, north: 52.5 },
        { name: 'Мангыстауская',         west: 49.0, south: 41.5, east: 57.0, north: 46.5 },
        { name: 'Атырауская',            west: 49.5, south: 46.5, east: 57.0, north: 49.5 },
        { name: 'Туркестанская',         west: 57.0, south: 40.5, east: 75.5, north: 42.5 },
        { name: 'Алматинская',           west: 75.5, south: 42.5, east: 84.0, north: 46.5 },
        { name: 'Акмолинская',           west: 67.5, south: 50.5, east: 73.5, north: 53.5 },
        { name: 'Павлодарская',          west: 73.5, south: 50.5, east: 79.5, north: 55.4 },
        { name: 'Северо-Казахстанская',  west: 67.5, south: 53.5, east: 73.5, north: 55.4 },
        { name: 'Восточно-Казахстанская',west: 79.5, south: 46.5, east: 87.5, north: 51.5 },
        { name: 'Актюбинская',           west: 55.5, south: 47.5, east: 67.5, north: 51.0 },
        { name: 'Карагандинская',        west: 71.5, south: 44.5, east: 79.5, north: 50.5 },
        { name: 'Улытауская',            west: 61.0, south: 46.5, east: 71.5, north: 50.5 },
        { name: 'г. Алматы',             west: 76.6, south: 43.0, east: 77.3, north: 43.5 },
        { name: 'г. Астана',             west: 71.2, south: 50.9, east: 71.8, north: 51.3 },
    ],
    'Кыргызстан': [
        { name: 'Чуйская',          west: 72.0, south: 42.2, east: 77.2, north: 43.3 },
        { name: 'Таласская',        west: 70.5, south: 42.0, east: 73.3, north: 42.8 },
        { name: 'Иссык-Кульская',   west: 75.5, south: 41.6, east: 80.3, north: 43.2 },
        { name: 'Нарынская',        west: 73.3, south: 40.7, east: 77.5, north: 42.2 },
        { name: 'Джалал-Абадская',  west: 71.5, south: 40.3, east: 74.5, north: 41.9 },
        { name: 'Ошская',           west: 71.7, south: 39.5, east: 74.2, north: 40.7 },
        { name: 'Баткенская',       west: 69.2, south: 39.3, east: 72.0, north: 40.5 },
    ],
    'Узбекистан': [
        { name: 'Каракалпакстан',    west: 55.9, south: 41.0, east: 61.5, north: 45.6 },
        { name: 'Хорезмская',        west: 59.5, south: 40.7, east: 62.0, north: 42.0 },
        { name: 'Навоийская',        west: 61.5, south: 39.7, east: 66.5, north: 43.5 },
        { name: 'Бухарская',         west: 61.8, south: 37.8, east: 65.5, north: 41.0 },
        { name: 'Сырдарьинская',     west: 67.7, south: 40.3, east: 69.3, north: 41.2 },
        { name: 'Джизакская',        west: 65.4, south: 39.8, east: 68.5, north: 41.1 },
        { name: 'Самаркандская',     west: 65.4, south: 39.0, east: 68.5, north: 40.5 },
        { name: 'Кашкадарьинская',   west: 64.8, south: 37.9, east: 68.0, north: 39.5 },
        { name: 'Сурхандарьинская',  west: 66.4, south: 37.0, east: 68.8, north: 38.5 },
        { name: 'Ташкентская',       west: 68.4, south: 40.2, east: 71.0, north: 41.7 },
        { name: 'Ферганская',        west: 70.5, south: 39.9, east: 72.0, north: 40.9 },
        { name: 'Андижанская',       west: 71.8, south: 40.3, east: 73.0, north: 41.1 },
        { name: 'Наманганская',      west: 70.3, south: 40.7, east: 72.3, north: 41.5 },
    ],
    'Туркменистан': [
        { name: 'Балканский',  west: 52.4, south: 37.0, east: 57.5, north: 42.8 },
        { name: 'Дашогузский', west: 57.0, south: 40.4, east: 63.5, north: 42.8 },
        { name: 'Ахальский',   west: 55.5, south: 37.0, east: 63.0, north: 40.5 },
        { name: 'Марыйский',   west: 59.8, south: 35.1, east: 64.8, north: 39.5 },
        { name: 'Лебапский',   west: 62.0, south: 37.5, east: 66.6, north: 41.0 },
    ],
    'Таджикистан': [
        { name: 'Согдийская',  west: 67.7, south: 39.5, east: 71.0, north: 41.1 },
        { name: 'РРП',         west: 68.0, south: 38.0, east: 70.5, north: 39.6 },
        { name: 'г. Душанбе',  west: 68.55, south: 38.43, east: 69.00, north: 38.75 },
        { name: 'Хатлонская',  west: 68.5, south: 36.7, east: 71.5, north: 38.5 },
        { name: 'ГБАО',        west: 71.0, south: 36.7, east: 75.2, north: 39.5 },
    ],
}

// ── Subscribe mode ───────────────────────────────────────────────────────────
const subscribeMode = ref(new URLSearchParams(window.location.search).has('subscribe'))
const savedZone = ref(props.currentZone ? { ...props.currentZone } : null)
const subscribeSaving = ref(false)

// ── Reactive state ────────────────────────────────────────────────────────────
const mapEl = ref(null)
const selectedOblast = ref(null)
const hotspots = ref([])
const fireHoursFilter = ref(48)
const fireSeverityFilter = ref(null)
const loading = ref(false)
const errorMsg = ref(null)
const activeTab = ref('map')
const mapLayer = ref('fires') // 'fires' | 'forecast'

// ── Forecast state ──────────────────────────────────────────────────────────
const forecastData = ref({})
const forecastLoading = ref(false)

const RISK_LABELS = {
    heavy_rain:  { icon: '🌧️', label: 'Сильный дождь',  color: '#3b82f6' },
    hail_storm:  { icon: '🌨️', label: 'Град / гроза',   color: '#8b5cf6' },
    drought:     { icon: '🏜️', label: 'Засуха',          color: '#f59e0b' },
    fire:        { icon: '🔥', label: 'Пожары',          color: '#ef4444' },
    strong_wind: { icon: '💨', label: 'Сильный ветер',   color: '#06b6d4' },
    frost:       { icon: '❄️', label: 'Заморозки',       color: '#93c5fd' },
}

const FORECAST_MARKER_COLORS = { high: '#ef4444', nominal: '#f59e0b', low: '#4ade80' }

function switchTab(tab) {
    activeTab.value = tab
    if (tab === 'crop') fetchSessions()
}

function switchMapLayer(layer) {
    mapLayer.value = layer
    hotspotLayer?.clearLayers()
    forecastMarkerLayer?.clearLayers()
    if (layer === 'fires') {
        renderHotspots(filteredHotspots.value)
    } else if (layer === 'forecast' && selectedCountry.value) {
        loadAllForecasts()
    }
}

let forecastMarkerLayer = null

async function fetchRegionForecast(oblast) {
    const center = oblastCenter(oblast)
    try {
        const { data } = await axios.post('/forecast/risks', {
            lat: center[0],
            lon: center[1],
            bbox_west: oblast.west,
            bbox_south: oblast.south,
            bbox_east: oblast.east,
            bbox_north: oblast.north,
        })
        return data
    } catch {
        return null
    }
}

async function loadAllForecasts() {
    if (!selectedCountry.value) return
    forecastLoading.value = true
    forecastMarkerLayer?.clearLayers()

    const regions = REGIONS[selectedCountry.value.name] ?? []
    for (const oblast of regions) {
        if (oblast.name.startsWith('г.')) continue
        const result = await fetchRegionForecast(oblast)
        if (result?.forecast) {
            forecastData.value[oblast.name] = result
            renderForecastMarker(oblast, result)
        }
        await new Promise(r => setTimeout(r, 350))
    }
    forecastLoading.value = false
}

async function loadSingleForecast(oblast) {
    forecastLoading.value = true
    const result = await fetchRegionForecast(oblast)
    if (result?.forecast) {
        forecastData.value[oblast.name] = result
    }
    forecastLoading.value = false
}

function renderForecastMarker(oblast, data) {
    if (!forecastMarkerLayer || mapLayer.value !== 'forecast') return
    const sev = data.forecast?.severity ?? 'low'
    const risks = data.forecast?.risks ?? []
    const center = oblastCenter(oblast)
    const color = FORECAST_MARKER_COLORS[sev] || FORECAST_MARKER_COLORS.low

    const marker = L.circleMarker(center, {
        radius: risks.length > 0 ? 10 : 7,
        color: '#fff',
        weight: 2,
        fillColor: color,
        fillOpacity: 0.9,
    }).addTo(forecastMarkerLayer)

    const riskLines = risks.map(r => {
        const cfg = RISK_LABELS[r.type] || { icon: '⚠️', label: r.type }
        return `${cfg.icon} ${cfg.label}: ${r.detail}`
    }).join('<br>')

    const s = data.forecast?.summary
    marker.bindPopup(
        `<div style="font-family:sans-serif;font-size:13px;line-height:1.6;min-width:200px">` +
        `<b>${oblast.name}</b><br>` +
        `🌡️ ${s?.temp_min}…${s?.temp_max}°C &nbsp; 💧 ${s?.precip_total} мм &nbsp; 💨 ${s?.wind_max} м/с<br>` +
        (riskLines ? `<hr style="border-color:#333;margin:4px 0">${riskLines}` : `<span style="color:#4ade80">✅ Рисков не обнаружено</span>`) +
        `</div>`
    )

    marker.bindTooltip(
        risks.length > 0
            ? risks.map(r => (RISK_LABELS[r.type]?.icon || '⚠️')).join('') + ' ' + oblast.name
            : '✅ ' + oblast.name,
        { permanent: false, direction: 'right', offset: [10, 0] }
    )
}

const selectedForecast = computed(() => {
    if (!selectedOblast.value) return null
    return forecastData.value[selectedOblast.value.name] ?? null
})

// ── Crop Chat ─────────────────────────────────────────────────────────────────
const cropMessages = ref([])
const cropInput = ref('')
const cropImage = ref(null)
const cropMediaType = ref('image/jpeg')
const cropFileName = ref(null)
const cropPreview = ref(null)
const cropLoading = ref(false)
const cropFileRef = ref(null)
const cropScrollEl = ref(null)

// ── Sessions ──────────────────────────────────────────────────────────────────
const cropSessions = ref([])
const currentSessionId = ref(null)
const sessionsLoading = ref(false)

async function fetchSessions() {
    sessionsLoading.value = true
    try {
        const { data } = await axios.get('/crop/sessions')
        cropSessions.value = data
    } finally {
        sessionsLoading.value = false
    }
}

async function loadSession(id) {
    if (currentSessionId.value === id) return
    currentSessionId.value = id
    cropMessages.value = []
    clearCropImage()
    cropLoading.value = true
    try {
        const { data } = await axios.get(`/crop/sessions/${id}`)
        cropMessages.value = data.messages
        await nextTick()
        scrollCropToBottom()
    } finally {
        cropLoading.value = false
    }
}

function startNewChat() {
    currentSessionId.value = null
    cropMessages.value = []
    cropInput.value = ''
    clearCropImage()
}

async function deleteCropSession(id, e) {
    e.stopPropagation()
    if (!confirm('Удалить этот чат?')) return
    await axios.delete(`/crop/sessions/${id}`)
    cropSessions.value = cropSessions.value.filter(s => s.id !== id)
    if (currentSessionId.value === id) startNewChat()
}

function upsertSession(session) {
    const idx = cropSessions.value.findIndex(s => s.id === session.id)
    if (idx === -1) {
        cropSessions.value.unshift(session)
    } else {
        cropSessions.value.splice(idx, 1)
        cropSessions.value.unshift(session)
    }
}

function formatSessionDate(dateStr) {
    const d = new Date(dateStr)
    const now = new Date()
    if (d.toDateString() === now.toDateString()) {
        return d.toLocaleTimeString('ru', { hour: '2-digit', minute: '2-digit' })
    }
    return d.toLocaleDateString('ru', { day: '2-digit', month: '2-digit' })
}

function onCropFile(e) {
    const file = e.target.files[0]
    if (!file) return
    cropMediaType.value = file.type || 'image/jpeg'
    cropFileName.value = file.name || null
    const reader = new FileReader()
    reader.onload = (ev) => {
        cropPreview.value = ev.target.result
        cropImage.value = ev.target.result.split(',')[1]
    }
    reader.readAsDataURL(file)
}

function clearCropImage() {
    cropImage.value = null
    cropPreview.value = null
    cropFileName.value = null
    if (cropFileRef.value) cropFileRef.value.value = ''
}

async function sendCrop() {
    const text = cropInput.value.trim()
    const img = cropImage.value
    const media = cropMediaType.value
    const fname = cropFileName.value
    if (!text && !img) return

    cropMessages.value.push({ role: 'user', text, preview: cropPreview.value })
    cropInput.value = ''
    clearCropImage()
    await nextTick()
    scrollCropToBottom()

    cropLoading.value = true
    try {
        const { data } = await axios.post('/n8n/crop', {
            message: text,
            image: img,
            mediaType: media,
            fileName: fname,
            sessionId: currentSessionId.value,
        })

        if (data.sessionId && data.sessionId !== currentSessionId.value) {
            currentSessionId.value = data.sessionId
            upsertSession({ id: data.sessionId, title: data.sessionTitle, updated_at: new Date().toISOString() })
        } else if (data.sessionId) {
            upsertSession({ id: data.sessionId, title: data.sessionTitle, updated_at: new Date().toISOString() })
        }

        cropMessages.value.push({ role: 'ai', text: data.response ?? data, preview: null })
    } catch {
        cropMessages.value.push({ role: 'ai', text: t('home.chat_error'), preview: null })
    } finally {
        cropLoading.value = false
        await nextTick()
        scrollCropToBottom()
    }
}

function scrollCropToBottom() {
    if (cropScrollEl.value) cropScrollEl.value.scrollTop = cropScrollEl.value.scrollHeight
}

function onCropKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault()
        sendCrop()
    }
}

// ── Camera ────────────────────────────────────────────────────────────────────
const cameraOpen = ref(false)
const videoEl = ref(null)
const canvasEl = ref(null)
let cameraStream = null

async function openCamera() {
    cameraOpen.value = true
    await nextTick()
    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: false,
        })
        videoEl.value.srcObject = cameraStream
        await videoEl.value.play()
    } catch {
        cameraOpen.value = false
        alert('Не удалось получить доступ к камере')
    }
}

function closeCamera() {
    cameraStream?.getTracks().forEach(t => t.stop())
    cameraStream = null
    cameraOpen.value = false
}

function capturePhoto() {
    const video = videoEl.value
    const canvas = canvasEl.value
    canvas.width = video.videoWidth
    canvas.height = video.videoHeight
    canvas.getContext('2d').drawImage(video, 0, 0)
    const dataUrl = canvas.toDataURL('image/jpeg', 0.92)
    cropPreview.value = dataUrl
    cropImage.value = dataUrl.split(',')[1]
    cropMediaType.value = 'image/jpeg'
    closeCamera()
}

// ── Selection state ───────────────────────────────────────────────────────────
const selectionLevel = ref('country') // 'country' | 'oblast' | 'detail'
const selectedCountry = ref(null)
const selectedCity = ref(null)

// ── Kazakhstan cities per oblast ──────────────────────────────────────────────
const CITIES = {
    'Акмолинская': [{ name: 'Кокшетау', lat: 53.284, lon: 69.396 }, { name: 'Степногорск', lat: 52.346, lon: 71.889 }, { name: 'Атбасар', lat: 51.806, lon: 68.362 }, { name: 'Макинск', lat: 52.638, lon: 70.857 }],
    'Актюбинская': [{ name: 'Актобе', lat: 50.280, lon: 57.207 }, { name: 'Алга', lat: 49.900, lon: 57.333 }, { name: 'Хромтау', lat: 50.254, lon: 58.449 }, { name: 'Кандыагаш', lat: 49.464, lon: 57.425 }, { name: 'Шалқар', lat: 47.834, lon: 59.611 }],
    'Алматинская': [{ name: 'Талдықорған', lat: 45.015, lon: 78.373 }, { name: 'Қапшағай', lat: 43.860, lon: 77.071 }, { name: 'Текелі', lat: 44.858, lon: 78.766 }, { name: 'Есік', lat: 43.349, lon: 77.440 }, { name: 'Қаскелең', lat: 43.199, lon: 76.626 }],
    'Атырауская': [{ name: 'Атырау', lat: 47.117, lon: 51.924 }, { name: 'Доссор', lat: 47.534, lon: 53.037 }, { name: 'Құлсары', lat: 46.991, lon: 54.024 }, { name: 'Махамбет', lat: 47.673, lon: 51.545 }],
    'Восточно-Казахстанская': [{ name: 'Өскемен', lat: 49.948, lon: 82.628 }, { name: 'Семей', lat: 50.411, lon: 80.226 }, { name: 'Риддер', lat: 50.347, lon: 83.512 }, { name: 'Аягөз', lat: 47.966, lon: 80.432 }, { name: 'Зайсан', lat: 47.477, lon: 84.873 }, { name: 'Шемонайха', lat: 50.633, lon: 81.923 }],
    'Жамбылская': [{ name: 'Тараз', lat: 42.900, lon: 71.373 }, { name: 'Шу', lat: 43.596, lon: 73.756 }, { name: 'Каратау', lat: 43.172, lon: 70.454 }, { name: 'Жанатас', lat: 43.062, lon: 70.375 }, { name: 'Қордай', lat: 43.302, lon: 74.095 }],
    'Западно-Казахстанская': [{ name: 'Орал', lat: 51.233, lon: 51.371 }, { name: 'Аксай', lat: 51.179, lon: 53.003 }, { name: 'Шыңғырлау', lat: 50.247, lon: 52.441 }, { name: 'Жаңақала', lat: 50.266, lon: 50.257 }],
    'Карагандинская': [{ name: 'Қарағанды', lat: 49.807, lon: 73.088 }, { name: 'Теміртау', lat: 50.058, lon: 72.961 }, { name: 'Балқаш', lat: 46.848, lon: 74.995 }, { name: 'Сарань', lat: 49.804, lon: 72.864 }, { name: 'Приозерск', lat: 46.047, lon: 73.897 }],
    'Костанайская': [{ name: 'Қостанай', lat: 53.215, lon: 63.627 }, { name: 'Рудный', lat: 52.957, lon: 63.118 }, { name: 'Лисаковск', lat: 52.646, lon: 62.489 }, { name: 'Арқалық', lat: 50.248, lon: 66.887 }, { name: 'Житіқара', lat: 52.187, lon: 61.198 }],
    'Кызылординская': [{ name: 'Қызылорда', lat: 44.853, lon: 65.510 }, { name: 'Байқоңыр', lat: 45.618, lon: 63.311 }, { name: 'Арал', lat: 46.797, lon: 61.672 }, { name: 'Қазалы', lat: 45.762, lon: 62.104 }, { name: 'Шиелі', lat: 44.183, lon: 66.742 }],
    'Мангыстауская': [{ name: 'Ақтау', lat: 43.654, lon: 51.175 }, { name: 'Жаңаөзен', lat: 43.338, lon: 52.869 }, { name: 'Бейнеу', lat: 45.317, lon: 55.096 }, { name: 'Форт-Шевченко', lat: 44.509, lon: 50.247 }],
    'Павлодарская': [{ name: 'Павлодар', lat: 52.285, lon: 76.966 }, { name: 'Екібастұз', lat: 51.710, lon: 75.365 }, { name: 'Ақсу', lat: 52.044, lon: 76.907 }, { name: 'Шарбақты', lat: 52.537, lon: 78.982 }],
    'Северо-Казахстанская': [{ name: 'Петропавл', lat: 54.876, lon: 69.158 }, { name: 'Мамлют', lat: 54.707, lon: 68.577 }, { name: 'Тайынша', lat: 53.836, lon: 69.776 }, { name: 'Бұлаево', lat: 54.903, lon: 70.432 }, { name: 'Есіл', lat: 51.960, lon: 66.404 }],
    'Туркестанская': [{ name: 'Шымкент', lat: 42.317, lon: 69.590 }, { name: 'Түркістан', lat: 43.298, lon: 68.270 }, { name: 'Кентау', lat: 43.518, lon: 68.510 }, { name: 'Арыс', lat: 42.428, lon: 68.804 }, { name: 'Сарыағаш', lat: 41.456, lon: 69.173 }, { name: 'Шардара', lat: 41.259, lon: 68.085 }],
    'Улытауская': [{ name: 'Жезқазған', lat: 47.803, lon: 67.707 }, { name: 'Сатпаев', lat: 47.903, lon: 67.524 }, { name: 'Ұлытау', lat: 48.619, lon: 67.006 }, { name: 'Жайрем', lat: 48.021, lon: 70.785 }],
    'г. Алматы': [{ name: 'Алмалинский р-н', lat: 43.262, lon: 76.946 }, { name: 'Бостандық р-н', lat: 43.249, lon: 76.858 }, { name: 'Медеу р-н', lat: 43.268, lon: 77.014 }, { name: 'Алатау р-н', lat: 43.217, lon: 76.999 }, { name: 'Жетісу р-н', lat: 43.292, lon: 77.050 }, { name: 'Наурызбай р-н', lat: 43.206, lon: 76.791 }, { name: 'Түрксіб р-н', lat: 43.311, lon: 77.046 }, { name: 'Әуезов р-н', lat: 43.224, lon: 76.875 }],
    'г. Астана': [{ name: 'Есіл р-н', lat: 51.134, lon: 71.502 }, { name: 'Алматы р-н', lat: 51.159, lon: 71.413 }, { name: 'Байқоңыр р-н', lat: 51.203, lon: 71.381 }, { name: 'Нұра р-н', lat: 51.221, lon: 71.509 }, { name: 'Сарыарқа р-н', lat: 51.186, lon: 71.500 }],
    // Kyrgyzstan
    'Чуйская':         [{ name: 'Бишкек', lat: 42.87, lon: 74.59 }, { name: 'Токмок', lat: 42.84, lon: 75.30 }, { name: 'Кара-Балта', lat: 42.83, lon: 73.86 }, { name: 'Кант', lat: 42.89, lon: 74.85 }, { name: 'Сокулук', lat: 42.86, lon: 74.30 }],
    'Таласская':       [{ name: 'Талас', lat: 42.52, lon: 72.24 }, { name: 'Кара-Буура', lat: 42.54, lon: 71.80 }],
    'Иссык-Кульская':  [{ name: 'Каракол', lat: 42.49, lon: 78.40 }, { name: 'Балыкчы', lat: 42.46, lon: 76.19 }, { name: 'Чолпон-Ата', lat: 42.65, lon: 77.08 }, { name: 'Бостери', lat: 42.67, lon: 76.80 }],
    'Нарынская':       [{ name: 'Нарын', lat: 41.43, lon: 76.00 }, { name: 'Ат-Баши', lat: 41.17, lon: 75.81 }, { name: 'Кочкор', lat: 42.21, lon: 75.76 }],
    'Джалал-Абадская': [{ name: 'Жалал-Абад', lat: 40.93, lon: 73.00 }, { name: 'Кара-Куль', lat: 41.62, lon: 72.72 }, { name: 'Таш-Кёмюр', lat: 41.35, lon: 72.22 }, { name: 'Майлуу-Суу', lat: 41.27, lon: 72.46 }],
    'Ошская':          [{ name: 'Ош', lat: 40.52, lon: 72.80 }, { name: 'Узген', lat: 40.77, lon: 73.29 }, { name: 'Кара-Суу', lat: 40.69, lon: 72.86 }],
    'Баткенская':      [{ name: 'Баткен', lat: 40.06, lon: 70.82 }, { name: 'Кадамжай', lat: 40.12, lon: 71.27 }, { name: 'Исфана', lat: 39.83, lon: 69.52 }, { name: 'Сулюкта', lat: 39.94, lon: 69.57 }],
    // Uzbekistan
    'Каракалпакстан':  [{ name: 'Нукус', lat: 42.46, lon: 59.62 }, { name: 'Тахиаташ', lat: 42.30, lon: 59.93 }, { name: 'Муйнак', lat: 43.77, lon: 59.02 }, { name: 'Чимбай', lat: 42.94, lon: 59.78 }],
    'Хорезмская':      [{ name: 'Ургенч', lat: 41.55, lon: 60.63 }, { name: 'Хива', lat: 41.38, lon: 60.36 }, { name: 'Хонка', lat: 41.53, lon: 60.82 }],
    'Навоийская':      [{ name: 'Навои', lat: 40.08, lon: 65.38 }, { name: 'Зарафшан', lat: 41.56, lon: 64.19 }, { name: 'Учкудук', lat: 41.56, lon: 63.56 }, { name: 'Газли', lat: 40.13, lon: 63.87 }],
    'Бухарская':       [{ name: 'Бухара', lat: 39.77, lon: 64.42 }, { name: 'Каган', lat: 39.71, lon: 64.55 }, { name: 'Коровулбозор', lat: 39.47, lon: 63.77 }],
    'Сырдарьинская':   [{ name: 'Гулистан', lat: 40.49, lon: 68.78 }, { name: 'Янгиер', lat: 40.39, lon: 68.83 }, { name: 'Ширин', lat: 40.50, lon: 69.04 }],
    'Джизакская':      [{ name: 'Джизак', lat: 40.12, lon: 67.84 }, { name: 'Зафаробод', lat: 40.17, lon: 68.05 }, { name: 'Дустлик', lat: 40.53, lon: 67.94 }],
    'Самаркандская':   [{ name: 'Самарканд', lat: 39.65, lon: 66.98 }, { name: 'Катта-Курган', lat: 39.90, lon: 66.26 }, { name: 'Ургут', lat: 39.40, lon: 67.25 }],
    'Кашкадарьинская': [{ name: 'Карши', lat: 38.86, lon: 65.79 }, { name: 'Шахрисабз', lat: 39.06, lon: 66.84 }, { name: 'Китаб', lat: 39.14, lon: 66.88 }],
    'Сурхандарьинская':[{ name: 'Термез', lat: 37.22, lon: 67.28 }, { name: 'Денов', lat: 38.27, lon: 67.89 }, { name: 'Байсун', lat: 38.21, lon: 67.19 }],
    'Ташкентская':     [{ name: 'Ташкент', lat: 41.30, lon: 69.24 }, { name: 'Алмалык', lat: 40.85, lon: 69.60 }, { name: 'Ангрен', lat: 41.02, lon: 69.98 }, { name: 'Чирчик', lat: 41.47, lon: 69.58 }],
    'Ферганская':      [{ name: 'Фергана', lat: 40.38, lon: 71.78 }, { name: 'Маргилан', lat: 40.47, lon: 71.72 }, { name: 'Коканд', lat: 40.53, lon: 70.94 }],
    'Андижанская':     [{ name: 'Андижан', lat: 40.78, lon: 72.35 }, { name: 'Асака', lat: 40.64, lon: 72.24 }],
    'Наманганская':    [{ name: 'Наманган', lat: 41.00, lon: 71.67 }, { name: 'Чуст', lat: 41.13, lon: 71.22 }],
    // Turkmenistan
    'Балканский':  [{ name: 'Балканабад', lat: 39.51, lon: 54.37 }, { name: 'Туркменбашы', lat: 40.02, lon: 52.97 }, { name: 'Сердар', lat: 38.97, lon: 56.27 }],
    'Дашогузский': [{ name: 'Дашогуз', lat: 41.84, lon: 59.97 }, { name: 'Гёнеургенч', lat: 42.32, lon: 59.15 }, { name: 'Болдумсаз', lat: 41.75, lon: 59.70 }],
    'Ахальский':   [{ name: 'Ашхабад', lat: 37.96, lon: 58.33 }, { name: 'Аркадаг', lat: 37.93, lon: 58.32 }, { name: 'Анау', lat: 37.90, lon: 58.52 }, { name: 'Теджен', lat: 37.39, lon: 60.51 }],
    'Марыйский':   [{ name: 'Мары', lat: 37.60, lon: 61.83 }, { name: 'Байрамали', lat: 37.62, lon: 62.18 }, { name: 'Ёлётен', lat: 37.28, lon: 62.37 }],
    'Лебапский':   [{ name: 'Туркменабат', lat: 39.09, lon: 63.57 }, { name: 'Сейди', lat: 39.44, lon: 62.89 }, { name: 'Атамырат', lat: 37.84, lon: 65.21 }],
    // Tajikistan
    'Согдийская':  [{ name: 'Худжанд', lat: 40.28, lon: 69.62 }, { name: 'Бустон', lat: 40.52, lon: 69.67 }, { name: 'Истаравшан', lat: 39.91, lon: 69.00 }, { name: 'Исфара', lat: 40.12, lon: 70.62 }],
    'РРП':         [{ name: 'Турсунзода', lat: 38.51, lon: 68.22 }, { name: 'Вахдат', lat: 38.56, lon: 69.01 }, { name: 'Файзобод', lat: 38.55, lon: 69.30 }, { name: 'Рогун', lat: 38.56, lon: 69.76 }],
    'г. Душанбе':  [{ name: 'Сино р-н', lat: 38.56, lon: 68.73 }, { name: 'Фирдавси р-н', lat: 38.57, lon: 68.74 }, { name: 'Шохмансур р-н', lat: 38.54, lon: 68.74 }, { name: 'Исмоили Сомони р-н', lat: 38.58, lon: 68.77 }],
    'Хатлонская':  [{ name: 'Бохтар', lat: 37.84, lon: 68.78 }, { name: 'Куляб', lat: 37.91, lon: 69.78 }, { name: 'Вахш', lat: 37.69, lon: 68.83 }, { name: 'Нурек', lat: 38.38, lon: 69.32 }],
    'ГБАО':        [{ name: 'Хорог', lat: 37.49, lon: 71.55 }, { name: 'Мургаб', lat: 38.17, lon: 73.97 }, { name: 'Ишкашим', lat: 36.72, lon: 71.61 }],
}

// ── Leaflet internals (non-reactive) ─────────────────────────────────────────
let map = null
let countryLayers = {}
let rectLayers = {}
let hotspotLayer = null
let cityMarkerLayer = null

// ── Helpers ──────────────────────────────────────────────────────────────────
function oblastBounds(o) {
    return [[o.south, o.west], [o.north, o.east]]
}
function oblastCenter(o) {
    return [(o.south + o.north) / 2, (o.west + o.east) / 2]
}

// ── Marker styles ───────────────────────────────────────────────────────────
const countryMarkerStyle      = () => ({ radius: 10, color: '#fff', weight: 2, fillColor: '#00bcd4', fillOpacity: 0.85 })
const countryActiveMarkerStyle= () => ({ radius: 12, color: '#fff', weight: 2.5, fillColor: '#00e5ff', fillOpacity: 1 })
const normalMarkerStyle       = () => ({ radius: 8, color: '#fff', weight: 1.5, fillColor: '#00e676', fillOpacity: 0.85 })
const activeMarkerStyle       = () => ({ radius: 10, color: '#fff', weight: 2, fillColor: '#00e676', fillOpacity: 1 })
const fireMarkerStyle         = () => ({ radius: 9, color: '#fff', weight: 1.5, fillColor: '#ff2200', fillOpacity: 0.9 })
const fireActiveMarkerStyle   = () => ({ radius: 11, color: '#fff', weight: 2, fillColor: '#ff2200', fillOpacity: 1 })

// ── Map init ──────────────────────────────────────────────────────────────────
function initMap() {
    map = L.map(mapEl.value, { center: [41.0, 62.0], zoom: 4 })

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 18,
    }).addTo(map)

    hotspotLayer = L.layerGroup().addTo(map)
    cityMarkerLayer = L.layerGroup().addTo(map)
    forecastMarkerLayer = L.layerGroup().addTo(map)

    COUNTRIES.forEach(country => {
        const marker = L.circleMarker(oblastCenter(country), countryMarkerStyle())
            .addTo(map)
            .bindTooltip(country.flag + ' ' + country.name, { permanent: true, direction: 'right', offset: [10, 0], className: 'afs-country-tooltip' })
        marker.on('click', () => selectCountry(country))
        countryLayers[country.name] = marker
    })

    if (props.currentZone) {
        for (const [countryName, regions] of Object.entries(REGIONS)) {
            const found = regions.find(o => o.name === props.currentZone.oblast_name)
            if (found) {
                const country = COUNTRIES.find(c => c.name === countryName)
                if (country) { selectCountry(country); break }
            }
        }
    }
}

// ── Detail drill-down ─────────────────────────────────────────────────────────
function drawDetail(oblast) {
    cityMarkerLayer.clearLayers()

        ; (CITIES[oblast.name] ?? []).forEach(city => {
            L.circleMarker([city.lat, city.lon], {
                radius: 6, color: '#fff', fillColor: '#4db8ff', fillOpacity: 1, weight: 1.5,
            }).addTo(cityMarkerLayer).bindTooltip(city.name, { permanent: false })
                .on('click', () => selectCity(city))
        })
}

function clearDetail() {
    cityMarkerLayer?.clearLayers()
}

async function selectCity(city) {
    selectedCity.value = city
    const r = 0.35
    map.flyTo([city.lat, city.lon], 10, { duration: 0.7 })
    await fetchFires({ name: city.name, west: city.lon - r, east: city.lon + r, south: city.lat - r, north: city.lat + r })
}

async function selectGridCell(cell) {
    selectedCity.value = { name: `Блок ${cell.label}`, ...cell }
    map.fitBounds([[cell.south, cell.west], [cell.north, cell.east]], { padding: [16, 16] })
    await fetchFires({ name: `Блок ${cell.label}`, ...cell })
}

function backToCountries() {
    selectionLevel.value = 'country'
    selectedCountry.value = null
    selectedOblast.value = null
    selectedCity.value = null
    oblastFireHotspots.value = {}
    forecastData.value = {}
    clearDetail()
    hotspots.value = []
    hotspotLayer.clearLayers()
    forecastMarkerLayer?.clearLayers()
    Object.values(rectLayers).forEach(l => map.removeLayer(l))
    rectLayers = {}
    Object.values(countryLayers).forEach(l => {
        l.setStyle(countryMarkerStyle())
        if (!map.hasLayer(l)) l.addTo(map)
    })
    map.flyTo([41.0, 62.0], 4, { duration: 0.8 })
}

function backToOblasts() {
    selectionLevel.value = 'oblast'
    selectedCity.value = null
    clearDetail()
    if (selectedOblast.value) {
        const name = selectedOblast.value.name
        const count = oblastFireCounts.value[name] ?? 0
        rectLayers[name]?.setStyle(count > 0 ? fireMarkerStyle() : normalMarkerStyle())
        selectedOblast.value = null
    }
    hotspots.value = []
    hotspotLayer.clearLayers()
    if (selectedCountry.value) {
        map.fitBounds(oblastBounds(selectedCountry.value), { padding: [20, 20] })
    }
}

// ── Selection ─────────────────────────────────────────────────────────────────
function selectCountry(country) {
    Object.entries(countryLayers).forEach(([name, l]) => {
        if (name !== country.name) map.removeLayer(l)
        else l.setStyle(countryActiveMarkerStyle())
    })
    selectedCountry.value = country
    selectedOblast.value = null
    selectedCity.value = null
    selectionLevel.value = 'oblast'
    oblastFireHotspots.value = {}
    hotspots.value = []
    hotspotLayer.clearLayers()
    clearDetail()

    Object.values(rectLayers).forEach(l => map.removeLayer(l))
    rectLayers = {}

    const regions = REGIONS[country.name] ?? []
    regions.forEach(oblast => {
        const marker = L.circleMarker(oblastCenter(oblast), normalMarkerStyle())
            .addTo(map)
            .bindTooltip(oblast.name, { permanent: false, direction: 'right', offset: [8, 0] })
        marker.on('click', () => selectOblast(oblast))
        rectLayers[oblast.name] = marker
    })

    map.fitBounds(oblastBounds(country), { padding: [30, 30] })
    setTimeout(checkAllOblastsFires, 500)
}

function selectOblast(oblast) {
    clearDetail()
    if (selectedOblast.value) {
        rectLayers[selectedOblast.value.name]?.setStyle(normalMarkerStyle())
    }
    selectedOblast.value = oblast
    selectedCity.value = null
    selectionLevel.value = 'detail'
    rectLayers[oblast.name]?.setStyle(activeMarkerStyle())
    map.fitBounds(oblastBounds(oblast), { padding: [30, 30] })
    drawDetail(oblast)
    fetchFires(oblast)
    if (mapLayer.value === 'forecast' && !forecastData.value[oblast.name]) {
        loadSingleForecast(oblast)
    }
}

// Фоновая проверка пожаров по регионам выбранной страны
async function checkAllOblastsFires() {
    if (!selectedCountry.value) return
    const regions = REGIONS[selectedCountry.value.name] ?? []
    for (const oblast of regions) {
        if (oblast.name.startsWith('г.')) continue
        try {
            const { data } = await axios.post('/zone/hotspots', {
                bbox_west: oblast.west,
                bbox_south: oblast.south,
                bbox_east: oblast.east,
                bbox_north: oblast.north,
            })
            oblastFireHotspots.value[oblast.name] = data.hotspots ?? []
        } catch { /* тихо игнорируем */ }
        await new Promise(r => setTimeout(r, 300))
    }
}

onMounted(() => { initMap() })

async function fetchFires(oblast) {
    loading.value = true
    errorMsg.value = null
    hotspots.value = []
    hotspotLayer.clearLayers()

    try {
        const { data } = await axios.post('/zone/hotspots', {
            bbox_west: oblast.west,
            bbox_south: oblast.south,
            bbox_east: oblast.east,
            bbox_north: oblast.north,
        })
        hotspots.value = data.hotspots
        renderHotspots(filteredHotspots.value)
    } catch {
        errorMsg.value = 'Ошибка получения данных о пожарах.'
    } finally {
        loading.value = false
    }
}

async function saveSubscription() {
    if (!selectedOblast.value) return
    subscribeSaving.value = true
    try {
        const oblast = selectedOblast.value
        const { data } = await axios.patch('/zone', {
            oblast_name: oblast.name,
            bbox_west: oblast.west,
            bbox_south: oblast.south,
            bbox_east: oblast.east,
            bbox_north: oblast.north,
        })
        savedZone.value = data.zone
        subscribeMode.value = false
        window.history.replaceState({}, '', window.location.pathname)
    } catch {
        errorMsg.value = 'Ошибка сохранения региона.'
    } finally {
        subscribeSaving.value = false
    }
}

function goToSavedZone() {
    if (!savedZone.value) return
    const zone = savedZone.value
    const oblastName = zone.oblast_name
    for (const [countryName, regions] of Object.entries(REGIONS)) {
        const found = regions.find(o => o.name === oblastName)
        if (found) {
            const country = COUNTRIES.find(c => c.name === countryName)
            if (country && selectedCountry.value?.name !== countryName) {
                selectCountry(country)
            }
            nextTick(() => selectOblast(found))
            return
        }
    }
}

async function restoreSelection(oblast) {
    selectedOblast.value = oblast
    selectionLevel.value = 'detail'
    rectLayers[oblast.name]?.setStyle(activeMarkerStyle())
    loading.value = true
    try {
        const { data } = await axios.get('/zone/fires')
        hotspots.value = data.hotspots
        renderHotspots(filteredHotspots.value)
    } catch { /* silent */ } finally {
        loading.value = false
    }
}
const oblastFireHotspots = ref({})

const oblastFireCounts = computed(() => {
    const cutoff = Date.now() - fireHoursFilter.value * 3600 * 1000
    const result = {}
    for (const [name, spots] of Object.entries(oblastFireHotspots.value)) {
        result[name] = spots.filter(spot => {
            const d = spotToDate(spot)
            return !d || d.getTime() >= cutoff
        }).length
    }
    return result
})

watch(oblastFireCounts, (newCounts) => {
    for (const [name, count] of Object.entries(newCounts)) {
        if (!rectLayers[name]) continue
        if (selectedOblast.value?.name === name) continue
        rectLayers[name].setStyle(count > 0 ? fireMarkerStyle() : normalMarkerStyle())
    }
}, { deep: true })

// ── Hotspot markers ───────────────────────────────────────────────────────────
function renderHotspots(spots) {
    hotspotLayer.clearLayers()
    spots.forEach(spot => {
        const sev = getSpotSeverity(spot)
        const cfg = SEVERITY_CONFIG[sev]
        L.circleMarker([spot.lat, spot.lon], {
            radius: cfg.radius, color: '#fff', fillColor: cfg.color, fillOpacity: 0.9, weight: 1.5,
        })
            .bindPopup(
                `<div style="font-family:sans-serif;font-size:13px;line-height:1.7;min-width:200px">` +
                `<b style="font-size:14px">🔥 Очаг возгорания</b><br>` +
                `<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;background:${cfg.color}22;color:${cfg.color};border:1px solid ${cfg.color}55;margin:2px 0">` +
                `${cfg.label}</span><br>` +
                `<span style="color:#e05050">📅 Обнаружен:</span> <b>${formatSpotDateTime(spot)}</b><br>` +
                `🌡️ Яркость: ${spot.brightness} K<br>` +
                `⚡ FRP: ${spot.frp} МВт<br>` +
                `🎯 Уверенность: ${spot.confidence}<br>` +
                `🛰️ Спутник: ${spot.satellite || '—'}<br>` +
                `🌗 Период: ${spot.daynight === 'D' ? 'День' : 'Ночь'}` +
                `</div>`
            )
            .addTo(hotspotLayer)
    })

    if (selectedOblast.value) {
        const hasFire = spots.length > 0
        rectLayers[selectedOblast.value.name]?.setStyle(
            hasFire ? fireActiveMarkerStyle() : activeMarkerStyle()
        )
    }
}

// ── Region Eco Stub Data ──────────────────────────────────────────────────────
const REGION_ECO = {
    'Костанайская':          { degradation: 3.2, water: 'высокий',     co2: 45 },
    'Акмолинская':           { degradation: 2.8, water: 'средний',     co2: 38 },
    'Павлодарская':          { degradation: 4.1, water: 'высокий',     co2: 52 },
    'Карагандинская':        { degradation: 3.7, water: 'критический', co2: 61 },
    'Северо-Казахстанская':  { degradation: 2.1, water: 'низкий',      co2: 28 },
    'Западно-Казахстанская': { degradation: 3.9, water: 'высокий',     co2: 44 },
    'Атырауская':            { degradation: 4.5, water: 'критический', co2: 38 },
    'Мангыстауская':         { degradation: 4.8, water: 'критический', co2: 29 },
    'Актюбинская':           { degradation: 3.4, water: 'высокий',     co2: 41 },
    'Жамбылская':            { degradation: 3.1, water: 'средний',     co2: 33 },
    'Туркестанская':         { degradation: 3.6, water: 'высокий',     co2: 37 },
    'Алматинская':           { degradation: 2.9, water: 'средний',     co2: 31 },
    'Кызылординская':        { degradation: 4.3, water: 'критический', co2: 48 },
    'Восточно-Казахстанская':{ degradation: 2.4, water: 'низкий',      co2: 22 },
    'г. Алматы':             { degradation: 1.8, water: 'низкий',      co2: 15 },
    'г. Астана':             { degradation: 1.5, water: 'низкий',      co2: 12 },
    'Улытауская':            { degradation: 3.8, water: 'высокий',     co2: 43 },
}

const regionEco = computed(() =>
    selectedOblast.value
        ? (REGION_ECO[selectedOblast.value.name] ?? { degradation: 3.0, water: 'средний', co2: 35 })
        : { degradation: 3.0, water: 'средний', co2: 35 }
)

function openAiWithContext() {
    switchTab('crop')
    cropInput.value = `Расскажи про экологическую ситуацию и пожарные риски в ${selectedOblast.value.name}`
    nextTick(() => scrollCropToBottom())
}

function formatHotspotTime(t) {
    if (!t) return '—'
    const s = String(t).padStart(4, '0')
    return s.slice(0, 2) + ':' + s.slice(2) + ' UTC'
}

function spotToDate(spot) {
    if (!spot.acq_date || spot.acq_time === undefined) return null
    const s = String(spot.acq_time).padStart(4, '0')
    return new Date(`${spot.acq_date}T${s.slice(0, 2)}:${s.slice(2)}:00Z`)
}

function formatSpotDateTime(spot) {
    const d = spotToDate(spot)
    if (!d) return '—'
    return d.toLocaleString('ru', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
        timeZone: 'UTC', timeZoneName: 'short',
    })
}

function getSpotSeverity(spot) {
    if (spot.severity) return spot.severity
    if (spot.frp >= 25 || spot.brightness >= 400) return 'high'
    if (spot.frp >= 8 || spot.brightness >= 340) return 'nominal'
    return 'low'
}

const SEVERITY_CONFIG = {
    low:     { label: 'Слабый',    color: '#facc15', border: '#a16207', radius: 6 },
    nominal: { label: 'Умеренный', color: '#fb923c', border: '#c2410c', radius: 8 },
    high:    { label: 'Высокий',   color: '#ef4444', border: '#991b1b', radius: 10 },
}

function severityLabel(spot) {
    return SEVERITY_CONFIG[getSpotSeverity(spot)]?.label ?? 'Слабый'
}

function severityBadgeClass(spot) {
    const s = getSpotSeverity(spot)
    if (s === 'high') return 'afs-rp-badge afs-rp-badge--red'
    if (s === 'nominal') return 'afs-rp-badge afs-rp-badge--orange'
    return 'afs-rp-badge afs-rp-badge--yellow'
}

const filteredHotspots = computed(() => {
    if (!hotspots.value.length) return []
    const cutoff = Date.now() - fireHoursFilter.value * 3600 * 1000
    return hotspots.value.filter(spot => {
        const d = spotToDate(spot)
        if (d && d.getTime() < cutoff) return false
        if (fireSeverityFilter.value && getSpotSeverity(spot) !== fireSeverityFilter.value) return false
        return true
    })
})

function degradationClass(v) {
    if (v > 4)    return 'afs-rp__val--red'
    if (v > 3)    return 'afs-rp__val--orange'
    if (v <= 2.5) return 'afs-rp__val--green'
    return ''
}

function waterBadgeClass(w) {
    if (w === 'критический') return 'afs-rp-badge afs-rp-badge--red'
    if (w === 'высокий')     return 'afs-rp-badge afs-rp-badge--orange'
    if (w === 'средний')     return 'afs-rp-badge afs-rp-badge--yellow'
    return 'afs-rp-badge afs-rp-badge--green'
}

function fireBadgeClass(count) {
    if (count >= 10) return 'afs-rp-badge afs-rp-badge--red'
    if (count >= 4)  return 'afs-rp-badge afs-rp-badge--orange'
    return 'afs-rp-badge afs-rp-badge--yellow'
}

function fireRiskLabel(count) {
    if (count >= 10) return t('home.risk_critical')
    if (count >= 4)  return t('home.risk_high')
    return t('home.risk_moderate')
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────
watch(fireHoursFilter, () => {
    if (hotspotLayer) renderHotspots(filteredHotspots.value)
})

watch(fireSeverityFilter, () => {
    if (hotspotLayer) renderHotspots(filteredHotspots.value)
})

onBeforeUnmount(() => { map?.remove(); closeCamera() })

</script>

<template>
    <AppLayout>

        <Head title="Главная — AgroMind KZ" />

        <!-- Error banner -->
        <div v-if="errorMsg" class="afs-error-banner">{{ errorMsg }}</div>

        <!-- Tabs -->
        <div class="afs-tabs">
            <button class="afs-tab" :class="{ 'afs-tab--active': activeTab === 'map' }" @click="switchTab('map')">
                Карта
            </button>
            <button class="afs-tab" :class="{ 'afs-tab--active': activeTab === 'crop' }" @click="switchTab('crop')">
                ИИ Помощник агроному
            </button>

            <!-- Map layer sub-toggle -->
            <div v-if="activeTab === 'map'" class="afs-layer-toggle">
                <button class="afs-layer-btn" :class="{ 'afs-layer-btn--active': mapLayer === 'fires' }" @click="switchMapLayer('fires')">
                    🔥 Пожары
                </button>
                <button class="afs-layer-btn" :class="{ 'afs-layer-btn--active': mapLayer === 'forecast' }" @click="switchMapLayer('forecast')">
                    ⛈️ Прогноз угроз
                </button>
            </div>
        </div>

        <!-- Workspace -->
        <div class="afs-workspace">

            <!-- Sidebar -->
            <aside class="afs-sidebar">

                <!-- Country-level header -->
                <div v-if="selectionLevel === 'country'" class="afs-sidebar__header">
                    <span class="afs-sidebar__title">Центральная Азия</span>
                </div>

                <!-- Oblast-level header -->
                <div v-else-if="selectionLevel === 'oblast'" class="afs-sidebar__header afs-sidebar__header--detail">
                    <button class="afs-back-btn" @click="backToCountries" title="Назад">←</button>
                    <span class="afs-sidebar__title afs-sidebar__title--detail">
                        {{ selectedCountry?.flag }} {{ selectedCountry?.name }}
                    </span>
                </div>

                <!-- Detail-level header -->
                <div v-else class="afs-sidebar__header afs-sidebar__header--detail">
                    <button class="afs-back-btn" @click="backToOblasts" title="Назад">←</button>
                    <span class="afs-sidebar__title afs-sidebar__title--detail">{{ selectedOblast?.name }}</span>
                    <span v-if="filteredHotspots.length" class="afs-fire-badge">🔥 {{ filteredHotspots.length }}</span>
                </div>

                <!-- Country list -->
                <ul v-if="selectionLevel === 'country'" class="afs-oblast-list">
                    <li v-for="country in COUNTRIES" :key="country.name"
                        class="afs-oblast-item afs-oblast-item--country"
                        @click="selectCountry(country)">
                        <span class="afs-oblast-item__name">{{ country.flag }} {{ country.name }}</span>
                    </li>
                </ul>

                <!-- Oblast list -->
                <ul v-else-if="selectionLevel === 'oblast'" class="afs-oblast-list">
                    <li v-for="oblast in (REGIONS[selectedCountry?.name] ?? [])" :key="oblast.name"
                        class="afs-oblast-item"
                        :class="{
                            'afs-oblast-item--active': selectedOblast?.name === oblast.name,
                            'afs-oblast-item--fire': oblastFireCounts[oblast.name] > 0
                        }"
                        @click="selectOblast(oblast)">
                        <span class="afs-oblast-item__name">
                            {{ oblast.name }}
                            <span v-if="oblastFireCounts[oblast.name] > 0" class="afs-fire-dot">🔥</span>
                        </span>
                        <span v-if="oblastFireCounts[oblast.name] > 0" class="afs-oblast-item__count">
                            {{ oblastFireCounts[oblast.name] }}
                        </span>
                    </li>
                </ul>

                <!-- City list (detail level) -->
                <ul v-else class="afs-oblast-list">
                    <li class="afs-detail-hint">Нас. пункты — или выберите блок на карте</li>
                    <li v-for="city in (CITIES[selectedOblast?.name] ?? [])" :key="city.name"
                        class="afs-oblast-item"
                        :class="{ 'afs-oblast-item--active': selectedCity?.name === city.name }"
                        @click="selectCity(city)">
                        <span class="afs-oblast-item__name">📍 {{ city.name }}</span>
                        <span v-if="selectedCity?.name === city.name && filteredHotspots.length"
                            class="afs-oblast-item__count">{{ filteredHotspots.length }}</span>
                    </li>
                </ul>

            </aside>

            <!-- Map panel -->
            <div v-show="activeTab === 'map'" class="afs-map-panel">

                <!-- Subscribe mode banner -->
                <div v-if="subscribeMode" class="afs-subscribe-banner">
                    <span>📍 Выберите регион для подписки на уведомления</span>
                    <button class="afs-subscribe-banner__cancel" @click="subscribeMode = false; window.history.replaceState({}, '', window.location.pathname)">Отмена</button>
                </div>

                <div ref="mapEl" class="afs-map"></div>

                <!-- Return to saved zone button -->
                <button
                    v-if="savedZone && !subscribeMode && selectedOblast?.name !== savedZone.oblast_name"
                    class="afs-return-zone-btn"
                    @click="goToSavedZone"
                >
                    📍 Вернуться к своему региону ({{ savedZone.oblast_name }})
                </button>

                <!-- Fire filter bars (fires layer only) -->
                <div v-if="mapLayer === 'fires'" class="afs-fire-filters">
                    <div class="afs-fire-filter-bar">
                        <span class="afs-filt-label">🔥 Период:</span>
                        <button class="afs-filt-btn" :class="{ 'afs-filt-btn--active': fireHoursFilter === 12 }" @click="fireHoursFilter = 12">12ч</button>
                        <button class="afs-filt-btn" :class="{ 'afs-filt-btn--active': fireHoursFilter === 24 }" @click="fireHoursFilter = 24">24ч</button>
                        <button class="afs-filt-btn" :class="{ 'afs-filt-btn--active': fireHoursFilter === 48 }" @click="fireHoursFilter = 48">48ч</button>
                        <div class="afs-filt-slider-wrap">
                            <input class="afs-filt-slider" type="range" min="1" max="48" step="1" v-model.number="fireHoursFilter"
                                :style="{ '--pct': ((fireHoursFilter - 1) / 47 * 100) + '%' }" />
                        </div>
                        <span class="afs-filt-value">{{ fireHoursFilter }}ч</span>
                    </div>
                    <div class="afs-fire-filter-bar afs-fire-filter-bar--severity">
                        <span class="afs-filt-label">📊 Степень:</span>
                        <button class="afs-filt-btn afs-filt-btn--all" :class="{ 'afs-filt-btn--active': !fireSeverityFilter }" @click="fireSeverityFilter = null">Все</button>
                        <button class="afs-filt-btn afs-sev-btn--low" :class="{ 'afs-sev-btn--low-active': fireSeverityFilter === 'low' }" @click="fireSeverityFilter = fireSeverityFilter === 'low' ? null : 'low'">Слабый</button>
                        <button class="afs-filt-btn afs-sev-btn--nominal" :class="{ 'afs-sev-btn--nominal-active': fireSeverityFilter === 'nominal' }" @click="fireSeverityFilter = fireSeverityFilter === 'nominal' ? null : 'nominal'">Умеренный</button>
                        <button class="afs-filt-btn afs-sev-btn--high" :class="{ 'afs-sev-btn--high-active': fireSeverityFilter === 'high' }" @click="fireSeverityFilter = fireSeverityFilter === 'high' ? null : 'high'">Высокий</button>
                    </div>
                </div>

                <!-- Loading overlay (fires) -->
                <div v-if="mapLayer === 'fires' && loading" class="afs-map-loading">
                    <div class="afs-spinner"></div>
                    <span>Загрузка данных NASA FIRMS...</span>
                </div>

                <!-- Summary bar -->
                <div v-if="mapLayer === 'fires' && selectedOblast && !loading" class="afs-summary-bar">
                    <span v-if="selectedCountry" class="afs-summary-bar__country">
                        {{ selectedCountry.flag }} {{ selectedCountry.name }}
                    </span>
                    <span v-if="selectedCountry" class="afs-summary-bar__arrow">›</span>
                    <span class="afs-summary-bar__oblast">{{ selectedOblast.name }}</span>
                    <span v-if="selectedCity" class="afs-summary-bar__arrow">›</span>
                    <span v-if="selectedCity" class="afs-summary-bar__city">{{ selectedCity.name }}</span>
                    <span v-if="filteredHotspots.length" class="afs-summary-bar__count">
                        Обнаружено очагов: <strong>{{ filteredHotspots.length }}</strong>
                        <span v-if="hotspots.length > filteredHotspots.length" class="afs-summary-bar__filtered">
                            (из {{ hotspots.length }} за 48ч)
                        </span>
                    </span>
                    <span v-else class="afs-summary-bar__none">
                        Активных пожаров не обнаружено
                    </span>
                </div>

                <!-- Placeholder when nothing selected -->
                <div v-if="!selectedOblast && !loading" class="afs-map-hint">
                    Выберите регион на карте или в списке слева
                </div>

                <!-- Forecast info panel -->
                <div v-if="mapLayer === 'forecast' && selectedOblast && !forecastLoading" class="afs-region-panel afs-forecast-panel">
                    <div class="afs-rp__header">
                        <span class="afs-rp__title">⛈️ {{ selectedOblast.name.toUpperCase() }}</span>
                        <button class="afs-rp__close" @click="backToOblasts" title="Закрыть">×</button>
                    </div>

                    <!-- Weather summary -->
                    <div v-if="selectedForecast?.forecast?.summary" class="afs-rp__section">
                        <div class="afs-rp__section-title">Погода на 5 дней</div>
                        <div class="afs-rp__eco-row">
                            <div class="afs-rp__eco-card">
                                <div class="afs-rp__eco-label">🌡️ Темп.</div>
                                <div class="afs-rp__eco-value">{{ selectedForecast.forecast.summary.temp_min }}…{{ selectedForecast.forecast.summary.temp_max }}°C</div>
                            </div>
                            <div class="afs-rp__eco-card">
                                <div class="afs-rp__eco-label">💧 Осадки</div>
                                <div class="afs-rp__eco-value">{{ selectedForecast.forecast.summary.precip_total }} мм</div>
                            </div>
                            <div class="afs-rp__eco-card">
                                <div class="afs-rp__eco-label">💨 Ветер</div>
                                <div class="afs-rp__eco-value">{{ selectedForecast.forecast.summary.wind_max }} м/с</div>
                            </div>
                        </div>
                    </div>

                    <!-- Risks -->
                    <div class="afs-rp__section">
                        <div class="afs-rp__section-title">Угрозы для урожая</div>
                        <div v-if="!selectedForecast?.forecast?.risks?.length" class="afs-rp__fire-block">
                            <span class="afs-rp-badge afs-rp-badge--green">✅ Угроз не обнаружено</span>
                        </div>
                        <div v-else class="afs-forecast-risks">
                            <div v-for="(risk, i) in selectedForecast.forecast.risks" :key="i" class="afs-forecast-risk"
                                :class="'afs-forecast-risk--' + risk.severity">
                                <span class="afs-forecast-risk__icon">{{ RISK_LABELS[risk.type]?.icon || '⚠️' }}</span>
                                <div class="afs-forecast-risk__body">
                                    <span class="afs-forecast-risk__label">{{ RISK_LABELS[risk.type]?.label || risk.type }}</span>
                                    <span class="afs-forecast-risk__detail">{{ risk.detail }}</span>
                                    <span v-if="risk.date" class="afs-forecast-risk__date">{{ risk.date }}</span>
                                </div>
                                <span class="afs-rp-badge" :class="{
                                    'afs-rp-badge--red': risk.severity === 'high',
                                    'afs-rp-badge--orange': risk.severity === 'nominal',
                                    'afs-rp-badge--yellow': risk.severity === 'low',
                                }">{{ risk.severity === 'high' ? 'Высокий' : risk.severity === 'nominal' ? 'Средний' : 'Низкий' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Forecast loading hint -->
                    <div v-if="!selectedForecast" class="afs-rp__section">
                        <p class="afs-rp__update-text">Загрузка прогноза...</p>
                    </div>
                </div>

                <!-- Forecast loading overlay -->
                <div v-if="mapLayer === 'forecast' && forecastLoading" class="afs-map-loading">
                    <div class="afs-spinner"></div>
                    <span>Загрузка прогнозов OpenWeatherMap...</span>
                </div>

                <!-- Region info panel (fires layer) -->
                <div v-if="mapLayer === 'fires' && selectedOblast && !loading" class="afs-region-panel">

                    <div class="afs-rp__header">
                        <span class="afs-rp__title">🌿 {{ selectedOblast.name.toUpperCase() }}</span>
                        <button class="afs-rp__close" @click="backToOblasts" title="Закрыть">×</button>
                    </div>

                    <!-- Fire status -->
                    <div class="afs-rp__section">
                        <div v-if="filteredHotspots.length === 0" class="afs-rp__fire-block">
                            <span class="afs-rp-badge afs-rp-badge--green">✅ Активных пожаров нет</span>
                            <p class="afs-rp__update-text">
                                Последнее обновление: сегодня, 4 раза в сутки (NASA FIRMS)
                            </p>
                        </div>
                        <div v-else class="afs-rp__fire-block">
                            <span class="afs-rp-badge afs-rp-badge--red">🔥 Обнаружено очагов: {{ filteredHotspots.length }}</span>
                            <span :class="fireBadgeClass(filteredHotspots.length)">{{ fireRiskLabel(filteredHotspots.length) }}</span>
                            <div class="afs-rp__severity-breakdown">
                                <span class="afs-rp-badge afs-rp-badge--yellow afs-sev-mini">
                                    Слабый: {{ filteredHotspots.filter(s => getSpotSeverity(s) === 'low').length }}
                                </span>
                                <span class="afs-rp-badge afs-rp-badge--orange afs-sev-mini">
                                    Умеренный: {{ filteredHotspots.filter(s => getSpotSeverity(s) === 'nominal').length }}
                                </span>
                                <span class="afs-rp-badge afs-rp-badge--red afs-sev-mini">
                                    Высокий: {{ filteredHotspots.filter(s => getSpotSeverity(s) === 'high').length }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Eco metrics -->
                    <div class="afs-rp__section">
                        <div class="afs-rp__section-title">Экология региона</div>
                        <div class="afs-rp__eco-row">
                            <div class="afs-rp__eco-card">
                                <div class="afs-rp__eco-label">🌱 Деградация</div>
                                <div class="afs-rp__eco-value" :class="degradationClass(regionEco.degradation)">
                                    {{ regionEco.degradation }}
                                </div>
                            </div>
                            <div class="afs-rp__eco-card">
                                <div class="afs-rp__eco-label">💧 Вод. стресс</div>
                                <span :class="waterBadgeClass(regionEco.water)">{{ regionEco.water }}</span>
                            </div>
                            <div class="afs-rp__eco-card">
                                <div class="afs-rp__eco-label">☁️ CO₂</div>
                                <div class="afs-rp__eco-value">{{ regionEco.co2 }} Кт</div>
                            </div>
                        </div>
                    </div>

                    <!-- Last hotspots table -->
                    <div v-if="filteredHotspots.length > 0" class="afs-rp__section">
                        <div class="afs-rp__section-title">Последние очаги</div>
                        <table class="afs-rp__table">
                            <thead>
                                <tr>
                                    <th>Дата / Время</th>
                                    <th>Степень</th>
                                    <th>FRP</th>
                                    <th>Спутник</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(h, i) in filteredHotspots.slice(0, 5)" :key="i">
                                    <td>{{ h.acq_date }} {{ formatHotspotTime(h.acq_time) }}</td>
                                    <td><span :class="severityBadgeClass(h)" class="afs-sev-table-badge">{{ severityLabel(h) }}</span></td>
                                    <td>{{ h.frp }} МВт</td>
                                    <td>{{ h.satellite || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Subscribe save button -->
                    <button
                        v-if="subscribeMode"
                        class="afs-rp__subscribe-btn"
                        :disabled="subscribeSaving"
                        @click="saveSubscription"
                    >
                        {{ subscribeSaving ? 'Сохранение...' : '📍 Сохранить как мой регион' }}
                    </button>

                    <!-- AI button -->
                    <button class="afs-rp__ai-btn" @click="openAiWithContext">
                        Спросить ИИ-агронома →
                    </button>

                </div>
            </div>

            <!-- Crop Chat panel -->
            <div v-show="activeTab === 'crop'" class="afs-crop-panel">

                <!-- Sessions sidebar -->
                <div class="afs-sessions-sidebar">
                    <div class="afs-sessions-header">
                        <span class="afs-sessions-title">История чатов</span>
                        <button class="afs-sessions-new" @click="startNewChat" title="Новый чат">+</button>
                    </div>
                    <div class="afs-sessions-list">
                        <div v-if="sessionsLoading" class="afs-sessions-spinner">
                            <div class="afs-spinner"></div>
                        </div>
                        <div v-else-if="cropSessions.length === 0" class="afs-sessions-empty">
                            Чатов пока нет
                        </div>
                        <div v-for="s in cropSessions" :key="s.id" class="afs-session-item"
                            :class="{ 'afs-session-item--active': s.id === currentSessionId }"
                            @click="loadSession(s.id)">
                            <div class="afs-session-item__body">
                                <span class="afs-session-item__title">{{ s.title }}</span>
                                <span class="afs-session-item__date">{{ formatSessionDate(s.updated_at) }}</span>
                            </div>
                            <button class="afs-session-item__del" @click="deleteCropSession(s.id, $event)"
                                title="Удалить">✕</button>
                        </div>
                    </div>
                </div>

                <!-- Main chat area -->
                <div class="afs-crop-main">

                    <!-- Messages -->
                    <div ref="cropScrollEl" class="afs-crop-messages">
                        <div v-if="cropMessages.length === 0" class="afs-crop-empty">
                            <div class="afs-crop-empty__icon">🌾</div>
                            <div class="afs-crop-empty__title">ИИ Помощник агроному</div>
                            <div class="afs-crop-empty__hint">
                                Загрузите фото урожая или задайте вопрос —<br>
                                ИИ определит состояние и даст рекомендации
                            </div>
                        </div>

                        <div v-for="(msg, idx) in cropMessages" :key="idx" class="afs-msg"
                            :class="msg.role === 'user' ? 'afs-msg--user' : 'afs-msg--ai'">
                            <div class="afs-msg__avatar">
                                {{ msg.role === 'user' ? '👤' : '🌿' }}
                            </div>
                            <div class="afs-msg__bubble">
                                <img v-if="msg.preview" :src="msg.preview" class="afs-msg__image" alt="crop photo" />
                                <span v-if="msg.text && msg.role === 'user'" class="afs-msg__text">{{ msg.text }}</span>
                                <div v-if="msg.text && msg.role === 'ai'" class="afs-msg__text ai-message-content" v-html="md.render(msg.text)"></div>
                            </div>
                        </div>

                        <div v-if="cropLoading" class="afs-msg afs-msg--ai">
                            <div class="afs-msg__avatar">🌿</div>
                            <div class="afs-msg__bubble afs-msg__bubble--typing">
                                <span class="afs-typing-dot"></span>
                                <span class="afs-typing-dot"></span>
                                <span class="afs-typing-dot"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Image preview bar -->
                    <div v-if="cropPreview" class="afs-crop-preview-bar">
                        <img :src="cropPreview" class="afs-crop-preview-img" alt="preview" />
                        <button class="afs-crop-preview-remove" @click="clearCropImage">✕</button>
                    </div>

                    <!-- Input -->
                    <div class="afs-crop-input-row">
                        <input ref="cropFileRef" type="file" accept="image/*" class="afs-crop-file-hidden"
                            @change="onCropFile" />
                        <button class="afs-crop-attach-btn" @click="cropFileRef.click()"
                            title="Прикрепить фото из галереи">
                            📎
                        </button>
                        <button class="afs-crop-attach-btn" @click="openCamera" title="Сфотографировать">
                            📷
                        </button>
                        <textarea v-model="cropInput" class="afs-crop-textarea"
                            placeholder="Опишите проблему или задайте вопрос... (Enter — отправить)" rows="1"
                            @keydown="onCropKeydown"></textarea>
                        <button class="afs-crop-send-btn" :disabled="cropLoading || (!cropInput.trim() && !cropImage)"
                            @click="sendCrop">
                            Отправить
                        </button>
                    </div>
                </div><!-- /.afs-crop-main -->
            </div><!-- /.afs-crop-panel -->
        </div>

        <!-- Camera modal -->
        <Teleport to="body">
            <div v-if="cameraOpen" class="afs-camera-overlay" @click.self="closeCamera">
                <div class="afs-camera-modal">
                    <div class="afs-camera-header">
                        <span class="afs-camera-title">Сфотографировать урожай</span>
                        <button class="afs-camera-close" @click="closeCamera">✕</button>
                    </div>
                    <video ref="videoEl" class="afs-camera-video" autoplay playsinline muted></video>
                    <canvas ref="canvasEl" style="display:none"></canvas>
                    <div class="afs-camera-footer">
                        <button class="afs-camera-capture-btn" @click="capturePhoto">
                            <span class="afs-camera-capture-ring"></span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
/* ── Country item ────────────────────────────────────────────────────────── */
.afs-oblast-item--country {
    font-size: 0.95rem;
    font-weight: 500;
    letter-spacing: 0.01em;
}

.afs-summary-bar__country {
    color: #00bcd4;
    font-size: 0.78rem;
    font-weight: 500;
}

/* ── Fire semantic — keep red, these represent real data ─────────────────── */
.afs-oblast-item--fire {
    background: rgba(220, 38, 38, 0.06);
    border-left: 2px solid #dc2626;
}

.afs-oblast-item--fire:hover {
    background: rgba(220, 38, 38, 0.11);
}

.afs-fire-dot {
    font-size: 12px;
    margin-left: 4px;
}

/* ── Error banner ─────────────────────────────────────────────────────────── */
.afs-error-banner {
    background: #1e0a0a;
    border-bottom: 2px solid #7f1d1d;
    color: #fca5a5;
    padding: 10px 20px;
    font-size: 13px;
    font-weight: 500;
}

/* ── Workspace ────────────────────────────────────────────────────────────── */
.afs-workspace {
    display: flex;
    height: calc(100vh - 64px - 49px);
    overflow: hidden;
}

/* ── Sidebar ──────────────────────────────────────────────────────────────── */
.afs-sidebar {
    width: 260px;
    min-width: 220px;
    background: #1a1a1a;
    border-right: 2px solid #2d2d2d;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.afs-sidebar__header {
    padding: 14px 18px;
    background: #161616;
    border-bottom: 1px solid #2d2d2d;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}

.afs-sidebar__title {
    color: #e0d6cc;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.afs-sidebar__header--detail {
    gap: 8px;
}

.afs-sidebar__title--detail {
    flex: 1;
    font-size: 11px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.afs-back-btn {
    flex-shrink: 0;
    background: rgba(0, 180, 80, 0.12);
    border: 1px solid rgba(0, 180, 80, 0.35);
    color: #00e676;
    border-radius: 6px;
    width: 26px;
    height: 26px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
}

.afs-back-btn:hover {
    background: rgba(0, 180, 80, 0.22);
}

.afs-detail-hint {
    padding: 8px 18px 4px;
    color: #555;
    font-size: 11px;
    font-style: italic;
    list-style: none;
}

.afs-fire-badge {
    background: #dc2626;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
}

.afs-oblast-list {
    list-style: none;
    padding: 6px 0;
    margin: 0;
    overflow-y: auto;
    flex: 1;
    background: #1a1a1a;
}

.afs-oblast-list::-webkit-scrollbar {
    width: 4px;
}

.afs-oblast-list::-webkit-scrollbar-track {
    background: #1a1a1a;
}

.afs-oblast-list::-webkit-scrollbar-thumb {
    background: #555;
    border-radius: 2px;
}

.afs-oblast-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 18px;
    cursor: pointer;
    border-left: 3px solid transparent;
    transition: background 0.15s, border-color 0.15s;
}

.afs-oblast-item:hover {
    background: #161616;
    border-left-color: #00e676;
}

.afs-oblast-item--active {
    background: #1e2a20;
    border-left-color: #00c853;
}

.afs-oblast-item__name {
    color: #c8bfb5;
    font-size: 13px;
    font-weight: 500;
}

.afs-oblast-item--active .afs-oblast-item__name {
    color: #00e676;
    font-weight: 600;
}

.afs-oblast-item__count {
    background: #dc2626;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 12px;
}

/* ── Map panel ────────────────────────────────────────────────────────────── */
.afs-map-panel {
    flex: 1;
    position: relative;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.afs-map {
    flex: 1;
    min-height: 0;
    z-index: 1;
}

/* ── Loading overlay ──────────────────────────────────────────────────────── */
.afs-map-loading {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.65);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    z-index: 500;
    color: #e0d6cc;
    font-size: 14px;
}

.afs-spinner {
    width: 36px;
    height: 36px;
    border: 3px solid rgba(0, 180, 80, 0.25);
    border-top-color: #00c853;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ── Summary bar ──────────────────────────────────────────────────────────── */
.afs-summary-bar {
    background: #161616;
    border-top: 2px solid #00c853;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    flex-shrink: 0;
    z-index: 2;
}

.afs-summary-bar__oblast {
    color: #00e676;
    font-weight: 700;
    font-size: 14px;
}

.afs-summary-bar__arrow {
    color: #555;
    font-size: 14px;
}

.afs-summary-bar__city {
    color: #60a5fa;
    font-weight: 600;
    font-size: 13px;
}

.afs-summary-bar__count {
    color: #c8bfb5;
    font-size: 13px;
}

.afs-summary-bar__count strong {
    color: #dc2626;
    font-size: 15px;
}

.afs-summary-bar__none {
    color: #555;
    font-size: 13px;
}

/* ── Tabs ─────────────────────────────────────────────────────────────────── */
.afs-tabs {
    display: flex;
    gap: 4px;
    padding: 8px 16px;
    background: #161616;
    border-bottom: 1px solid #2d2d2d;
    flex-shrink: 0;
}

.afs-tab {
    padding: 7px 20px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #888;
    background: transparent;
    border: 1px solid transparent;
    cursor: pointer;
    transition: background 0.18s, color 0.18s, border-color 0.18s;
}

.afs-tab:hover {
    background: #1e2a20;
    color: #e0d6cc;
    border-color: #555;
}

.afs-tab--active {
    background: #1e2a20;
    color: #00e676;
    border-color: #00c853;
    font-weight: 700;
}

/* ── Map hint ─────────────────────────────────────────────────────────────── */
.afs-map-hint {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(20, 20, 20, 0.92);
    color: #c8bfb5;
    font-size: 13px;
    padding: 10px 20px;
    border-radius: 20px;
    border: 1px solid #2d2d2d;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    pointer-events: none;
    z-index: 10;
    white-space: nowrap;
}

/* ── Crop Chat panel ──────────────────────────────────────────────────────── */
.afs-crop-panel {
    flex: 1;
    display: flex;
    flex-direction: row;
    background: #161616;
    min-width: 0;
    overflow: hidden;
}

/* ── Sessions sidebar ────────────────────────────────────────────────────── */
.afs-sessions-sidebar {
    width: 220px;
    min-width: 180px;
    flex-shrink: 0;
    background: #161616;
    border-right: 1px solid #2d2d2d;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.afs-sessions-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    background: #161616;
    border-bottom: 1px solid #2d2d2d;
    flex-shrink: 0;
}

.afs-sessions-title {
    color: #888;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.afs-sessions-new {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    background: #00c853;
    border: none;
    color: #fff;
    font-size: 18px;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
}

.afs-sessions-new:hover {
    background: #00a844;
}

.afs-sessions-list {
    flex: 1;
    overflow-y: auto;
    padding: 4px 0;
}

.afs-sessions-list::-webkit-scrollbar {
    width: 3px;
}

.afs-sessions-list::-webkit-scrollbar-track {
    background: #1a1a1a;
}

.afs-sessions-list::-webkit-scrollbar-thumb {
    background: #555;
    border-radius: 2px;
}

.afs-sessions-spinner {
    display: flex;
    justify-content: center;
    padding: 24px 0;
}

.afs-sessions-empty {
    padding: 20px 14px;
    color: #555;
    font-size: 12px;
    text-align: center;
}

.afs-session-item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 9px 12px;
    cursor: pointer;
    border-left: 2px solid transparent;
    transition: background 0.14s, border-color 0.14s;
}

.afs-session-item:hover {
    background: #1e2a20;
    border-left-color: #00e676;
}

.afs-session-item--active {
    background: #1e2a20;
    border-left-color: #00c853;
}

.afs-session-item__body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.afs-session-item__title {
    color: #c8bfb5;
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
}

.afs-session-item--active .afs-session-item__title {
    color: #00e676;
    font-weight: 600;
}

.afs-session-item__date {
    color: #555;
    font-size: 10px;
}

.afs-session-item__del {
    flex-shrink: 0;
    background: transparent;
    border: none;
    color: #555;
    font-size: 11px;
    cursor: pointer;
    padding: 2px 4px;
    border-radius: 4px;
    opacity: 0;
    transition: opacity 0.14s, color 0.14s, background 0.14s;
}

.afs-session-item:hover .afs-session-item__del {
    opacity: 1;
}

.afs-session-item__del:hover {
    color: #dc2626;
    background: rgba(220, 38, 38, 0.08);
}

/* ── Crop main area ──────────────────────────────────────────────────────── */
.afs-crop-main {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #1a1a1a;
}

.afs-crop-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    background: #1a1a1a;
}

.afs-crop-messages::-webkit-scrollbar {
    width: 5px;
}

.afs-crop-messages::-webkit-scrollbar-track {
    background: #161616;
}

.afs-crop-messages::-webkit-scrollbar-thumb {
    background: #555;
    border-radius: 3px;
}

.afs-crop-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    text-align: center;
    padding: 60px 20px;
}

.afs-crop-empty__icon {
    font-size: 48px;
    margin-bottom: 4px;
}

.afs-crop-empty__title {
    font-size: 18px;
    font-weight: 700;
    color: #c8bfb5;
}

.afs-crop-empty__hint {
    font-size: 13px;
    line-height: 1.6;
    color: #555;
}

/* ── Chat messages ───────────────────────────────────────────────────────── */
.afs-msg {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    max-width: 780px;
}

.afs-msg--user {
    flex-direction: row-reverse;
    align-self: flex-end;
}

.afs-msg--ai {
    align-self: flex-start;
}

.afs-msg__avatar {
    flex-shrink: 0;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #1e2a20;
    border: 1px solid #555;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.afs-msg__bubble {
    background: #1a1a1a;
    border: 1px solid #2d2d2d;
    border-radius: 14px;
    padding: 12px 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-width: 660px;
}

.afs-msg--user .afs-msg__bubble {
    background: #1e2a20;
    border-color: #00c853;
    border-radius: 14px 4px 14px 14px;
}

.afs-msg--ai .afs-msg__bubble {
    border-radius: 4px 14px 14px 14px;
}

.afs-msg__image {
    max-width: 320px;
    max-height: 240px;
    object-fit: contain;
    border-radius: 8px;
    border: 1px solid #2d2d2d;
}

.afs-msg__text {
    color: #e0d6cc;
    font-size: 13.5px;
    line-height: 1.65;
    white-space: pre-wrap;
    word-break: break-word;
}

.afs-msg--user .afs-msg__text {
    color: #c8e6cc;
}

/* Typing animation */
.afs-msg__bubble--typing {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 5px;
    padding: 14px 18px;
}

.afs-typing-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #00c853;
    animation: typing-bounce 1.2s infinite ease-in-out;
}

.afs-typing-dot:nth-child(2) { animation-delay: 0.2s; }
.afs-typing-dot:nth-child(3) { animation-delay: 0.4s; }

@keyframes typing-bounce {
    0%, 80%, 100% { transform: scale(0.7); opacity: 0.5; }
    40%           { transform: scale(1.1); opacity: 1; }
}

/* ── Image preview bar ───────────────────────────────────────────────────── */
.afs-crop-preview-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 20px;
    background: #161616;
    border-top: 1px solid #2d2d2d;
    flex-shrink: 0;
}

.afs-crop-preview-img {
    height: 60px;
    width: auto;
    border-radius: 6px;
    border: 1px solid #555;
    object-fit: cover;
}

.afs-crop-preview-remove {
    background: #1e0a0a;
    color: #dc2626;
    border: 1px solid #7f1d1d;
    border-radius: 6px;
    width: 28px;
    height: 28px;
    font-size: 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
}

.afs-crop-preview-remove:hover {
    background: #2a1010;
}

/* ── Input row ───────────────────────────────────────────────────────────── */
.afs-crop-input-row {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    padding: 14px 20px;
    background: #161616;
    border-top: 2px solid #2d2d2d;
    flex-shrink: 0;
}

.afs-crop-file-hidden {
    display: none;
}

.afs-crop-attach-btn {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: #1e2a20;
    border: 1px solid #555;
    color: #c8bfb5;
    font-size: 18px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s, border-color 0.15s;
}

.afs-crop-attach-btn:hover {
    background: #1e2a20;
    border-color: #00c853;
}

.afs-crop-textarea {
    flex: 1;
    background: #1a1a1a;
    border: 1.5px solid #2d2d2d;
    border-radius: 10px;
    color: #e0d6cc;
    font-size: 13.5px;
    line-height: 1.5;
    padding: 10px 14px;
    resize: none;
    max-height: 120px;
    overflow-y: auto;
    outline: none;
    font-family: inherit;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.afs-crop-textarea:focus {
    border-color: #00c853;
    box-shadow: 0 0 0 3px rgba(0, 180, 80, 0.12);
}

.afs-crop-textarea::placeholder {
    color: #555;
}

.afs-crop-send-btn {
    flex-shrink: 0;
    padding: 10px 22px;
    background: #00e676;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: opacity 0.18s;
    height: 40px;
}

.afs-crop-send-btn:hover:not(:disabled) {
    opacity: 0.85;
}

.afs-crop-send-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

/* ── Camera modal ────────────────────────────────────────────────────────── */
.afs-camera-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.85);
    z-index: 9000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.afs-camera-modal {
    background: #1a1a1a;
    border: 2px solid #00e676;
    border-radius: 16px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    width: min(96vw, 640px);
    max-height: 92vh;
}

.afs-camera-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px;
    background: #0b2014;
    border-bottom: 1px solid #13301c;
    flex-shrink: 0;
}

.afs-camera-title {
    color: #fff;
    font-size: 14px;
    font-weight: 700;
}

.afs-camera-close {
    background: transparent;
    border: none;
    color: #888;
    font-size: 18px;
    cursor: pointer;
    line-height: 1;
    padding: 2px 6px;
    border-radius: 6px;
    transition: color 0.15s, background 0.15s;
}

.afs-camera-close:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.1);
}

.afs-camera-video {
    width: 100%;
    aspect-ratio: 4/3;
    object-fit: cover;
    background: #000;
    display: block;
}

.afs-camera-footer {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: #111;
    flex-shrink: 0;
}

.afs-camera-capture-btn {
    width: 68px;
    height: 68px;
    border-radius: 50%;
    background: #00e676;
    border: 4px solid #fff;
    cursor: pointer;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.12s, opacity 0.12s;
    box-shadow: 0 0 0 3px rgba(0, 230, 118, 0.4);
}

.afs-camera-capture-btn:hover {
    transform: scale(1.06);
}

.afs-camera-capture-btn:active {
    transform: scale(0.94);
    opacity: 0.85;
}

.afs-camera-capture-ring {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    border: 3px solid rgba(255, 255, 255, 0.7);
    display: block;
}

/* ── AI markdown content ──────────────────────────────────────────────────── */
.ai-message-content :deep(h1),
.ai-message-content :deep(h2),
.ai-message-content :deep(h3) {
    color: #4ade80;
    font-weight: 600;
    margin: 8px 0 4px;
}
.ai-message-content :deep(p) { margin: 4px 0; line-height: 1.6; }
.ai-message-content :deep(table) {
    border-collapse: collapse;
    width: 100%;
    margin: 8px 0;
}
.ai-message-content :deep(td),
.ai-message-content :deep(th) {
    border: 1px solid #2d4a2d;
    padding: 6px 10px;
    font-size: 13px;
}
.ai-message-content :deep(th) { background: #1a3a1a; color: #4ade80; }
.ai-message-content :deep(tr:nth-child(even)) { background: #0d1a0d; }
.ai-message-content :deep(strong) { color: #86efac; }
.ai-message-content :deep(hr) { border-color: #2d4a2d; margin: 8px 0; }
.ai-message-content :deep(ul),
.ai-message-content :deep(ol) { padding-left: 20px; margin: 4px 0; }
.ai-message-content :deep(li) { margin: 2px 0; }

/* ── Fire filters container ───────────────────────────────────────────────── */
.afs-fire-filters {
    position: absolute;
    top: 10px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 20;
    display: flex;
    flex-direction: column;
    gap: 6px;
    white-space: nowrap;
}

/* ── Fire filter bar ──────────────────────────────────────────────────────── */
.afs-fire-filter-bar {
    display: flex;
    align-items: center;
    gap: 6px;
    background: rgba(18, 18, 18, 0.92);
    border: 1px solid #2d2d2d;
    border-radius: 28px;
    padding: 6px 14px;
    box-shadow: 0 2px 16px rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(6px);
    white-space: nowrap;
}

.afs-filt-label {
    color: #888;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.3px;
    margin-right: 2px;
}

.afs-filt-btn {
    padding: 4px 12px;
    border-radius: 16px;
    font-size: 12px;
    font-weight: 700;
    background: #1e1e1e;
    border: 1px solid #3a3a3a;
    color: #888;
    cursor: pointer;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}

.afs-filt-btn:hover {
    background: #2a2a2a;
    color: #e0d6cc;
    border-color: #555;
}

.afs-filt-btn--active {
    background: rgba(220, 38, 38, 0.18);
    border-color: #dc2626;
    color: #f87171;
}

.afs-filt-slider-wrap {
    width: 110px;
    display: flex;
    align-items: center;
    padding: 0 4px;
}

.afs-filt-slider {
    width: 100%;
    -webkit-appearance: none;
    appearance: none;
    height: 4px;
    border-radius: 2px;
    background: linear-gradient(to right, #dc2626 0%, #dc2626 calc(var(--pct, 100%) * 1%), #3a3a3a calc(var(--pct, 100%) * 1%), #3a3a3a 100%);
    outline: none;
    cursor: pointer;
}

.afs-filt-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #f87171;
    border: 2px solid #fff;
    box-shadow: 0 1px 4px rgba(0,0,0,0.4);
    cursor: pointer;
}

.afs-filt-slider::-moz-range-thumb {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #f87171;
    border: 2px solid #fff;
    cursor: pointer;
}

.afs-filt-value {
    color: #f87171;
    font-size: 12px;
    font-weight: 700;
    min-width: 28px;
    text-align: right;
}

/* ── Severity filter buttons ──────────────────────────────────────────────── */
.afs-sev-btn--low-active {
    background: rgba(250, 204, 21, 0.18) !important;
    border-color: #facc15 !important;
    color: #facc15 !important;
}
.afs-sev-btn--nominal-active {
    background: rgba(251, 146, 60, 0.18) !important;
    border-color: #fb923c !important;
    color: #fb923c !important;
}
.afs-sev-btn--high-active {
    background: rgba(239, 68, 68, 0.18) !important;
    border-color: #ef4444 !important;
    color: #ef4444 !important;
}

.afs-rp__severity-breakdown {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    width: 100%;
    margin-top: 6px;
}

.afs-sev-mini {
    font-size: 10px !important;
    padding: 2px 7px !important;
}

.afs-sev-table-badge {
    font-size: 10px !important;
    padding: 1px 6px !important;
}

.afs-summary-bar__filtered {
    color: #555;
    font-size: 11px;
    font-weight: 400;
}

/* ── Region info panel ────────────────────────────────────────────────────── */
.afs-region-panel {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 300px;
    max-height: calc(100% - 80px);
    background: #1a1a1a;
    border: 1px solid #2d2d2d;
    border-top: 2px solid #4ade80;
    border-radius: 12px;
    overflow-y: auto;
    z-index: 20;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.5);
}

.afs-region-panel::-webkit-scrollbar { width: 3px; }
.afs-region-panel::-webkit-scrollbar-track { background: #1a1a1a; }
.afs-region-panel::-webkit-scrollbar-thumb { background: #3a3a3a; border-radius: 2px; }

.afs-rp__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    border-bottom: 1px solid #2d2d2d;
    flex-shrink: 0;
}

.afs-rp__title {
    font-size: 11px;
    font-weight: 700;
    color: #4ade80;
    letter-spacing: 0.6px;
}

.afs-rp__close {
    background: transparent;
    border: none;
    color: #888;
    font-size: 18px;
    line-height: 1;
    cursor: pointer;
    padding: 0 2px;
    transition: color 0.15s;
}
.afs-rp__close:hover { color: #fff; }

.afs-rp__section {
    padding: 12px 14px;
    border-bottom: 1px solid #222;
}
.afs-rp__section:last-of-type { border-bottom: none; }

.afs-rp__section-title {
    font-size: 10px;
    font-weight: 700;
    color: #555;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

/* ── Fire block ── */
.afs-rp__fire-block {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.afs-rp__update-text {
    font-size: 11px;
    color: #555;
    margin-top: 6px;
    line-height: 1.5;
    width: 100%;
}

/* ── Badges ── */
.afs-rp-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}
.afs-rp-badge--green  { background: rgba(74,222,128,0.15);  color: #4ade80; border: 1px solid rgba(74,222,128,0.35); }
.afs-rp-badge--yellow { background: rgba(202,138,4,0.15);   color: #facc15; border: 1px solid rgba(202,138,4,0.35); }
.afs-rp-badge--orange { background: rgba(234,88,12,0.15);   color: #fb923c; border: 1px solid rgba(234,88,12,0.35); }
.afs-rp-badge--red    { background: rgba(220,38,38,0.15);   color: #f87171; border: 1px solid rgba(220,38,38,0.35); }

/* ── Eco row ── */
.afs-rp__eco-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
}

.afs-rp__eco-card {
    background: #111;
    border: 1px solid #2d2d2d;
    border-radius: 8px;
    padding: 8px 10px;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.afs-rp__eco-label {
    font-size: 10px;
    color: #555;
    font-weight: 600;
}

.afs-rp__eco-value {
    font-size: 15px;
    font-weight: 700;
    color: #c8bfb5;
}
.afs-rp__val--red    { color: #f87171; }
.afs-rp__val--orange { color: #fb923c; }
.afs-rp__val--green  { color: #4ade80; }

/* ── Hotspots table ── */
.afs-rp__table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
}

.afs-rp__table thead tr { background: #111; }

.afs-rp__table th {
    padding: 6px 8px;
    text-align: left;
    color: #555;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    border-bottom: 1px solid #2d2d2d;
}

.afs-rp__table tbody tr {
    border-bottom: 1px solid #222;
    transition: background 0.1s;
}
.afs-rp__table tbody tr:hover { background: #1e2a20; }

.afs-rp__table td {
    padding: 6px 8px;
    color: #c8bfb5;
}

/* ── Map layer toggle ── */
.afs-layer-toggle {
    display: flex;
    gap: 2px;
    margin-left: auto;
    background: #111;
    border-radius: 8px;
    padding: 2px;
    border: 1px solid #2d2d2d;
}
.afs-layer-btn {
    padding: 5px 14px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    background: transparent;
    border: none;
    color: #888;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
    white-space: nowrap;
}
.afs-layer-btn:hover { background: #1e2a20; color: #c8bfb5; }
.afs-layer-btn--active { background: #1e2a20; color: #00e676; }

/* ── Forecast panel ── */
.afs-forecast-panel { border-top-color: #00bcd4 !important; }

.afs-forecast-risks {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.afs-forecast-risk {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    border-radius: 8px;
    border: 1px solid #2d2d2d;
    background: #111;
}
.afs-forecast-risk--high    { border-color: rgba(239,68,68,0.4); background: rgba(239,68,68,0.06); }
.afs-forecast-risk--nominal { border-color: rgba(245,158,11,0.4); background: rgba(245,158,11,0.06); }

.afs-forecast-risk__icon { font-size: 20px; flex-shrink: 0; }

.afs-forecast-risk__body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 1px;
}
.afs-forecast-risk__label { font-size: 12px; font-weight: 700; color: #e0d6cc; }
.afs-forecast-risk__detail { font-size: 11px; color: #888; }
.afs-forecast-risk__date { font-size: 10px; color: #555; }

/* ── Subscribe save button ── */
.afs-rp__subscribe-btn {
    margin: 12px 14px 0;
    padding: 10px 14px;
    background: #00bcd4;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.18s;
    width: calc(100% - 28px);
    flex-shrink: 0;
}
.afs-rp__subscribe-btn:hover:not(:disabled) { background: #00acc1; }
.afs-rp__subscribe-btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* ── Subscribe banner ── */
.afs-subscribe-banner {
    background: rgba(0, 188, 212, 0.12);
    border-bottom: 2px solid #00bcd4;
    color: #00e5ff;
    padding: 10px 20px;
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-shrink: 0;
    z-index: 3;
}
.afs-subscribe-banner__cancel {
    background: transparent;
    border: 1px solid rgba(0, 188, 212, 0.4);
    color: #00e5ff;
    padding: 4px 14px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
}
.afs-subscribe-banner__cancel:hover { background: rgba(0, 188, 212, 0.15); }

/* ── Return to saved zone button ── */
.afs-return-zone-btn {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 20;
    background: rgba(18, 18, 18, 0.92);
    border: 1px solid #00bcd4;
    color: #00e5ff;
    padding: 8px 18px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    backdrop-filter: blur(6px);
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.4);
    transition: background 0.15s, border-color 0.15s;
    white-space: nowrap;
}
.afs-return-zone-btn:hover {
    background: rgba(0, 188, 212, 0.15);
    border-color: #00e5ff;
}

/* ── AI button ── */
.afs-rp__ai-btn {
    margin: 12px 14px;
    padding: 9px 14px;
    background: #4ade80;
    color: #000;
    font-size: 12px;
    font-weight: 700;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.18s;
    width: calc(100% - 28px);
    flex-shrink: 0;
}
.afs-rp__ai-btn:hover { background: #22c55e; }

</style>

<!-- ── Light theme overrides (non-scoped, wins over scoped via html[] specificity) ── -->
<style>
.afs-country-tooltip {
    background: rgba(18, 18, 18, 0.92);
    color: #e0d6cc;
    border: 1px solid #2d2d2d;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    padding: 4px 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.4);
}
.afs-country-tooltip::before {
    border-right-color: rgba(18, 18, 18, 0.92) !important;
}
html[data-theme="light"] .afs-country-tooltip {
    background: rgba(255, 255, 255, 0.95);
    color: #1a2e1d;
    border-color: #c4ddc8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
html[data-theme="light"] .afs-country-tooltip::before {
    border-right-color: rgba(255, 255, 255, 0.95) !important;
}
</style>

<style>
html[data-theme="light"] .afs-error-banner         { background: #fff0f0; border-bottom-color: #fca5a5; color: #991b1b; }

html[data-theme="light"] .afs-tabs                 { background: #ffffff; border-bottom-color: #c4ddc8; }
html[data-theme="light"] .afs-tab                  { color: #6b8570; }
html[data-theme="light"] .afs-tab:hover            { background: #e6f4ea; color: #1a2e1d; border-color: #c4ddc8; }
html[data-theme="light"] .afs-tab--active          { background: #e6f4ea; color: #007a3a; border-color: #00a550; }

html[data-theme="light"] .afs-sidebar              { background: #f8faf8; border-right-color: #c4ddc8; }
html[data-theme="light"] .afs-sidebar__header      { background: #ffffff; border-bottom-color: #c4ddc8; }
html[data-theme="light"] .afs-sidebar__title       { color: #3d5c42; }
html[data-theme="light"] .afs-back-btn             { background: rgba(0,165,80,0.08); border-color: rgba(0,165,80,0.3); color: #007a3a; }
html[data-theme="light"] .afs-back-btn:hover       { background: rgba(0,165,80,0.16); }
html[data-theme="light"] .afs-detail-hint          { color: #9ab0a0; }

html[data-theme="light"] .afs-oblast-list          { background: #f8faf8; }
html[data-theme="light"] .afs-oblast-list::-webkit-scrollbar-track { background: #f8faf8; }
html[data-theme="light"] .afs-oblast-list::-webkit-scrollbar-thumb { background: #c4ddc8; }
html[data-theme="light"] .afs-oblast-item:hover    { background: #e6f4ea; border-left-color: #00a550; }
html[data-theme="light"] .afs-oblast-item--active  { background: #d4edda; border-left-color: #007a3a; }
html[data-theme="light"] .afs-oblast-item__name    { color: #1a2e1d; }
html[data-theme="light"] .afs-oblast-item--active .afs-oblast-item__name { color: #007a3a; }

html[data-theme="light"] .afs-summary-bar          { background: #ffffff; border-top-color: #00a550; }
html[data-theme="light"] .afs-summary-bar__oblast  { color: #007a3a; }
html[data-theme="light"] .afs-summary-bar__arrow   { color: #9ab0a0; }
html[data-theme="light"] .afs-summary-bar__city    { color: #2563eb; }
html[data-theme="light"] .afs-summary-bar__count   { color: #3d5c42; }
html[data-theme="light"] .afs-summary-bar__none    { color: #9ab0a0; }

html[data-theme="light"] .afs-map-hint             { background: rgba(255,255,255,0.94); color: #3d5c42; border-color: #c4ddc8; }
html[data-theme="light"] .afs-map-loading          { background: rgba(255,255,255,0.75); color: #1a2e1d; }

html[data-theme="light"] .afs-region-panel         { background: #ffffff; border-color: #c4ddc8; border-top-color: #00a550; box-shadow: 0 4px 24px rgba(0,0,0,0.12); }
html[data-theme="light"] .afs-region-panel::-webkit-scrollbar-track { background: #fff; }
html[data-theme="light"] .afs-region-panel::-webkit-scrollbar-thumb { background: #c4ddc8; }
html[data-theme="light"] .afs-rp__header           { border-bottom-color: #c4ddc8; }
html[data-theme="light"] .afs-rp__title            { color: #007a3a; }
html[data-theme="light"] .afs-rp__close            { color: #9ab0a0; }
html[data-theme="light"] .afs-rp__close:hover      { color: #1a2e1d; }
html[data-theme="light"] .afs-rp__section          { border-bottom-color: #eaf3eb; }
html[data-theme="light"] .afs-rp__section-title    { color: #9ab0a0; }
html[data-theme="light"] .afs-rp__update-text      { color: #9ab0a0; }
html[data-theme="light"] .afs-rp__eco-card         { background: #f4f7f4; border-color: #c4ddc8; }
html[data-theme="light"] .afs-rp__eco-label        { color: #9ab0a0; }
html[data-theme="light"] .afs-rp__eco-value        { color: #1a2e1d; }
html[data-theme="light"] .afs-rp__table thead tr   { background: #e6f4ea; }
html[data-theme="light"] .afs-rp__table th         { color: #007a3a; border-color: #c4ddc8; }
html[data-theme="light"] .afs-rp__table td         { color: #3d5c42; border-color: #eaf3eb; }
html[data-theme="light"] .afs-rp__table tr:nth-child(even) { background: #f4f7f4; }

html[data-theme="light"] .afs-crop-panel           { background: #f2f6f2; }
html[data-theme="light"] .afs-sessions-sidebar     { background: #ffffff; border-right-color: #c4ddc8; }
html[data-theme="light"] .afs-sessions-header      { background: #ffffff; border-bottom-color: #c4ddc8; }
html[data-theme="light"] .afs-sessions-title       { color: #9ab0a0; }
html[data-theme="light"] .afs-sessions-empty       { color: #9ab0a0; }
html[data-theme="light"] .afs-session-item         { border-bottom-color: #eaf3eb; }
html[data-theme="light"] .afs-session-item:hover   { background: #e6f4ea; }
html[data-theme="light"] .afs-session-item--active { background: #d4edda; }
html[data-theme="light"] .afs-session-item__title  { color: #1a2e1d; }
html[data-theme="light"] .afs-session-item__date   { color: #9ab0a0; }
html[data-theme="light"] .afs-session-item__del    { color: #9ab0a0; }
html[data-theme="light"] .afs-session-item__del:hover { color: #dc2626; }

html[data-theme="light"] .afs-crop-main            { background: #f8faf8; }
html[data-theme="light"] .afs-crop-messages        { background: #f8faf8; }
html[data-theme="light"] .afs-crop-empty__title    { color: #1a2e1d; }
html[data-theme="light"] .afs-crop-empty__hint     { color: #6b8570; }

html[data-theme="light"] .afs-msg--user .afs-msg__bubble { background: #d4edda; color: #1a2e1d; border-color: #c4ddc8; }
html[data-theme="light"] .afs-msg--ai  .afs-msg__bubble  { background: #ffffff; color: #1a2e1d; border-color: #c4ddc8; }

html[data-theme="light"] .afs-crop-input-row       { background: #ffffff; border-top-color: #c4ddc8; }
html[data-theme="light"] .afs-crop-textarea        { background: #f8faf8; color: #1a2e1d; border-color: #c4ddc8; }
html[data-theme="light"] .afs-crop-textarea::placeholder { color: #9ab0a0; }
html[data-theme="light"] .afs-crop-attach-btn      { background: #e6f4ea; color: #007a3a; border-color: #c4ddc8; }
html[data-theme="light"] .afs-crop-attach-btn:hover { background: #c8e6c9; }

html[data-theme="light"] .afs-crop-preview-bar     { background: #ffffff; border-top-color: #c4ddc8; }

html[data-theme="light"] .afs-camera-modal         { background: #ffffff; }
html[data-theme="light"] .afs-camera-header        { background: #f8faf8; border-bottom-color: #c4ddc8; }
html[data-theme="light"] .afs-camera-title         { color: #1a2e1d; }
html[data-theme="light"] .afs-camera-close         { color: #6b8570; }
html[data-theme="light"] .afs-camera-footer        { background: #f2f6f2; }

html[data-theme="light"] .ai-message-content :deep(h1),
html[data-theme="light"] .ai-message-content :deep(h2),
html[data-theme="light"] .ai-message-content :deep(h3) { color: #007a3a; }
html[data-theme="light"] .ai-message-content :deep(td),
html[data-theme="light"] .ai-message-content :deep(th) { border-color: #c4ddc8; }
html[data-theme="light"] .ai-message-content :deep(th) { background: #e6f4ea; color: #007a3a; }
html[data-theme="light"] .ai-message-content :deep(tr:nth-child(even)) { background: #f4f7f4; }
html[data-theme="light"] .ai-message-content :deep(strong) { color: #007a3a; }
html[data-theme="light"] .ai-message-content :deep(hr)     { border-color: #c4ddc8; }

html[data-theme="light"] .afs-fire-filter-bar { background: rgba(255,255,255,0.94); border-color: #c4ddc8; box-shadow: 0 2px 16px rgba(0,0,0,0.10); }
html[data-theme="light"] .afs-filt-label      { color: #6b8570; }
html[data-theme="light"] .afs-filt-btn        { background: #f4f7f4; border-color: #c4ddc8; color: #6b8570; }
html[data-theme="light"] .afs-filt-btn:hover  { background: #e6f4ea; color: #1a2e1d; }
html[data-theme="light"] .afs-filt-btn--active { background: rgba(220,38,38,0.10); border-color: #dc2626; color: #dc2626; }
html[data-theme="light"] .afs-sev-btn--low-active     { background: rgba(202,138,4,0.12) !important; border-color: #a16207 !important; color: #a16207 !important; }
html[data-theme="light"] .afs-sev-btn--nominal-active  { background: rgba(234,88,12,0.12) !important; border-color: #c2410c !important; color: #c2410c !important; }
html[data-theme="light"] .afs-sev-btn--high-active     { background: rgba(220,38,38,0.12) !important; border-color: #dc2626 !important; color: #dc2626 !important; }
html[data-theme="light"] .afs-layer-toggle              { background: #f4f7f4; border-color: #c4ddc8; }
html[data-theme="light"] .afs-layer-btn                { color: #6b8570; }
html[data-theme="light"] .afs-layer-btn:hover          { background: #e6f4ea; color: #1a2e1d; }
html[data-theme="light"] .afs-layer-btn--active        { background: #e6f4ea; color: #007a3a; }
html[data-theme="light"] .afs-forecast-panel           { border-top-color: #0097a7 !important; }
html[data-theme="light"] .afs-forecast-risk            { background: #f4f7f4; border-color: #c4ddc8; }
html[data-theme="light"] .afs-forecast-risk--high      { border-color: rgba(220,38,38,0.3); background: rgba(220,38,38,0.04); }
html[data-theme="light"] .afs-forecast-risk--nominal   { border-color: rgba(202,138,4,0.3); background: rgba(202,138,4,0.04); }
html[data-theme="light"] .afs-forecast-risk__label     { color: #1a2e1d; }
html[data-theme="light"] .afs-forecast-risk__detail    { color: #6b8570; }
html[data-theme="light"] .afs-subscribe-banner         { background: rgba(0,188,212,0.08); border-bottom-color: #0097a7; color: #00838f; }
html[data-theme="light"] .afs-subscribe-banner__cancel { border-color: rgba(0,188,212,0.3); color: #00838f; }
html[data-theme="light"] .afs-return-zone-btn          { background: rgba(255,255,255,0.94); border-color: #0097a7; color: #00838f; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
html[data-theme="light"] .afs-return-zone-btn:hover    { background: rgba(0,188,212,0.08); }
html[data-theme="light"] .afs-rp__subscribe-btn        { background: #0097a7; }
html[data-theme="light"] .afs-rp__subscribe-btn:hover:not(:disabled) { background: #00838f; }
</style>
