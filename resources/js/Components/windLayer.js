// Анимация ветра частицами вокруг поля (в духе windy.com).
// Ветер у нас известен в одной точке — по прогнозу для координат поля, — поэтому поток однородный:
// направление и скорость частиц настоящие, но это не карта ветра по всей территории.
import L from 'leaflet'

const PANE = 'afsWindPane'

export function createWindLayer(map) {
    if (!map.getPane(PANE)) {
        const pane = map.createPane(PANE)
        pane.style.zIndex = 390 // под линиями и маркерами полей (overlayPane — 400)
        pane.style.pointerEvents = 'none'
    }

    const canvas = L.DomUtil.create('canvas', 'afs-wind-canvas', map.getPane(PANE))
    canvas.style.position = 'absolute'
    const ctx = canvas.getContext('2d')
    const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

    let state = null      // { lat, lon, radiusKm, speed, deg }
    let particles = []
    let frame = null
    let origin = L.point(0, 0)
    let center = null
    let radiusPx = 0
    let velocity = { x: 0, y: 0 }

    function resize() {
        const size = map.getSize()
        const dpr = window.devicePixelRatio || 1
        canvas.width = size.x * dpr
        canvas.height = size.y * dpr
        canvas.style.width = size.x + 'px'
        canvas.style.height = size.y + 'px'
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
        origin = map.containerPointToLayerPoint([0, 0])
        L.DomUtil.setPosition(canvas, origin)
    }

    function geometry() {
        center = map.latLngToLayerPoint([state.lat, state.lon])
        const edge = map.latLngToLayerPoint([state.lat + state.radiusKm / 111, state.lon])
        radiusPx = Math.abs(center.y - edge.y)

        // Метео-направление — откуда дует; частицы летят туда, куда дует
        const to = ((state.deg + 180) % 360) * Math.PI / 180
        // Экранная скорость — наглядная, пропорциональна ветру, а не буквальная
        const px = 0.6 + state.speed * 0.35
        velocity = { x: Math.sin(to) * px, y: -Math.cos(to) * px }
    }

    function spawn(anywhere) {
        // Новые частицы рождаются по всему кругу, со смещением к наветренной стороне
        const a = Math.random() * Math.PI * 2
        const r = Math.sqrt(Math.random()) * radiusPx
        let x = center.x + Math.cos(a) * r
        let y = center.y + Math.sin(a) * r
        if (!anywhere) {
            const v = Math.hypot(velocity.x, velocity.y) || 1
            x -= (velocity.x / v) * radiusPx * 0.4 * Math.random()
            y -= (velocity.y / v) * radiusPx * 0.4 * Math.random()
        }
        return { x, y, age: 0, life: 60 + Math.random() * 90, jitter: (Math.random() - 0.5) * 0.25 }
    }

    function reset() {
        if (!state) return
        resize()
        geometry()
        const count = Math.round(Math.min(700, 120 + state.speed * 45) * Math.min(1, radiusPx / 220 + 0.25))
        particles = Array.from({ length: count }, () => spawn(true))
        ctx.clearRect(0, 0, canvas.width, canvas.height)
    }

    function clip() {
        ctx.beginPath()
        ctx.arc(center.x - origin.x, center.y - origin.y, radiusPx, 0, Math.PI * 2)
        ctx.clip()
    }

    function color() {
        const light = document.documentElement.dataset.theme === 'light'
        if (state.speed >= 10) return light ? 'rgba(234,88,12,0.85)' : 'rgba(251,146,60,0.9)'
        return light ? 'rgba(2,132,199,0.85)' : 'rgba(125,211,252,0.9)'
    }

    function tick() {
        frame = requestAnimationFrame(tick)

        // Шлейфы: чуть стираем прошлый кадр вместо полной очистки
        ctx.save()
        ctx.globalCompositeOperation = 'destination-out'
        ctx.fillStyle = 'rgba(0,0,0,0.09)'
        ctx.fillRect(0, 0, canvas.width, canvas.height)
        ctx.restore()

        ctx.save()
        clip()
        ctx.strokeStyle = color()
        ctx.lineWidth = 1.6
        ctx.lineCap = 'round'
        ctx.beginPath()
        for (let i = 0; i < particles.length; i++) {
            const p = particles[i]
            const nx = p.x + velocity.x + velocity.y * p.jitter
            const ny = p.y + velocity.y - velocity.x * p.jitter
            ctx.moveTo(p.x - origin.x, p.y - origin.y)
            ctx.lineTo(nx - origin.x, ny - origin.y)
            p.x = nx
            p.y = ny
            p.age++
            if (p.age > p.life || Math.hypot(p.x - center.x, p.y - center.y) > radiusPx) {
                particles[i] = spawn(false)
            }
        }
        ctx.stroke()
        ctx.restore()
    }

    // Статичная картинка для тех, кто отключил анимацию в системе
    function drawStatic() {
        ctx.clearRect(0, 0, canvas.width, canvas.height)
        ctx.save()
        clip()
        ctx.strokeStyle = color()
        ctx.lineWidth = 2
        const v = Math.hypot(velocity.x, velocity.y) || 1
        const ux = velocity.x / v, uy = velocity.y / v
        for (let gx = -radiusPx; gx <= radiusPx; gx += 40) {
            for (let gy = -radiusPx; gy <= radiusPx; gy += 40) {
                const x = center.x - origin.x + gx, y = center.y - origin.y + gy
                ctx.beginPath()
                ctx.moveTo(x - ux * 12, y - uy * 12)
                ctx.lineTo(x + ux * 12, y + uy * 12)
                ctx.lineTo(x + ux * 6 - uy * 5, y + uy * 6 + ux * 5)
                ctx.stroke()
            }
        }
        ctx.restore()
    }

    function start() {
        cancelAnimationFrame(frame)
        frame = null
        reset()
        canvas.style.display = ''
        if (reduceMotion) drawStatic()
        else tick()
    }

    function onZoomStart() {
        cancelAnimationFrame(frame)
        frame = null
        canvas.style.display = 'none'
    }

    function onViewChanged() {
        if (state) start()
    }

    map.on('zoomstart', onZoomStart)
    map.on('zoomend moveend resize', onViewChanged)

    return {
        show(lat, lon, radiusKm, speed, deg) {
            if (deg === null || deg === undefined || speed < 0.3) return this.hide()
            state = { lat, lon, radiusKm, speed, deg }
            start()
        },
        hide() {
            state = null
            cancelAnimationFrame(frame)
            frame = null
            ctx.clearRect(0, 0, canvas.width, canvas.height)
            canvas.style.display = 'none'
        },
        destroy() {
            this.hide()
            map.off('zoomstart', onZoomStart)
            map.off('zoomend moveend resize', onViewChanged)
            canvas.remove()
        },
    }
}

// Компас на поле: стрелка «куда дует» и скорость, стрелка мягко «дышит» по ветру
export function windBadge(speed, deg, from) {
    const to = (deg + 180) % 360
    return L.divIcon({
        className: 'afs-wind-badge',
        iconSize: [0, 0],
        html: `
            <div class="afs-wind-badge__arrow" style="--wind-rot:${to}deg">
                <svg viewBox="0 0 40 40" width="40" height="40" aria-hidden="true">
                    <circle cx="20" cy="20" r="18" />
                    <path d="M20 7 L27 23 L20 19 L13 23 Z" />
                </svg>
            </div>
            <div class="afs-wind-badge__text">${speed} м/с${from ? ' · с ' + from : ''}</div>`,
    })
}
