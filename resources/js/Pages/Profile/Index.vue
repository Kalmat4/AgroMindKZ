<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'

const props = defineProps({
    user: Object,
})

const page = usePage()

const profileForm = useForm({
    name:  props.user.name,
    email: props.user.email,
})

const passwordForm = useForm({
    current_password:      '',
    password:              '',
    password_confirmation: '',
})

const saveProfile = () => {
    profileForm.patch('/profile', { preserveScroll: true })
}

const savePassword = () => {
    passwordForm.patch('/profile/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    })
}

const logout = () => {
    router.post('/logout')
}
</script>

<template>
    <AppLayout>
        <Head title="Профиль — AgroMind KZ" />
        <!-- Page content -->
        <main class="afs-main">

            <div class="afs-page-title">
                <h1>Профиль</h1>
                <p>Управляйте своими данными</p>
            </div>

            <!-- Flash -->
            <div v-if="page.props.flash?.success" class="afs-flash">
                ✓ {{ page.props.flash.success }}
            </div>

            <!-- Region subscription card -->
            <section class="afs-card afs-card--region">
                <div class="afs-card__header">
                    <span class="afs-card__icon">📍</span>
                    <h2>Мой регион</h2>
                </div>
                <p v-if="props.user.zone" class="afs-region-info">
                    Текущий регион: <strong>{{ props.user.zone.oblast_name }}</strong>
                </p>
                <p v-else class="afs-region-info afs-region-info--empty">
                    Вы ещё не выбрали регион для уведомлений
                </p>
                <Link href="/dashboard?subscribe=1" class="afs-btn afs-btn--subscribe">
                    📍 Подписаться на уведомления региона
                </Link>
            </section>

            <div class="afs-cards">

                <!-- Personal info -->
                <section class="afs-card">
                    <div class="afs-card__header">
                        <span class="afs-card__icon">👤</span>
                        <h2>Личные данные</h2>
                    </div>

                    <form @submit.prevent="saveProfile" class="afs-form">
                        <div class="afs-field">
                            <label for="name">ФИО</label>
                            <input
                                id="name"
                                v-model="profileForm.name"
                                type="text"
                                placeholder="Введите ФИО"
                                :class="{ 'afs-input--error': profileForm.errors.name }"
                            />
                            <span v-if="profileForm.errors.name" class="afs-error">
                                {{ profileForm.errors.name }}
                            </span>
                        </div>

                        <div class="afs-field">
                            <label for="email">Почта</label>
                            <input
                                id="email"
                                v-model="profileForm.email"
                                type="email"
                                placeholder="example@mail.com"
                                :class="{ 'afs-input--error': profileForm.errors.email }"
                            />
                            <span v-if="profileForm.errors.email" class="afs-error">
                                {{ profileForm.errors.email }}
                            </span>
                        </div>

                        <button
                            type="submit"
                            class="afs-btn afs-btn--primary"
                            :disabled="profileForm.processing"
                        >
                            {{ profileForm.processing ? 'Сохранение...' : 'Сохранить' }}
                        </button>
                    </form>
                </section>

                <!-- Password -->
                <section class="afs-card">
                    <div class="afs-card__header">
                        <span class="afs-card__icon">🔒</span>
                        <h2>Смена пароля</h2>
                    </div>

                    <form @submit.prevent="savePassword" class="afs-form">
                        <div class="afs-field">
                            <label for="current_password">Текущий пароль</label>
                            <input
                                id="current_password"
                                v-model="passwordForm.current_password"
                                type="password"
                                placeholder="••••••••"
                                :class="{ 'afs-input--error': passwordForm.errors.current_password }"
                            />
                            <span v-if="passwordForm.errors.current_password" class="afs-error">
                                {{ passwordForm.errors.current_password }}
                            </span>
                        </div>

                        <div class="afs-field">
                            <label for="password">Новый пароль</label>
                            <input
                                id="password"
                                v-model="passwordForm.password"
                                type="password"
                                placeholder="Минимум 8 символов"
                                :class="{ 'afs-input--error': passwordForm.errors.password }"
                            />
                            <span v-if="passwordForm.errors.password" class="afs-error">
                                {{ passwordForm.errors.password }}
                            </span>
                        </div>

                        <div class="afs-field">
                            <label for="password_confirmation">Повторите пароль</label>
                            <input
                                id="password_confirmation"
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                placeholder="••••••••"
                            />
                        </div>

                        <button
                            type="submit"
                            class="afs-btn afs-btn--primary"
                            :disabled="passwordForm.processing"
                        >
                            {{ passwordForm.processing ? 'Сохранение...' : 'Изменить пароль' }}
                        </button>
                    </form>
                </section>

            </div>
        </main>
    </AppLayout>
</template>

<style scoped>
/* ── Page ── */
.afs-main {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 24px;
}

.afs-page-title {
    margin-bottom: 32px;
}

.afs-page-title h1 {
    font-size: 28px;
    font-weight: 700;
    color: #e5e5e5;
    margin: 0 0 4px;
    border-left: 3px solid #4ade80;
    padding-left: 12px;
}

.afs-page-title p {
    color: #888;
    margin: 0 0 0 15px;
    font-size: 14px;
}

/* ── Flash ── */
.afs-flash {
    background: rgba(74, 222, 128, 0.1);
    border: 1px solid rgba(74, 222, 128, 0.35);
    color: #4ade80;
    border-radius: 10px;
    padding: 12px 18px;
    margin-bottom: 24px;
    font-size: 14px;
    font-weight: 500;
}

/* ── Cards grid ── */
.afs-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
    gap: 24px;
}

/* ── Card ── */
.afs-card {
    background: #1a1a1a;
    border-radius: 12px;
    padding: 28px;
    box-shadow: none;
    border: 1px solid #2d2d2d;
}

.afs-card__header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #2d2d2d;
}

.afs-card__icon {
    font-size: 22px;
}

.afs-card__header h2 {
    font-size: 17px;
    font-weight: 700;
    color: #e5e5e5;
    margin: 0;
}

/* ── Form ── */
.afs-form {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.afs-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.afs-field label {
    font-size: 13px;
    font-weight: 600;
    color: #888;
}

.afs-field input {
    padding: 10px 14px;
    border: 1px solid #2d2d2d;
    border-radius: 8px;
    font-size: 14px;
    color: #e5e5e5;
    background: #111;
    transition: border-color 0.18s, box-shadow 0.18s;
    outline: none;
    font-family: inherit;
}

.afs-field input::placeholder {
    color: #555;
}

.afs-field input:focus {
    border-color: #4ade80;
    box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.1);
}

.afs-input--error {
    border-color: #f87171 !important;
}

.afs-error {
    font-size: 12px;
    color: #f87171;
}

/* ── Button ── */
.afs-btn {
    padding: 10px 24px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: background 0.18s;
    align-self: flex-start;
}

.afs-btn--primary {
    background: #4ade80;
    color: #000;
}

.afs-btn--primary:hover:not(:disabled) {
    background: #22c55e;
}

.afs-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* ── Region card ── */
.afs-card--region {
    margin-bottom: 8px;
}

.afs-region-info {
    color: #c8bfb5;
    font-size: 14px;
    margin: 0 0 18px;
    line-height: 1.5;
}
.afs-region-info strong {
    color: #4ade80;
}
.afs-region-info--empty {
    color: #888;
    font-style: italic;
}

.afs-btn--subscribe {
    display: inline-block;
    background: #00bcd4;
    color: #fff;
    text-decoration: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    transition: background 0.18s;
}
.afs-btn--subscribe:hover {
    background: #00acc1;
}
</style>

<style>
html[data-theme="light"] .afs-main          { background: var(--afs-bg) !important; }
html[data-theme="light"] .afs-page-title h1 { color: var(--afs-text); }
html[data-theme="light"] .afs-page-title p  { color: var(--afs-muted); }
html[data-theme="light"] .afs-card          { background: #ffffff; border-color: var(--afs-border); box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
html[data-theme="light"] .afs-card__header  { border-bottom-color: var(--afs-border); }
html[data-theme="light"] .afs-card__header h2 { color: var(--afs-text); }
html[data-theme="light"] .afs-field label   { color: var(--afs-text2); }
html[data-theme="light"] .afs-field input   { background: var(--afs-bg3); color: var(--afs-text); border-color: var(--afs-border); }
html[data-theme="light"] .afs-field input::placeholder { color: var(--afs-muted2); }
html[data-theme="light"] .afs-field input:focus { border-color: var(--afs-accent2); }
html[data-theme="light"] .afs-region-info        { color: var(--afs-text2); }
html[data-theme="light"] .afs-region-info strong  { color: #007a3a; }
html[data-theme="light"] .afs-region-info--empty  { color: var(--afs-muted); }
html[data-theme="light"] .afs-btn--subscribe      { background: #0097a7; }
html[data-theme="light"] .afs-btn--subscribe:hover { background: #00838f; }
</style>
