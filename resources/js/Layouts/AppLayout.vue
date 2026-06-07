<script setup>
import { Link, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useTheme } from '@/composables/useTheme'

const { t, locale } = useI18n()
const { isDark, toggle } = useTheme()

const logout = () => { router.post('/logout') }

const setLocale = (lang) => {
    locale.value = lang
    localStorage.setItem('agromind_locale', lang)
}
</script>

<template>
    <div class="afs-root">
        <header class="afs-header">
            <div class="afs-header__inner">
                <Link href="/dashboard" class="afs-logo">
                    <span class="afs-logo__icon">🌿</span>
                    <span class="afs-logo__text">AgroMind KZ</span>
                </Link>
                <nav class="afs-nav">
                    <Link href="/dashboard" class="afs-nav__btn">{{ t('nav.home') }}</Link>
                    <Link href="/subsidies" class="afs-nav__btn">{{ t('nav.subsidies') }}</Link>
                    <Link href="/ecology" class="afs-nav__btn">{{ t('nav.ecology') }}</Link>
                    <Link href="/yield" class="afs-nav__btn">{{ t('nav.yield') }}</Link>
                    <Link href="/about" class="afs-nav__btn">{{ t('nav.about') }}</Link>
                    <Link href="/profile" class="afs-nav__btn">{{ t('nav.profile') }}</Link>

                    <div class="lang-switcher">
                        <button
                            v-for="lang in ['ru', 'en', 'kz']"
                            :key="lang"
                            class="lang-btn"
                            :class="{ active: locale === lang }"
                            @click="setLocale(lang)"
                        >{{ lang.toUpperCase() }}</button>
                    </div>

                    <!-- Theme toggle -->
                    <button class="afs-theme-toggle" @click="toggle" :title="isDark ? 'Дневная тема' : 'Ночная тема'">
                        <span v-if="isDark">☀️</span>
                        <span v-else>🌙</span>
                    </button>

                    <button class="afs-nav__logout" @click="logout">{{ t('nav.logout') }}</button>
                </nav>
            </div>
        </header>
        <slot />
    </div>
</template>

<style scoped>
.afs-root {
    min-height: 100vh;
    background: var(--afs-bg);
    color: var(--afs-text);
    transition: background 0.25s, color 0.25s;
}

.afs-header {
    background: var(--afs-header-bg);
    border-bottom: 3px solid var(--afs-header-bdr);
    box-shadow: 0 2px 20px var(--afs-shadow);
    position: sticky;
    top: 0;
    z-index: 1000;
    transition: background 0.25s, box-shadow 0.25s;
}

.afs-header__inner {
    max-width: 100%;
    padding: 0 24px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.afs-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    user-select: none;
}

.afs-logo__icon {
    font-size: 28px;
    filter: drop-shadow(0 0 8px var(--afs-shadow));
}

.afs-logo__text {
    font-size: 20px;
    font-weight: 700;
    color: var(--afs-text);
    letter-spacing: 0.5px;
    transition: color 0.25s;
}

.afs-nav {
    display: flex;
    align-items: center;
    gap: 8px;
}

.afs-nav__btn {
    display: inline-flex;
    align-items: center;
    padding: 8px 18px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    color: var(--afs-text2);
    text-decoration: none;
    border: 1px solid transparent;
    transition: background 0.18s, color 0.18s, box-shadow 0.18s;
}

.afs-nav__btn:hover,
.afs-nav__btn.router-link-active {
    background: var(--afs-dim);
    color: var(--afs-accent);
    border-color: var(--afs-shadow);
    box-shadow: 0 0 8px var(--afs-dim);
}

.afs-nav__logout {
    display: inline-flex;
    align-items: center;
    padding: 8px 18px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    color: var(--afs-text2);
    background: transparent;
    border: 1px solid var(--afs-border);
    cursor: pointer;
    transition: background 0.18s, color 0.18s;
}

.afs-nav__logout:hover {
    background: var(--afs-dim);
    color: var(--afs-accent);
    border-color: var(--afs-accent2);
}

/* ── Theme toggle ── */
.afs-theme-toggle {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--afs-dim);
    border: 1px solid var(--afs-border);
    cursor: pointer;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.18s, border-color 0.18s, transform 0.18s;
    flex-shrink: 0;
}

.afs-theme-toggle:hover {
    background: var(--afs-dim);
    border-color: var(--afs-accent);
    transform: scale(1.1);
}

/* ── Language switcher ── */
.lang-switcher {
    display: flex;
    align-items: center;
    gap: 2px;
    background: var(--afs-dim2);
    border: 1px solid var(--afs-border);
    border-radius: 8px;
    padding: 3px;
    margin: 0 4px;
    transition: background 0.25s, border-color 0.25s;
}

.lang-btn {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    color: var(--afs-text2);
    background: transparent;
    border: none;
    cursor: pointer;
    transition: color 0.15s, background 0.15s;
    letter-spacing: 0.5px;
}

.lang-btn:hover { color: var(--afs-accent); }

.lang-btn.active {
    background: var(--afs-dim);
    color: var(--afs-accent);
}
</style>
