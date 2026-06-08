<template>
  <div v-if="!ticket && loading" class="flex items-center justify-center py-20 text-gray-400">
    <div class="w-8 h-8 border-4 border-red-600 border-t-transparent rounded-full animate-spin mr-3"></div>
    {{ t('common.loading') }}
  </div>

  <div v-else-if="ticket" class="max-w-5xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-4">

    <!-- Main content (left 2/3) -->
    <div class="lg:col-span-2 space-y-4">

      <!-- Ticket info -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
        <div class="flex items-start justify-between mb-4 gap-3">
          <div class="min-w-0">
            <p class="text-sm text-gray-400 font-mono mb-1">{{ ticket.ticket_number }}</p>
            <h2 class="text-lg sm:text-xl font-semibold text-gray-900">{{ ticket.title }}</h2>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0 flex-wrap justify-end">
            <span class="px-2 py-1 rounded-full text-xs font-medium" :class="statusClass(ticket.status)">
              {{ t(`ticket.${ticket.status}`) }}
            </span>
            <span v-if="ticket.sla_resolution_breached" class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded-full">
              {{ t('common.breached') }}
            </span>
          </div>
        </div>
        <p class="text-gray-700 whitespace-pre-wrap text-sm sm:text-base">{{ ticket.description }}</p>
      </div>

      <!-- Attachments -->
      <div v-if="ticket.attachments && ticket.attachments.length"
        class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
        <h3 class="font-semibold text-gray-700 mb-4">{{ t('ticket.attachments') }}</h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <a v-for="att in ticket.attachments" :key="att.id"
            :href="att.url" target="_blank" rel="noopener noreferrer"
            class="group block rounded-lg border border-gray-200 overflow-hidden hover:border-red-300 transition">
            <img v-if="att.mime_type.startsWith('image/')" :src="att.url" :alt="att.original_name"
              class="w-full h-28 sm:h-32 object-cover" />
            <div v-else class="w-full h-28 sm:h-32 flex flex-col items-center justify-center bg-gray-50 p-3 gap-2">
              <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
              <span class="text-xs text-gray-500 text-center truncate w-full px-1">{{ att.original_name }}</span>
            </div>
            <div class="px-2 py-1.5 bg-white border-t border-gray-100">
              <p class="text-xs text-gray-500 truncate">{{ att.original_name }}</p>
            </div>
          </a>
        </div>
      </div>

      <!-- Comments -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
        <h3 class="font-semibold text-gray-700 mb-4">{{ t('comment.title') }}</h3>

        <div v-if="!comments.length" class="text-sm text-gray-400 text-center py-6">
          {{ t('comment.noComments') }}
        </div>

        <div class="space-y-4 mb-6">
          <div v-for="comment in comments" :key="comment.id"
            class="flex gap-3"
            :class="comment.is_internal ? 'opacity-75' : ''">
            <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center text-sm font-bold text-red-700 flex-shrink-0">
              {{ comment.user.name[0]?.toUpperCase() }}
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1 flex-wrap">
                <span class="text-sm font-medium text-gray-800">{{ comment.user.name }}</span>
                <span v-if="comment.is_internal" class="text-xs bg-yellow-100 text-yellow-700 px-1.5 py-0.5 rounded">
                  Internal
                </span>
                <span class="text-xs text-gray-400">{{ formatDateTime(comment.created_at) }}</span>
              </div>
              <div class="bg-gray-50 rounded-lg p-3 text-sm text-gray-700 whitespace-pre-wrap">{{ comment.body }}</div>
            </div>
          </div>
        </div>

        <!-- Comment form -->
        <div class="border-t pt-4">
          <textarea v-model="newComment" :placeholder="t('comment.placeholder')" rows="3"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500 resize-none mb-2" />
          <div class="flex items-center justify-between gap-2">
            <label v-if="auth.isItStaff" class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
              <input v-model="isInternal" type="checkbox" class="rounded" />
              <span class="text-xs sm:text-sm">{{ t('comment.internal') }}</span>
            </label>
            <div v-else></div>
            <button @click="submitComment" :disabled="!newComment.trim() || submitting"
              class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition disabled:opacity-50 flex-shrink-0">
              {{ t('comment.send') }}
            </button>
          </div>
        </div>
      </div>

      <!-- History -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
        <h3 class="font-semibold text-gray-700 mb-4">History</h3>
        <div class="space-y-2">
          <div v-for="h in ticket.histories" :key="h.id"
            class="flex items-start sm:items-center gap-3 text-xs sm:text-sm text-gray-500 flex-wrap">
            <div class="w-1.5 h-1.5 rounded-full bg-gray-300 mt-1.5 sm:mt-0 flex-shrink-0"></div>
            <span class="font-medium text-gray-700">{{ h.user.name }}</span>
            <span>{{ h.action }}</span>
            <span v-if="h.field" class="text-gray-400">{{ h.old_value }} → {{ h.new_value }}</span>
            <span class="ml-auto text-xs text-gray-400">{{ formatDateTime(h.created_at) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Sidebar (right 1/3) -->
    <div class="space-y-4">

      <!-- Details -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-5">
        <h3 class="font-semibold text-gray-700 mb-4">Details</h3>
        <dl class="space-y-3 text-sm">
          <div>
            <dt class="text-gray-500">{{ t('ticket.priority') }}</dt>
            <dd class="mt-0.5">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="priorityClass(ticket.priority)">
                {{ t(`ticket.${ticket.priority}`) }}
              </span>
            </dd>
          </div>
          <div v-if="ticket.category">
            <dt class="text-gray-500">{{ t('ticket.category') }}</dt>
            <dd class="text-gray-800 font-medium text-sm mt-0.5">
              {{ categoryEmoji }} {{ getCategoryLabel(ticket.category, locale) }}
            </dd>
            <dd v-if="ticket.subcategory" class="text-gray-500 text-xs mt-0.5 pl-4">
              ↳ {{ getSubCategoryLabel(ticket.category, ticket.subcategory, locale) }}
            </dd>
          </div>
          <div>
            <dt class="text-gray-500">{{ t('ticket.department') }}</dt>
            <dd class="text-gray-800 font-medium">
              {{ locale === 'zh' ? ticket.department?.name_zh : ticket.department?.name }}
            </dd>
          </div>
          <div>
            <dt class="text-gray-500">{{ t('ticket.createdBy') }}</dt>
            <dd class="text-gray-800 font-medium">{{ ticket.creator?.name }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">{{ t('ticket.createdAt') }}</dt>
            <dd class="text-gray-800">{{ formatDateTime(ticket.created_at) }}</dd>
          </div>
          <div v-if="ticket.sla_resolution_due_at">
            <dt class="text-gray-500">{{ t('ticket.slaDeadline') }}</dt>
            <dd class="font-medium" :class="ticket.sla_resolution_breached ? 'text-red-600' : 'text-gray-800'">
              {{ formatDateTime(ticket.sla_resolution_due_at) }}
            </dd>
          </div>
        </dl>
      </div>

      <!-- IT Actions (staff only) -->
      <div v-if="auth.isItStaff" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-5">
        <h3 class="font-semibold text-gray-700 mb-4">Actions</h3>

        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('ticket.assignee') }}</label>
          <select v-model="assignedTo" @change="handleAssign"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
            <option :value="null">{{ t('ticket.unassigned') }}</option>
            <option v-for="staff in itStaff" :key="staff.id" :value="staff.id">{{ staff.name }}</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('ticket.status') }}</label>
          <div class="flex flex-col gap-2">
            <button v-for="s in availableStatuses" :key="s" @click="handleStatusChange(s)"
              class="w-full px-3 py-2 border rounded-lg text-sm font-medium transition text-left"
              :class="ticket.status === s
                ? 'border-red-500 bg-red-50 text-red-700'
                : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50 text-gray-700'">
              {{ t(`ticket.${s}`) }}
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useTicketStore } from '@/stores/tickets'
import { useAuthStore } from '@/stores/auth'
import { userApi, commentApi } from '@/api'
import type { Ticket, Comment } from '@/stores/tickets'
import { CATEGORIES, getCategoryLabel, getSubCategoryLabel } from '@/constants/categories'

const { t, locale } = useI18n()
const route = useRoute()
const ticketStore = useTicketStore()
const auth = useAuthStore()

const loading = ref(true)
const ticket = computed(() => ticketStore.currentTicket)
const comments = ref<Comment[]>([])
const itStaff = ref<any[]>([])
const newComment = ref('')
const isInternal = ref(false)
const submitting = ref(false)
const assignedTo = ref<number | null>(null)

const statuses = ['open', 'in_progress', 'pending', 'resolved', 'closed']
const availableStatuses = computed(() => statuses.filter(s => s !== ticket.value?.status))
const categoryEmoji = computed(() =>
  CATEGORIES.find(c => c.id === ticket.value?.category)?.emoji ?? ''
)

async function loadComments() {
  const { data } = await commentApi.list(Number(route.params.id))
  comments.value = data
}

async function submitComment() {
  if (!newComment.value.trim()) return
  submitting.value = true
  try {
    const c = await ticketStore.addComment(Number(route.params.id), newComment.value, isInternal.value)
    comments.value.push(c)
    newComment.value = ''
    isInternal.value = false
  } finally {
    submitting.value = false
  }
}

async function handleAssign() {
  await ticketStore.assignTicket(Number(route.params.id), assignedTo.value)
}

async function handleStatusChange(status: string) {
  await ticketStore.updateStatus(Number(route.params.id), status)
}

function statusClass(s: string) {
  const map: Record<string, string> = {
    open: 'bg-sky-100 text-sky-700', in_progress: 'bg-yellow-100 text-yellow-700',
    pending: 'bg-purple-100 text-purple-700', resolved: 'bg-green-100 text-green-700',
    closed: 'bg-gray-100 text-gray-600'
  }
  return map[s] || 'bg-gray-100'
}

function priorityClass(p: string) {
  const map: Record<string, string> = {
    critical: 'bg-red-100 text-red-700', high: 'bg-orange-100 text-orange-700',
    medium: 'bg-yellow-100 text-yellow-700', low: 'bg-gray-100 text-gray-600'
  }
  return map[p] || 'bg-gray-100'
}

function formatDateTime(dt: string) {
  return new Date(dt).toLocaleString()
}

onMounted(async () => {
  try {
    await ticketStore.fetchTicket(Number(route.params.id))
    assignedTo.value = ticket.value?.assigned_to ?? null
    await loadComments()
    if (auth.isItStaff) {
      const { data } = await userApi.itStaff()
      itStaff.value = data
    }
  } finally {
    loading.value = false
  }
})
</script>
