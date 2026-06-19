// Apply saved theme before Vue boots to prevent flash of unstyled content
;(function () {
  const saved = localStorage.getItem('theme')
  if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark')
  }
})()

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createI18n } from 'vue-i18n'
import VueApexCharts from 'vue3-apexcharts'
import App from './App.vue'
import router from './router'
import en from './locales/en'
import zh from './locales/zh'
import './assets/main.css'

const i18n = createI18n({
  legacy: false,
  locale: localStorage.getItem('locale') || 'en',
  fallbackLocale: 'en',
  messages: { en, zh }
})

// Recover from a failed module preload (stale chunk after redeploy, or a
// blocked fetch e.g. untrusted TLS cert) by reloading the page once.
window.addEventListener('vite:preloadError', () => {
  if (!sessionStorage.getItem('chunkReloaded')) {
    sessionStorage.setItem('chunkReloaded', '1')
    window.location.reload()
  }
})

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.use(i18n)
app.use(VueApexCharts)
app.mount('#app')
