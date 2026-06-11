import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const backendUrl = env.VITE_API_TARGET ?? 'http://localhost:8000'

  return {
    plugins: [vue()],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url))
      }
    },
    server: {
      port: 5173,
      proxy: {
        '/api': { target: backendUrl, changeOrigin: true },
        '/storage': { target: backendUrl, changeOrigin: true }
      }
    },
    build: {
      // 'static' instead of the default 'assets' — the /assets URL path is
      // taken by the inventory SPA route, and a real /assets directory makes
      // nginx skip the SPA fallback (404 on refresh).
      assetsDir: 'static',
      chunkSizeWarningLimit: 600,
      rollupOptions: {
        output: {
          manualChunks: {
            'vue-vendor':   ['vue', 'vue-router', 'pinia', 'vue-i18n'],
            'ui-vendor':    ['@vueuse/core'],
            'charts':       ['vue3-apexcharts', 'apexcharts'],
            'http-vendor':  ['axios'],
          }
        }
      }
    }
  }
})
