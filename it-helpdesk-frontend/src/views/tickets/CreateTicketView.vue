<template>
  <div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
      <h2 class="text-lg font-semibold text-gray-800 mb-6">{{ t('ticket.actions.create') }}</h2>

      <form @submit.prevent="submit" class="space-y-5">

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('ticket.title') }} *</label>
          <input v-model="form.title" required :placeholder="t('ticket.title')"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('ticket.description') }} *</label>
          <textarea v-model="form.description" required rows="5" :placeholder="t('ticket.description')"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 resize-none" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('ticket.priority') }} *</label>
            <select v-model="form.priority" required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
              <option value="low">{{ t('ticket.low') }}</option>
              <option value="medium" selected>{{ t('ticket.medium') }}</option>
              <option value="high">{{ t('ticket.high') }}</option>
              <option value="critical">{{ t('ticket.critical') }}</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('ticket.department') }} *</label>
            <select v-model="form.department_id" required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
              <option value="" disabled>Select department...</option>
              <option v-for="d in departments" :key="d.id" :value="d.id">
                {{ locale === 'zh' ? d.name_zh : d.name }}
              </option>
            </select>
          </div>
        </div>

        <CategoryPicker v-model:category="form.category" v-model:subcategory="form.subcategory" />

        <!-- Photo / File Attachments -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            {{ t('ticket.attachments') }}
            <span class="text-gray-400 font-normal text-xs ml-1">({{ t('ticket.maxFiles') }})</span>
          </label>

          <!-- Hidden inputs -->
          <input ref="fileInputRef" type="file" accept="image/*,application/pdf" multiple
            class="hidden" @change="handleFiles($event)" />
          <input ref="cameraInputRef" type="file" accept="image/*" capture="environment"
            class="hidden" @change="handleFiles($event)" />

          <!-- Upload buttons -->
          <div class="flex flex-wrap gap-2 mb-3">
            <button type="button" @click="fileInputRef?.click()"
              :disabled="attachments.length >= 5"
              class="flex items-center gap-2 px-4 py-2.5 border-2 border-dashed border-gray-300 rounded-xl text-sm text-gray-600 hover:border-red-400 hover:text-red-600 transition disabled:opacity-40 disabled:cursor-not-allowed">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
              {{ t('ticket.chooseFiles') }}
            </button>
            <button type="button" @click="cameraInputRef?.click()"
              :disabled="attachments.length >= 5"
              class="flex items-center gap-2 px-4 py-2.5 border-2 border-dashed border-gray-300 rounded-xl text-sm text-gray-600 hover:border-red-400 hover:text-red-600 transition disabled:opacity-40 disabled:cursor-not-allowed">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
              {{ t('ticket.takePhoto') }}
            </button>
          </div>

          <!-- Preview grid -->
          <div v-if="attachments.length" class="grid grid-cols-3 sm:grid-cols-5 gap-2">
            <div v-for="(file, i) in attachments" :key="i" class="relative group">
              <img v-if="previews[i]" :src="previews[i]" :alt="file.name"
                class="w-full h-20 sm:h-24 object-cover rounded-lg border border-gray-200" />
              <div v-else
                class="w-full h-20 sm:h-24 flex flex-col items-center justify-center bg-gray-50 rounded-lg border border-gray-200 p-2 gap-1">
                <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span class="text-xs text-gray-500 truncate w-full text-center px-1">{{ file.name }}</span>
              </div>
              <button type="button" @click="removeFile(i)"
                class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-600 text-white rounded-full text-xs flex items-center justify-center shadow-sm opacity-0 group-hover:opacity-100 focus:opacity-100 transition">
                ×
              </button>
            </div>
          </div>
        </div>

        <div v-if="error" class="p-3 bg-red-50 border border-red-200 text-red-600 rounded-lg text-sm">
          {{ error }}
        </div>

        <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-2">
          <router-link to="/tickets"
            class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition text-center">
            {{ t('common.cancel') }}
          </router-link>
          <button type="submit" :disabled="loading"
            class="px-6 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition disabled:opacity-50">
            {{ loading ? t('common.loading') : t('ticket.actions.create') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useTicketStore } from '@/stores/tickets'
import { useAuthStore } from '@/stores/auth'
import { departmentApi } from '@/api'
import CategoryPicker from '@/components/tickets/CategoryPicker.vue'

const { t, locale } = useI18n()
const router = useRouter()
const ticketStore = useTicketStore()
const auth = useAuthStore()

const departments = ref<any[]>([])
const loading = ref(false)
const error = ref('')

const form = reactive({
  title: '',
  description: '',
  priority: 'medium',
  department_id: auth.user?.department_id || '',
  category: '',
  subcategory: ''
})

const fileInputRef = ref<HTMLInputElement>()
const cameraInputRef = ref<HTMLInputElement>()
const attachments = ref<File[]>([])
const previews = ref<string[]>([])

function handleFiles(e: Event) {
  const input = e.target as HTMLInputElement
  const files = Array.from(input.files || [])
  for (const file of files) {
    if (attachments.value.length >= 5) break
    attachments.value.push(file)
    if (file.type.startsWith('image/')) {
      const reader = new FileReader()
      reader.onload = ev => previews.value.push(ev.target?.result as string)
      reader.readAsDataURL(file)
    } else {
      previews.value.push('')
    }
  }
  input.value = ''
}

function removeFile(i: number) {
  attachments.value.splice(i, 1)
  previews.value.splice(i, 1)
}

async function submit() {
  loading.value = true
  error.value = ''
  try {
    const fd = new FormData()
    fd.append('title', form.title)
    fd.append('description', form.description)
    fd.append('priority', form.priority)
    fd.append('department_id', String(form.department_id))
    if (form.category) fd.append('category', form.category)
    if (form.subcategory) fd.append('subcategory', form.subcategory)
    attachments.value.forEach(f => fd.append('attachments[]', f))

    await ticketStore.createTicket(fd)
    router.replace('/tickets')
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('common.error')
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  const { data } = await departmentApi.list()
  departments.value = data
})
</script>
