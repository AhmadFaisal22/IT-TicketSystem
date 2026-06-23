<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="text-center">
      <div class="w-12 h-12 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
      <p class="text-gray-600">Signing you in...</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { consumeRedirect } from '@/utils/postLoginRedirect'
import api from '@/api'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

onMounted(async () => {
  try {
    const ALLOWED_PROVIDERS = ['google', 'microsoft']
    const rawProvider = route.query.provider as string
    const provider = ALLOWED_PROVIDERS.includes(rawProvider) ? rawProvider : 'google'
    const code = route.query.code as string
    const { data } = await api.get(`/auth/callback/${provider}?code=${code}&state=${route.query.state || ''}`)
    auth.setToken(data.token)
    auth.user = data.user
    const redirect = consumeRedirect()
    router.push(redirect ? { path: redirect } : { name: 'dashboard' })
  } catch {
    router.push({ name: 'login' })
  }
})
</script>
