<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <p class="text-sm text-gray-500">{{ t('myTasks.subtitle') }}</p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="p-8 text-center text-gray-400">
      <div class="w-8 h-8 border-4 border-red-600 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
      {{ t('common.loading') }}
    </div>

    <template v-else>
      <!-- Error -->
      <div v-if="error" class="mb-3 p-3 bg-red-50 border border-red-200 text-red-600 rounded-lg text-sm">
        {{ error }}
      </div>

      <!-- Empty -->
      <div v-if="!tickets.length" class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <p class="text-gray-400">{{ t('myTasks.empty') }}</p>
      </div>

      <!-- Board -->
      <div v-else class="flex items-start gap-4 overflow-x-auto pb-2">
        <div v-for="col in columns" :key="col.key"
          class="flex-shrink-0 w-72 bg-gray-50 rounded-xl border border-gray-100">
          <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <span class="text-sm font-semibold text-gray-700">{{ t(col.labelKey) }}</span>
            <span class="text-xs text-gray-400">{{ col.tickets.length }}</span>
          </div>
          <div class="p-2 space-y-2 min-h-[60px]"
            @dragover.prevent @drop="onDrop(col.key)">
            <div v-for="ticket in col.tickets" :key="ticket.id"
              draggable="true"
              @dragstart="onDragStart(ticket)"
              @dragend="draggingId = null"
              @click="router.push(`/tickets/${ticket.id}`)"
              :class="['bg-white rounded-lg border border-gray-200 p-3 cursor-pointer select-none hover:border-red-300 hover:shadow-sm transition', draggingId === ticket.id ? 'opacity-50' : '']">
              <div class="flex items-start justify-between gap-2 mb-1.5">
                <span class="text-xs font-mono text-red-600">{{ ticket.ticket_number }}</span>
                <div class="flex items-center gap-1">
                  <span v-if="ticket.sla_resolution_breached"
                    class="px-1.5 py-0.5 text-xs bg-red-100 text-red-700 rounded font-medium">SLA</span>
                  <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="priorityClass(ticket.priority)">
                    {{ t(`ticket.${ticket.priority}`) }}
                  </span>
                </div>
              </div>
              <p class="text-sm font-medium text-gray-800 mb-2 leading-snug">{{ ticket.title }}</p>
              <select :value="ticket.status" @click.stop @change="onStatusSelect(ticket, $event)"
                class="w-full px-2 py-1 border border-gray-200 rounded text-xs text-gray-600 focus:outline-none focus:ring-2 focus:ring-red-500">
                <option v-for="s in statusOptions" :key="s" :value="s">{{ t(`ticket.${s}`) }}</option>
              </select>
            </div>
            <p v-if="!col.tickets.length" class="text-xs text-gray-300 text-center py-4">-</p>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ticketApi } from '@/api'
import { useAuthStore } from '@/stores/auth'
import type { Ticket } from '@/stores/tickets'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

const tickets = ref<Ticket[]>([])
const loading = ref(false)
const error = ref('')
const draggingId = ref<number | null>(null)

const statusOptions = ['open', 'in_progress', 'pending', 'resolved', 'closed'] as const

const columns = computed(() => [
  { key: 'open', labelKey: 'ticket.open', tickets: tickets.value.filter(tk => tk.status === 'open') },
  { key: 'in_progress', labelKey: 'ticket.in_progress', tickets: tickets.value.filter(tk => tk.status === 'in_progress') },
  { key: 'pending', labelKey: 'ticket.pending', tickets: tickets.value.filter(tk => tk.status === 'pending') },
  {
    key: 'done',
    labelKey: 'myTasks.done',
    tickets: tickets.value.filter(tk => tk.status === 'resolved' || tk.status === 'closed'),
  },
])

function priorityClass(p: string) {
  const map: Record<string, string> = {
    critical: 'bg-red-100 text-red-700',
    high: 'bg-orange-100 text-orange-700',
    medium: 'bg-yellow-100 text-yellow-700',
    low: 'bg-gray-100 text-gray-600',
  }
  return map[p] || 'bg-gray-100 text-gray-600'
}

function onStatusSelect(ticket: Ticket, e: Event) {
  changeStatus(ticket, (e.target as HTMLSelectElement).value)
}

function onDragStart(ticket: Ticket) {
  draggingId.value = ticket.id
}

function onDrop(columnKey: string) {
  const ticket = tickets.value.find(tk => tk.id === draggingId.value)
  draggingId.value = null
  if (!ticket) return
  // The Done column groups resolved+closed; a drop there resolves the ticket.
  changeStatus(ticket, columnKey === 'done' ? 'resolved' : columnKey)
}

async function changeStatus(ticket: Ticket, newStatus: string) {
  if (ticket.status === newStatus) return
  const old = ticket.status
  ticket.status = newStatus as Ticket['status'] // optimistic
  error.value = ''
  try {
    const { data } = await ticketApi.updateStatus(ticket.id, newStatus)
    Object.assign(ticket, data) // sync server-updated fields (resolved_at, sla, etc.)
  } catch (err: any) {
    ticket.status = old // revert
    error.value = err?.response?.data?.message || t('common.error')
  }
}

async function fetchTasks() {
  if (!auth.user?.id) return
  loading.value = true
  error.value = ''
  try {
    const { data } = await ticketApi.list({ assigned_to: auth.user.id, per_page: 100 })
    tickets.value = data.data
  } catch (err: any) {
    error.value = err?.response?.data?.message || t('common.error')
  } finally {
    loading.value = false
  }
}

onMounted(fetchTasks)
</script>
