<script setup>
import { Head, Link } from '@inertiajs/vue3'
</script>

<template>
    <Head title="О проекте — AgroMind KZ" />

    <div class="about-root">

        <!-- Hero -->
        <section class="about-hero">
            <div class="about-hero__logo">🌿</div>
            <h1 class="about-hero__title">AgroMind KZ</h1>
            <p class="about-hero__subtitle">
                Интеллектуальная платформа экологического мониторинга Центральной Азии
            </p>
            <Link href="/login" class="about-hero__cta">Войти в платформу</Link>
        </section>

        <!-- Stats row -->
        <section class="about-stats">
            <div class="about-inner">
                <div class="about-stat">
                    <div class="about-stat__value">5</div>
                    <div class="about-stat__label">стран ЦА — охват</div>
                </div>
                <div class="about-stat">
                    <div class="about-stat__value">4×</div>
                    <div class="about-stat__label">обновление данных в сутки</div>
                </div>
                <div class="about-stat">
                    <div class="about-stat__value">$0</div>
                    <div class="about-stat__label">стоимость NASA FIRMS API</div>
                </div>
                <div class="about-stat">
                    <div class="about-stat__value">~$25</div>
                    <div class="about-stat__label">общие затраты в месяц</div>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section class="about-section">
            <div class="about-inner">
                <h2 class="about-section__title">Что умеет платформа</h2>
                <div class="about-feature-grid">
                    <div class="about-feature-card">
                        <div class="about-feature-card__icon">🔥</div>
                        <div class="about-feature-card__title">Мониторинг пожаров</div>
                        <div class="about-feature-card__text">
                            Спутники VIIRS/MODIS, обновление каждые 6 часов
                        </div>
                    </div>
                    <div class="about-feature-card">
                        <div class="about-feature-card__icon">🌿</div>
                        <div class="about-feature-card__title">ИИ-агроном</div>
                        <div class="about-feature-card__text">
                            Анализ фото урожая, диагностика болезней, рекомендации
                        </div>
                    </div>
                    <div class="about-feature-card">
                        <div class="about-feature-card__icon">📊</div>
                        <div class="about-feature-card__title">Прозрачность АПК</div>
                        <div class="about-feature-card__text">
                            Мониторинг субсидий, аномалии урожайности
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Tech stack -->
        <section class="about-section about-section--alt">
            <div class="about-inner">
                <h2 class="about-section__title">Технологии</h2>
                <div class="about-tech-list">
                    <span class="about-tech-tag">Laravel 13</span>
                    <span class="about-tech-tag">Vue.js 3</span>
                    <span class="about-tech-tag">n8n</span>
                    <span class="about-tech-tag">NASA FIRMS API</span>
                    <span class="about-tech-tag">Claude Haiku AI</span>
                    <span class="about-tech-tag">MySQL 8</span>
                </div>
            </div>
        </section>

        <!-- NASA section -->
        <section class="about-section">
            <div class="about-inner">
                <h2 class="about-section__title">NASA FIRMS — ответ скептикам</h2>
                <div class="about-nasa-card">
                    <div class="about-nasa-card__header">Это бесплатно и работает</div>
                    <p class="about-nasa-card__text">
                        NASA FIRMS — государственный сервис США с открытым доступом.
                        Регистрация бесплатна. Лимит: 5000 запросов / 10 минут.<br>
                        Спутники VIIRS и MODIS дают 4 снимка в сутки над Казахстаном.
                        Данные доступны через 3–6 часов после пролёта.
                    </p>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="about-footer">
            AgroMind KZ · Green &amp; Digital Solutions for Central Asia · 2026
        </footer>

    </div>
</template>

<style scoped>
*,
*::before,
*::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

.about-root {
    background: #060d08;
    color: #c8e6cc;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* ── Inner container ──────────────────────────────────────────────────────── */
.about-inner {
    max-width: 1100px;
    margin: 0 auto;
    width: 100%;
    padding: 0 32px;
}

/* ── Hero ─────────────────────────────────────────────────────────────────── */
.about-hero {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 80px 32px 72px;
    background: linear-gradient(160deg, #060d08 0%, #0b2014 40%, #060d08 100%);
    border-bottom: 1px solid #0f2d1a;
}

.about-hero__logo {
    font-size: 64px;
    margin-bottom: 20px;
    filter: drop-shadow(0 0 28px rgba(0, 230, 118, 0.8));
    animation: pulse-glow 3s ease-in-out infinite;
}

@keyframes pulse-glow {
    0%, 100% { filter: drop-shadow(0 0 20px rgba(0, 230, 118, 0.6)); }
    50%       { filter: drop-shadow(0 0 40px rgba(0, 230, 118, 1.0)); }
}

.about-hero__title {
    font-size: 52px;
    font-weight: 800;
    color: #fff;
    letter-spacing: -1px;
    margin-bottom: 16px;
    line-height: 1.1;
}

.about-hero__subtitle {
    font-size: 18px;
    color: #6aab7a;
    max-width: 560px;
    line-height: 1.6;
    margin-bottom: 36px;
}

.about-hero__cta {
    display: inline-flex;
    align-items: center;
    padding: 14px 36px;
    background: #00e676;
    color: #060d08;
    font-size: 15px;
    font-weight: 700;
    border-radius: 10px;
    text-decoration: none;
    transition: background 0.18s, transform 0.18s, box-shadow 0.18s;
    letter-spacing: 0.3px;
    box-shadow: 0 0 24px rgba(0, 230, 118, 0.4);
}

.about-hero__cta:hover {
    background: #69f0ae;
    transform: translateY(-2px);
    box-shadow: 0 0 40px rgba(0, 230, 118, 0.6);
}

/* ── Stats row ────────────────────────────────────────────────────────────── */
.about-stats {
    background: #080f09;
    border-bottom: 1px solid #0f2d1a;
    padding: 40px 0;
}

.about-stats .about-inner {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0;
}

.about-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 0 24px;
    border-right: 1px solid #222;
}

.about-stat:last-child { border-right: none; }

.about-stat__value {
    font-size: 36px;
    font-weight: 800;
    color: #00e676;
    line-height: 1;
    text-shadow: 0 0 12px rgba(0, 230, 118, 0.5);
}

.about-stat__label {
    font-size: 13px;
    color: #4a7a55;
    text-align: center;
    line-height: 1.4;
}

/* ── Generic section ──────────────────────────────────────────────────────── */
.about-section {
    padding: 60px 0;
    border-bottom: 1px solid #0d1f12;
}

.about-section--alt {
    background: #080f09;
}

.about-section__title {
    font-size: 20px;
    font-weight: 700;
    color: #c8e6cc;
    margin-bottom: 28px;
    padding-bottom: 12px;
    border-bottom: 2px solid #0b2014;
}

/* ── Feature cards ────────────────────────────────────────────────────────── */
.about-feature-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.about-feature-card {
    background: #0c1a0e;
    border: 1px solid #163020;
    border-radius: 14px;
    padding: 28px 24px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    transition: border-color 0.18s, box-shadow 0.18s;
}

.about-feature-card:hover {
    border-color: rgba(0, 230, 118, 0.4);
    box-shadow: 0 0 20px rgba(0, 230, 118, 0.08);
}

.about-feature-card__icon {
    font-size: 36px;
    line-height: 1;
}

.about-feature-card__title {
    font-size: 16px;
    font-weight: 700;
    color: #c8e6cc;
}

.about-feature-card__text {
    font-size: 13px;
    color: #4a7a55;
    line-height: 1.6;
}

/* ── Tech tags ────────────────────────────────────────────────────────────── */
.about-tech-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.about-tech-tag {
    background: #0c1a0e;
    border: 1px solid #163020;
    color: #6aab7a;
    font-size: 13px;
    font-weight: 600;
    padding: 8px 18px;
    border-radius: 8px;
    letter-spacing: 0.2px;
    transition: border-color 0.15s, color 0.15s;
}

.about-tech-tag:hover {
    border-color: rgba(0, 230, 118, 0.4);
    color: #00e676;
}

/* ── NASA card ────────────────────────────────────────────────────────────── */
.about-nasa-card {
    background: #0c1a0e;
    border: 1px solid #163020;
    border-left: 3px solid #00e676;
    border-radius: 10px;
    padding: 24px 28px;
    max-width: 720px;
    box-shadow: -4px 0 20px rgba(0, 230, 118, 0.08);
}

.about-nasa-card__header {
    font-size: 16px;
    font-weight: 700;
    color: #00e676;
    margin-bottom: 12px;
}

.about-nasa-card__text {
    font-size: 14px;
    color: #4a7a55;
    line-height: 1.8;
}

/* ── Footer ───────────────────────────────────────────────────────────────── */
.about-footer {
    margin-top: auto;
    padding: 24px 32px;
    background: #040908;
    border-top: 1px solid #0b2014;
    text-align: center;
    font-size: 12px;
    color: #2a5535;
    letter-spacing: 0.3px;
}
</style>

<style>
html[data-theme="light"] .about-root            { background: var(--afs-bg) !important; color: var(--afs-text); }
html[data-theme="light"] .about-hero            { background: linear-gradient(160deg, #e8f5e9 0%, #f4f7f4 100%); }
html[data-theme="light"] .about-hero__title     { color: var(--afs-text); }
html[data-theme="light"] .about-hero__subtitle  { color: var(--afs-text2); }
html[data-theme="light"] .about-hero__cta       { background: var(--afs-accent2); color: #ffffff; }
html[data-theme="light"] .about-hero__cta:hover { background: var(--afs-accent); }
html[data-theme="light"] .about-stats           { background: #ffffff; border-top-color: var(--afs-border); border-bottom-color: var(--afs-border); }
html[data-theme="light"] .about-stat            { border-right-color: var(--afs-border); }
html[data-theme="light"] .about-stat__value     { color: var(--afs-accent2); }
html[data-theme="light"] .about-stat__label     { color: var(--afs-muted); }
html[data-theme="light"] .about-section         { background: var(--afs-bg); }
html[data-theme="light"] .about-section--alt    { background: #ffffff; }
html[data-theme="light"] .about-section__title  { color: var(--afs-text); }
html[data-theme="light"] .about-feature-card    { background: #ffffff; border-color: var(--afs-border); box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
html[data-theme="light"] .about-feature-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.1); border-color: var(--afs-accent2); }
html[data-theme="light"] .about-feature-card__title { color: var(--afs-text); }
html[data-theme="light"] .about-feature-card__text  { color: var(--afs-text2); }
html[data-theme="light"] .about-tech-tag        { background: var(--afs-bg5); border-color: var(--afs-border); color: var(--afs-text2); }
html[data-theme="light"] .about-tech-tag:hover  { border-color: var(--afs-accent2); color: var(--afs-accent2); }
html[data-theme="light"] .about-nasa-card       { background: #ffffff; border-color: var(--afs-border); }
html[data-theme="light"] .about-nasa-card__header { border-bottom-color: var(--afs-border); }
html[data-theme="light"] .about-nasa-card__text { color: var(--afs-text2); }
html[data-theme="light"] .about-footer          { background: var(--afs-bg3) !important; border-top-color: var(--afs-border); color: var(--afs-muted); }
</style>
