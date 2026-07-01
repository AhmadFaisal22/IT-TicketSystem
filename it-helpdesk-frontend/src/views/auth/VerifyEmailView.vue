<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 p-4">
    <div class="w-full max-w-sm">

      <!-- Logo -->
      <div class="flex items-center gap-3 mb-8 justify-center">
        <img src="/SEG Logo.png" alt="SEG Solar" class="h-10 w-auto object-contain" />
        <span class="text-sm text-gray-400 font-medium">IT Ticketing System</span>
      </div>

      <!-- Card -->
      <div class="bg-white rounded-card shadow-soft border border-gray-100 p-8 text-center">

        <!-- Verifying -->
        <div v-if="state === 'verifying'">
          <div class="w-12 h-12 border-4 border-brand-200 border-t-brand-600 rounded-full animate-spin mx-auto mb-4"></div>
          <h2 class="text-xl font-semibold text-gray-800 mb-2">Verifying your email…</h2>
          <p class="text-sm text-gray-500">This will only take a moment.</p>
        </div>

        <!-- Success -->
        <div v-else-if="state === 'success'">
          <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
          </div>
          <h2 class="text-xl font-semibold text-gray-800 mb-2">Email verified!</h2>
          <p class="text-sm text-gray-500 mb-6">{{ message }}</p>
          <router-link to="/login"
            class="block w-full text-center py-2.5 rounded-btn bg-brand-600 hover:bg-brand-700 text-sm font-semibold text-white transition">
            Sign in
          </router-link>
        </div>

        <!-- Failure -->
        <div v-else>
          <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <h2 class="text-xl font-semibold text-gray-800 mb-2">Verification failed</h2>
          <p class="text-sm text-gray-500 mb-6">{{ message }}</p>
          <router-link to="/register"
            class="block w-full text-center py-2.5 rounded-btn bg-brand-600 hover:bg-brand-700 text-sm font-semibold text-white transition">
            Register again
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { authApi } from '@/api'

const route = useRoute()

const state   = ref<'verifying' | 'success' | 'failure'>('verifying')
const message = ref('')

onMounted(async () => {
  const token = (route.query.token as string) ?? ''
  const email = (route.query.email as string) ?? ''

  if (!token || !email) {
    state.value = 'failure'
    message.value = 'This verification link is missing required parameters.'
    return
  }

  try {
    const { data } = await authApi.verifyEmail(token, email)
    state.value = 'success'
    message.value = data?.message || 'You can now sign in.'
  } catch (e: any) {
    state.value = 'failure'
    message.value = e?.response?.data?.message || 'This verification link is invalid or has expired.'
  }
})
</script>
