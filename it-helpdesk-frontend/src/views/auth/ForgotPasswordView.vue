<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 p-4">
    <div class="w-full max-w-sm">

      <!-- Logo -->
      <div class="flex items-center gap-3 mb-8 justify-center">
        <img src="/SEG Logo.png" alt="SEG Solar" class="h-10 w-auto object-contain" />
        <span class="text-sm text-gray-400 font-medium">IT Ticketing System</span>
      </div>

      <!-- Card -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">

        <!-- Success state -->
        <div v-if="sent" class="text-center">
          <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
          </div>
          <h2 class="text-xl font-semibold text-gray-800 mb-2">Check your email</h2>
          <p class="text-sm text-gray-500 mb-1">
            We sent a password reset link to
          </p>
          <p class="text-sm font-semibold text-gray-800 mb-4">{{ email }}</p>
          <p class="text-xs text-gray-400 mb-6">
            Didn't receive it? Check your spam folder or
            <button @click="sent = false" class="text-red-600 hover:underline font-medium">try again</button>.
          </p>
          <router-link to="/login"
            class="block w-full text-center py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm font-medium text-gray-700 transition">
            Back to sign in
          </router-link>
        </div>

        <!-- Form state -->
        <div v-else>
          <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-800">Forgot your password?</h2>
            <p class="text-sm text-gray-500 mt-1">
              Enter your email and we'll send you a reset link.
            </p>
          </div>

          <form @submit.prevent="submit" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
              <input v-model="email" type="email" required autofocus
                placeholder="you@company.com"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                :class="error ? 'border-red-400 bg-red-50' : ''" />
              <p v-if="error" class="text-xs text-red-600 mt-1.5">{{ error }}</p>
            </div>

            <button type="submit" :disabled="loading"
              class="w-full py-2.5 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 disabled:opacity-50 transition flex items-center justify-center gap-2">
              <div v-if="loading" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
              {{ loading ? 'Sending…' : 'Send Reset Link' }}
            </button>
          </form>

          <div class="mt-6 text-center">
            <router-link to="/login" class="text-sm text-gray-500 hover:text-gray-700 flex items-center justify-center gap-1.5">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
              </svg>
              Back to sign in
            </router-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { authApi } from '@/api'

const email   = ref('')
const error   = ref('')
const loading = ref(false)
const sent    = ref(false)

async function submit() {
  error.value   = ''
  loading.value = true
  try {
    await authApi.forgotPassword(email.value)
    sent.value = true
  } catch (e: any) {
    error.value = e?.response?.data?.message || 'Something went wrong. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>
