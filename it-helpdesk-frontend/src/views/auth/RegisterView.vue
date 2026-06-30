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
          <h2 class="text-xl font-semibold text-gray-800 mb-2">Verify your email</h2>
          <p class="text-sm text-gray-500 mb-1">We sent a verification link to</p>
          <p class="text-sm font-semibold text-gray-800 mb-4">{{ form.email }}</p>
          <p class="text-xs text-gray-400 mb-6">
            Click the link in the email to activate your account, then sign in.
            Didn't receive it? Check your spam folder.
          </p>
          <router-link to="/login"
            class="block w-full text-center py-2.5 rounded-lg bg-red-600 hover:bg-red-700 text-sm font-semibold text-white transition">
            Back to sign in
          </router-link>
        </div>

        <!-- Form state -->
        <div v-else>
          <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-800">Create your account</h2>
            <p class="text-sm text-gray-500 mt-1">Use your <span class="font-medium">{{ domainHint }}</span> email address.</p>
          </div>

          <form @submit.prevent="submit" class="space-y-4">
            <!-- Name -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Name <span class="text-red-500">*</span></label>
              <input v-model.trim="form.name" type="text" required autofocus placeholder="Name"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                :class="fieldError('name') ? 'border-red-400 bg-red-50' : ''" />
              <p v-if="fieldError('name')" class="text-xs text-red-600 mt-1.5">{{ fieldError('name') }}</p>
            </div>

            <!-- Email -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Email <span class="text-red-500">*</span></label>
              <input v-model.trim="form.email" type="email" required :placeholder="`user@${domains[0] ?? 'company.com'}`"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                :class="fieldError('email') ? 'border-red-400 bg-red-50' : ''" />
              <p v-if="fieldError('email')" class="text-xs text-red-600 mt-1.5">{{ fieldError('email') }}</p>
            </div>

            <!-- Password -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Password <span class="text-red-500">*</span></label>
              <div class="relative">
                <input v-model="form.password" :type="showPassword ? 'text' : 'password'" required minlength="8"
                  placeholder="At least 8 characters"
                  class="w-full px-4 py-2.5 pr-12 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
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

            <!-- Department -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Department <span class="text-red-500">*</span></label>
              <select v-model="form.department_id" required
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                :class="fieldError('department_id') ? 'border-red-400 bg-red-50' : ''">
                <option :value="null" disabled>—</option>
                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>
              <p v-if="fieldError('department_id')" class="text-xs text-red-600 mt-1.5">{{ fieldError('department_id') }}</p>
            </div>

            <!-- Generic error -->
            <p v-if="genericError" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
              {{ genericError }}
            </p>

            <button type="submit" :disabled="loading"
              class="w-full py-2.5 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 disabled:opacity-50 transition flex items-center justify-center gap-2">
              <div v-if="loading" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
              {{ loading ? 'Creating…' : 'Create Account' }}
            </button>
          </form>

          <div class="mt-6 text-center text-sm text-gray-500">
            Already have an account?
            <router-link to="/login" class="text-red-600 font-medium hover:text-red-700 transition">Sign in</router-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { authApi } from '@/api'

interface DeptOption { id: number; name: string; name_zh: string }

const form = reactive({
  name: '',
  email: '',
  password: '',
  department_id: null as number | null,
})

const departments  = ref<DeptOption[]>([])
const domains      = ref<string[]>(['segsolar.com'])
const loading      = ref(false)
const showPassword = ref(false)
const sent         = ref(false)
const errors       = ref<Record<string, string[]>>({})
const genericError = ref('')

const domainHint = computed(() =>
  domains.value.map(d => `@${d}`).join(' or ')
)

onMounted(async () => {
  try {
    const { data } = await authApi.registerDepartments()
    departments.value = data.departments ?? []
    if (Array.isArray(data.domains) && data.domains.length) domains.value = data.domains
  } catch {
    genericError.value = 'Could not load departments. Please refresh the page.'
  }
})

function fieldError(field: string): string {
  return errors.value[field]?.[0] ?? ''
}

async function submit() {
  errors.value = {}
  genericError.value = ''

  const email = form.email.toLowerCase()
  if (!domains.value.some(d => email.endsWith(`@${d}`))) {
    errors.value = { email: [`Email must end with ${domainHint.value}`] }
    return
  }
  if (form.department_id === null) {
    errors.value = { department_id: ['Please select a department.'] }
    return
  }

  loading.value = true
  try {
    await authApi.register({
      name: form.name,
      email: form.email,
      password: form.password,
      department_id: form.department_id,
    })
    sent.value = true
  } catch (e: any) {
    const status = e?.response?.status
    const data   = e?.response?.data
    if (status === 422 && data?.errors) {
      errors.value = data.errors
    } else {
      genericError.value = data?.message || 'Something went wrong. Please try again.'
    }
  } finally {
    loading.value = false
  }
}
</script>
