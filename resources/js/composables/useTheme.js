import { ref, watch } from 'vue'

const isDark = ref(localStorage.getItem('afs-theme') !== 'light')

watch(isDark, (dark) => {
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light')
    localStorage.setItem('afs-theme', dark ? 'dark' : 'light')
}, { immediate: true })

export function useTheme() {
    return {
        isDark,
        toggle: () => { isDark.value = !isDark.value },
    }
}
