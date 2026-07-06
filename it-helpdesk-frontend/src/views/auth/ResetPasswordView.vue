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
        <div v-if="done" class="text-center">
          <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
          </div>
          <h2 class="text-xl font-semibold text-gray-800 mb-2">Password reset!</h2>
          <p class="text-sm text-gray-500 mb-6">Your password has been changed successfully. You can now sign in with your new password.</p>
          <router-link to="/login"
            class="block w-full text-center py-2.5 rounded-lg bg-red-600 hover:bg-red-700 text-sm font-semibold text-white transition">
            Back to sign in
          </router-link>
        </div>

        <!-- Invalid token state -->
        <div v-else-if="invalidLink" class="text-center">
          <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <h2 class="text-xl font-semibold text-gray-800 mb-2">Link expired</h2>
          <p class="text-sm text-gray-500 mb-6">{{ invalidLink }}</p>
          <router-link to="/forgot-password"
            class="block w-full text-center py-2.5 rounded-lg bg-red-600 hover:bg-red-700 text-sm font-semibold text-white transition">
            Request a new link
          </router-link>
        </div>

        <!-- Form state -->
        <div v-else>
          <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-800">Create new password</h2>
            <p class="text-sm text-gray-500 mt-1">
              Choose a strong password for <span class="font-medium">{{ emailFromQuery }}</span>.
            </p>
          </div>

          <form @submit.prevent="submit" class="space-y-4">
            <!-- New password -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">New password</label>
              <div class="relative">
                <input v-model="password" :type="showPassword ? 'text' : 'password'" required minlength="8"
                  placeholder="At least 8 characters"
                  class="w-full px-4 py-2.5 pr-12 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                  :class="fieldError('password') ? 'border-red-400 bg-red-50' : ''" />
                <button type="button" @click="showPassword = !showPassword"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                  <svg v-if="!showPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  </svg>
                  <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                  </svg>
                </button>
              </div>
              <p v-if="fieldError('password')" class="text-xs text-red-600 mt-1.5">{{ fieldError('password') }}</p>
            </div>

            <!-- Confirm password -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm new password</label>
              <input v-model="passwordConfirmation" :type="showPassword ? 'text' : 'password'" required
                placeholder="Repeat your password"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                :class="fieldError('password_confirmation') ? 'border-red-400 bg-red-50' : ''" />
              <p v-if="fieldError('password_confirmation')" class="text-xs text-red-600 mt-1.5">{{ fieldError('password_confirmation') }}</p>
            </div>

            <!-- Generic error -->
            <p v-if="genericError" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
              {{ genericError }}
            </p>

            <button type="submit" :disabled="loading"
              class="w-full py-2.5 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 disabled:opacity-50 transition flex items-center justify-center gap-2">
              <div v-if="loading" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
              {{ loading ? 'Resetting…' : 'Reset Password' }}
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
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { authApi } from '@/api'

const route  = useRoute()
const router = useRouter()

const token              = ref('')
const emailFromQuery     = ref('')
const password           = ref('')
const passwordConfirmation = ref('')
const loading            = ref(false)
const showPassword       = ref(false)
const done               = ref(false)
const invalidLink        = ref('')
const errors             = ref<Record<string, string[]>>({})
const genericError       = ref('')

onMounted(() => {
  token.value         = (route.query.token as string) ?? ''
  emailFromQuery.value = (route.query.email as string) ?? ''
  if (!token.value || !emailFromQuery.value) {
    invalidLink.value = 'This reset link is missing required parameters. Please request a new one.'
  }
})

function fieldError(field: string): string {
  return errors.value[field]?.[0] ?? ''
}

async function submit() {
  errors.value      = {}
  genericError.value = ''
  if (/\s/.test(password.value)) {
    errors.value = { password: ['Password must not contain spaces.'] }
    return
  }
  loading.value     = true
  try {
    await authApi.resetPassword(token.value, emailFromQuery.value, password.value, passwordConfirmation.value)
    done.value = true
  } catch (e: any) {
    const status = e?.response?.status
    const data   = e?.response?.data
    if (status === 422) {
      if (data?.errors) {
        errors.value = data.errors
      } else {
        // Expired / invalid token — show the expired state
        const msg = data?.message ?? ''
        if (msg.toLowerCase().includes('invalid') || msg.toLowerCase().includes('expired')) {
          invalidLink.value = msg
        } else {
          genericError.value = msg
        }
      }
    } else {
      genericError.value = data?.message || 'Something went wrong. Please try again.'
    }
  } finally {
    loading.value = false
  }
}
</script>
