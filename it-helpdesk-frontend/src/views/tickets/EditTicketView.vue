<template>
  <div class="max-w-2xl mx-auto">

    <div v-if="loadingTicket" class="flex items-center justify-center py-20 text-gray-400">
      <div class="w-8 h-8 border-4 border-red-600 border-t-transparent rounded-full animate-spin mr-3"></div>
      {{ t('common.loading') }}
    </div>

    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
      <h2 class="text-lg font-semibold text-gray-800 mb-1">{{ t('ticket.actions.edit') }}</h2>
      <p class="text-sm text-gray-400 font-mono mb-6">{{ ticketNumber }}</p>

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

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('ticket.priority') }} *</label>
          <select v-model="form.priority" required
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
            <option value="low">{{ t('ticket.low') }}</option>
            <option value="medium">{{ t('ticket.medium') }}</option>
            <option value="high">{{ t('ticket.high') }}</option>
            <option value="critical">{{ t('ticket.critical') }}</option>
          </select>
        </div>

        <CategoryPicker v-model:category="form.category" v-model:subcategory="form.subcategory" />

        <div v-if="error" class="p-3 bg-red-50 border border-red-200 text-red-600 rounded-lg text-sm">
          {{ error }}
        </div>

        <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-2">
          <router-link :to="`/tickets/${ticketId}`"
            class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition text-center">
            {{ t('common.cancel') }}
          </router-link>
          <button type="submit" :disabled="loading"
            class="px-6 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition disabled:opacity-50">
            {{ loading ? t('common.loading') : t('common.save') }}
          </button>
        </div>
      </form>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ticketApi } from '@/api'
import CategoryPicker from '@/components/tickets/CategoryPicker.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const ticketId = Number(route.params.id)
const loadingTicket = ref(true)
const loading = ref(false)
const error = ref('')
const ticketNumber = ref('')

const form = reactive({
  title: '',
  description: '',
  priority: 'medium',
  category: '',
  subcategory: ''
})

async function loadTicket() {
  try {
    const { data } = await ticketApi.get(ticketId)
    ticketNumber.value = data.ticket_number
    form.title = data.title
    form.description = data.description
    form.priority = data.priority
    form.category = data.category ?? ''
    form.subcategory = data.subcategory ?? ''
  } catch {
    error.value = t('common.error')
  } finally {
    loadingTicket.value = false
  }
}

async function submit() {
  loading.value = true
  error.value = ''
  try {
    await ticketApi.update(ticketId, {
      title: form.title,
      description: form.description,
      priority: form.priority,
      category: form.category || null,
      subcategory: form.subcategory || null,
    })
    router.push(`/tickets/${ticketId}`)
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('common.error')
  } finally {
    loading.value = false
  }
}

onMounted(loadTicket)
</script>
