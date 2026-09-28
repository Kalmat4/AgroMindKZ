<script setup>
import { ref, watch, nextTick, onBeforeUnmount } from 'vue'
import L from 'leaflet'

const props = defineProps({ modelValue: { type: Object, default: null }, disabled: Boolean })
const emit = defineEmits(['update:modelValue'])
const expanded = ref(false)
const mapEl = ref(null)
const lat = ref('')
const lon = ref('')
const crop = ref('')
const stage = ref('')
const irrigation = ref('unknown')
const error = ref('')
let map, marker

watch(() => props.modelValue, value => {
    lat.value = value?.lat ?? ''
    lon.value = value?.lon ?? ''
    crop.value = value?.crop ?? ''
    stage.value = value?.growth_stage ?? ''
    irrigation.value = value?.irrigation ?? 'unknown'
    if (value && map) showPoint(value.lat, value.lon)
    else if (marker) { marker.remove(); marker = null }
}, { immediate: true })

function showPoint(latitude, longitude) {
    if (marker) marker.setLatLng([latitude, longitude])
    else marker = L.circleMarker([latitude, longitude], { radius: 8, color: '#118b45' }).addTo(map)
    map.setView([latitude, longitude], Math.max(map.getZoom(), 10))
}

watch(expanded, async open => {
    if (!open) return
    await nextTick()
    if (!map) {
        map = L.map(mapEl.value).setView([48, 67], 5)
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
        }).addTo(map)
        map.on('click', event => {
            if (props.disabled) return
            lat.value = Number(event.latlng.lat.toFixed(6))
            lon.value = Number(event.latlng.wrap().lng.toFixed(6))
            showPoint(lat.value, lon.value)
        })
    }
    map.invalidateSize()
    if (props.modelValue) showPoint(props.modelValue.lat, props.modelValue.lon)
})

function save() {
    if (props.disabled) return
    const latitude = Number(lat.value), longitude = Number(lon.value)
    if (lat.value === '' || lon.value === '' || !Number.isFinite(latitude) || !Number.isFinite(longitude)
        || Math.abs(latitude) > 90 || Math.abs(longitude) > 180) {
        error.value = 'Укажите широту от −90 до 90 и долготу от −180 до 180.'
        return
    }
    emit('update:modelValue', { lat: latitude, lon: longitude, crop: crop.value.trim(), growth_stage: stage.value.trim(), irrigation: irrigation.value })
    error.value = ''
    expanded.value = false
}
function clear() {
    if (props.disabled) return
    emit('update:modelValue', null)
    expanded.value = false
}
onBeforeUnmount(() => map?.remove())
</script>

<template>
    <section class="agro-field">
        <button type="button" :disabled="disabled" @click="expanded = !expanded" :aria-expanded="expanded">
            {{ modelValue ? `📍 ${Number(modelValue.lat).toFixed(5)}, ${Number(modelValue.lon).toFixed(5)} · ${modelValue.crop || 'Культура не указана'}` : '📍 Выбрать участок для помощника' }}
        </button>
        <p v-if="!modelValue">Без точки помощник использует только выбранный регион, если он задан.</p>
        <div v-show="expanded">
            <p>Нажмите на карту или введите координаты поля и сохраните выбор.</p>
            <div ref="mapEl" class="agro-field-map" aria-label="Карта выбора точки участка"></div>
            <fieldset :disabled="disabled" class="agro-field-inputs">
                <label>Широта <input v-model="lat" type="number" step="any" min="-90" max="90" /></label>
                <label>Долгота <input v-model="lon" type="number" step="any" min="-180" max="180" /></label>
                <label>Культура <input v-model="crop" maxlength="100" placeholder="Например, пшеница" /></label>
                <label>Фаза роста <input v-model="stage" maxlength="100" placeholder="Если известна" /></label>
                <label>Орошение <select v-model="irrigation"><option value="unknown">Не указано</option><option value="rainfed">Богара</option><option value="irrigated">Орошаемое</option></select></label>
                <button type="button" @click="save">Сохранить участок</button>
                <button v-if="modelValue" type="button" @click="clear">Убрать участок</button>
            </fieldset>
            <p v-if="error" role="alert">{{ error }}</p>
        </div>
    </section>
</template>

<style scoped>
.agro-field { padding: 12px 16px; border-bottom: 1px solid #72977b; max-height: 50vh; overflow: auto; }
.agro-field p { font-size: 12px; margin: 6px 0; }
.agro-field button { cursor: pointer; padding: 6px; border: 1px solid #72977b; border-radius: 6px; }
.agro-field-map { height: 220px; margin: 8px 0; z-index: 0; }
.agro-field-inputs { display: flex; flex-wrap: wrap; gap: 8px; border: 0; }
.agro-field-inputs label { display: flex; flex-direction: column; font-size: 12px; flex: 1 1 140px; }
.agro-field-inputs input, .agro-field-inputs select { color: #173a24; background: #fff; border: 1px solid #72977b; border-radius: 4px; padding: 6px; width: 100%; }
</style>
