<template>
  <div class="min-h-screen relative overflow-hidden bg-white">

    <!-- Full-viewport image background (desktop) -->
    <div class="hidden lg:block absolute inset-0 z-0">
      <AnimatedMapPanel />
    </div>

    <!-- Gradient blend: image fades into white toward the right -->
    <div
      class="hidden lg:block absolute inset-0 z-10 pointer-events-none"
      style="background: linear-gradient(to right, transparent 38%, rgba(255,255,255,0.55) 52%, rgba(255,255,255,0.92) 63%, #ffffff 74%);"
    />

    <!-- Login form — sits on the right, above the gradient -->
    <div class="relative z-20 min-h-screen flex lg:justify-end">
      <div class="w-full lg:w-[46%] flex items-center justify-center p-8 bg-white lg:bg-transparent">
      <div class="w-full max-w-sm">

        <!-- Logo -->
        <div class="flex items-center gap-3 mb-8">
          <img src="/SEG Logo.png" alt="SEG Solar" class="h-11 w-auto object-contain" />
          <div class="hidden sm:block">
            <p class="text-xs text-gray-400 leading-tight">IT Ticketing System</p>
          </div>
        </div>

        <!-- Welcome heading -->
        <div class="mb-6">
          <h1 class="text-2xl font-semibold text-gray-800">{{ t('auth.welcomeTitle') }}</h1>
          <p class="text-gray-500 text-sm mt-1">{{ t('auth.welcomeSubtitle') }}</p>
        </div>

        <!-- Error / lockout banner -->
        <Transition name="error-slide">
        <div v-if="error || isLocked"
          class="mb-4 rounded-lg border px-4 py-3 text-sm transition-all"
          :class="isLocked
            ? 'bg-red-50 border-red-300 text-red-700'
            : attemptsLeft <= 1
              ? 'bg-red-50 border-red-300 text-red-700'
              : 'bg-orange-50 border-orange-200 text-orange-700'">

          <!-- Icon + message -->
          <div class="flex items-start gap-2">
            <svg v-if="isLocked" class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <svg v-else class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="flex-1">
              <p v-if="isLocked" class="font-medium">{{ t('auth.tooManyAttempts') }}</p>
              <p v-else class="font-medium">{{ error }}</p>
              <p v-if="isLocked" class="mt-0.5 text-xs opacity-80">
                {{ t('auth.lockoutWait', { n: lockCountdown }) }}
              </p>
            </div>
          </div>

          <!-- Attempt progress bar -->
          <div v-if="!isLocked && failedAttempts > 0"
            class="mt-3 pt-2.5 border-t"
            :class="attemptsLeft <= 1 ? 'border-red-200' : 'border-orange-200'">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs opacity-70">{{ t('auth.loginAttempts') }}</span>
              <span class="text-xs font-semibold"
                :class="attemptsLeft <= 1 ? 'text-red-700' : attemptsLeft <= 2 ? 'text-orange-600' : 'text-amber-600'">
                {{ attemptsLeft }} / {{ MAX_ATTEMPTS }} {{ t('auth.attemptsLeft') }}
              </span>
            </div>
            <div class="flex items-center gap-1.5">
              <div v-for="i in MAX_ATTEMPTS" :key="i"
                class="flex-1 h-1.5 rounded-full transition-all duration-300"
                :class="i <= attemptsLeft
                  ? attemptsLeft <= 1 ? 'bg-red-500'
                    : attemptsLeft <= 2 ? 'bg-orange-400'
                    : attemptsLeft <= 3 ? 'bg-amber-400'
                    : 'bg-amber-300'
                  : 'bg-gray-200'">
              </div>
            </div>
          </div>
        </div>
        </Transition>

        <!-- Form -->
        <form @submit.prevent="loginWithPassword" :class="['space-y-4', { shake: shaking }]">
          <!-- Email -->
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('auth.email') }}</label>
            <input
              id="email"
              name="email"
              v-model="email"
              type="email"
              required
              autocomplete="email"
              :placeholder="t('auth.emailPlaceholder')"
              @input="clearError"
              class="w-full px-4 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
              :class="error && !isLocked ? 'border-red-400 bg-red-50' : 'border-gray-300'"
            />
          </div>

          <!-- Password -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label for="password" class="block text-sm font-medium text-gray-700">{{ t('auth.password') }}</label>
              <router-link to="/forgot-password" class="text-sm text-red-600 hover:text-red-700 font-medium">{{ t('auth.forgotPassword') }}</router-link>
            </div>
            <div class="relative">
              <input
                id="password"
                name="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="current-password"
                :placeholder="t('auth.passwordPlaceholder')"
                @input="clearError"
                class="w-full px-4 py-2.5 pr-12 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                :class="error && !isLocked ? 'border-red-400 bg-red-50' : 'border-gray-300'"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
              >
                <svg v-if="!showPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- Remember Me -->
          <div class="flex items-center">
            <input
              id="remember"
              v-model="remember"
              type="checkbox"
              class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500"
            />
            <label for="remember" class="ml-2 text-sm text-gray-600">{{ t('auth.rememberMe') }}</label>
          </div>

          <!-- Sign In button -->
          <button
            type="submit"
            :disabled="loading || isLocked"
            class="w-full py-3 text-white font-semibold rounded-lg transition disabled:cursor-not-allowed text-sm tracking-wide"
            :class="isLocked
              ? 'bg-gray-400 hover:bg-gray-400 opacity-80'
              : 'bg-red-600 hover:bg-red-700 disabled:opacity-50'"
          >
            <span v-if="isLocked" class="flex items-center justify-center gap-2">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
              </svg>
              {{ t('auth.lockedOut') }} ({{ lockCountdown }}s)
            </span>
            <span v-else-if="loading" class="flex items-center justify-center gap-2">
              <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              {{ t('auth.signingIn') }}
            </span>
            <span v-else>{{ t('auth.login') }}</span>
          </button>
        </form>

        <!-- Register link -->
        <div class="mt-6 text-center">
          <router-link to="/register" class="text-sm text-gray-500 hover:text-gray-700">
            {{ t('auth.noAccount') }} <span class="text-red-600 font-medium">{{ t('auth.register') }}</span>
          </router-link>
        </div>

        <!-- Locale + theme toggle -->
        <div class="mt-8 flex justify-center items-center gap-4 text-sm border-t border-gray-100 dark:border-gray-700 pt-6">
          <ThemeToggle />
          <span class="text-gray-200 dark:text-gray-600">|</span>
          <button
            @click="setLocale('en')"
            :class="locale === 'en' ? 'text-red-600 font-semibold' : 'text-gray-400 hover:text-gray-600'"
            class="transition"
          >English</button>
          <span class="text-gray-300">|</span>
          <button
            @click="setLocale('zh')"
            :class="locale === 'zh' ? 'text-red-600 font-semibold' : 'text-gray-400 hover:text-gray-600'"
            class="transition"
          >中文</button>
        </div>

      </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { authApi } from '@/api'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'
import AnimatedMapPanel from '@/components/ui/AnimatedMapPanel.vue'
import { useAuthStore } from '@/stores/auth'

const { t, locale } = useI18n()
const router = useRouter()
const auth = useAuthStore()

const MAX_ATTEMPTS = 5

const loading = ref(false)
const error = ref('')
const email = ref('')
const password = ref('')
const remember = ref(false)
const showPassword = ref(false)
const failedAttempts = ref(0)
const isLocked = ref(false)
const lockCountdown = ref(0)
const shaking = ref(false)

let countdownTimer: ReturnType<typeof setInterval> | null = null

const attemptsLeft = computed(() => Math.max(0, MAX_ATTEMPTS - failedAttempts.value))

function triggerShake() {
  shaking.value = true
  setTimeout(() => { shaking.value = false }, 600)
}

function startLockout(seconds: number) {
  isLocked.value = true
  lockCountdown.value = seconds
  error.value = ''
  if (countdownTimer) clearInterval(countdownTimer)
  countdownTimer = setInterval(() => {
    lockCountdown.value--
    if (lockCountdown.value <= 0) {
      clearInterval(countdownTimer!)
      countdownTimer = null
      isLocked.value = false
      failedAttempts.value = 0
      error.value = ''
    }
  }, 1000)
}

function clearError() {
  if (error.value) error.value = ''
}

async function loginWithPassword() {
  if (isLocked.value) return
  loading.value = true
  try {
    const { data } = await authApi.login(email.value, password.value)
    auth.setToken(data.token)
    auth.user = data.user
    failedAttempts.value = 0
    error.value = ''
    router.push({ name: 'dashboard' })
  } catch (e: any) {
    const status = e.response?.status
    if (status === 429) {
      const retryAfter = parseInt(e.response.headers?.['retry-after'] ?? '60', 10)
      startLockout(retryAfter || 60)
    } else if (status === 403) {
      error.value = e.response?.data?.message || t('auth.accountDisabled')
      triggerShake()
    } else {
      failedAttempts.value = Math.min(failedAttempts.value + 1, MAX_ATTEMPTS)
      error.value = e.response?.data?.message ?? t('auth.invalidCredentials')
      triggerShake()
    }
  } finally {
    loading.value = false
  }
}

function setLocale(l: string) {
  locale.value = l
  auth.changeLocale(l)
}

onUnmounted(() => {
  if (countdownTimer) clearInterval(countdownTimer)
})
</script>

<style scoped>
@keyframes shake {
  0%, 100% { transform: translateX(0); }
  15%       { transform: translateX(-7px); }
  30%       { transform: translateX(7px); }
  45%       { transform: translateX(-5px); }
  60%       { transform: translateX(5px); }
  75%       { transform: translateX(-3px); }
  90%       { transform: translateX(3px); }
}
.shake { animation: shake 0.55s ease-in-out; }

.error-slide-enter-active { transition: all 0.25s ease-out; }
.error-slide-leave-active  { transition: all 0.2s ease-in; }
.error-slide-enter-from    { opacity: 0; transform: translateY(-8px); }
.error-slide-leave-to      { opacity: 0; transform: translateY(-4px); }
</style>
