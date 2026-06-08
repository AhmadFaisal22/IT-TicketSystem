<template>
  <div>
    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:flex lg:flex-wrap gap-3">
        <input v-model="filters.search" @input="debouncedFetch" :placeholder="t('ticket.search')"
          class="col-span-1 sm:col-span-2 lg:flex-1 lg:min-w-48 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />

        <select v-model="filters.status" @change="fetchData"
          class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
          <option value="">{{ t('common.all') }} {{ t('ticket.status') }}</option>
          <option v-for="s in statuses" :key="s" :value="s">{{ t(`ticket.${s}`) }}</option>
        </select>

        <select v-model="filters.priority" @change="fetchData"
          class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
          <option value="">{{ t('common.all') }} {{ t('ticket.priority') }}</option>
          <option v-for="p in priorities" :key="p" :value="p">{{ t(`ticket.${p}`) }}</option>
        </select>

        <select v-if="auth.isItStaff" v-model="filters.department_id" @change="fetchData"
          class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
          <option value="">{{ t('common.all') }} {{ t('ticket.department') }}</option>
          <option v-for="d in departments" :key="d.id" :value="d.id">
            {{ locale === 'zh' ? d.name_zh : d.name }}
          </option>
        </select>
      </div>
    </div>

    <!-- Tickets list -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

      <!-- Loading -->
      <div v-if="ticketStore.loading" class="p-8 text-center text-gray-400">
        <div class="w-8 h-8 border-4 border-red-600 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
        {{ t('common.loading') }}
      </div>

      <!-- Empty state -->
      <div v-else-if="!ticketStore.tickets.length" class="p-12 text-center">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <p class="text-gray-400 mb-3">{{ t('ticket.noTickets') }}</p>
        <router-link to="/tickets/create"
          class="text-red-600 hover:text-red-800 text-sm font-medium">{{ t('ticket.createFirst') }}</router-link>
      </div>

      <template v-else>
        <!-- Mobile cards -->
        <div class="md:hidden divide-y divide-gray-100">
          <div v-for="ticket in ticketStore.tickets" :key="ticket.id"
            @click="$router.push(`/tickets/${ticket.id}`)"
            class="p-4 hover:bg-gray-50 active:bg-gray-100 cursor-pointer transition">
            <div class="flex items-start justify-between gap-2 mb-1.5">
              <span class="text-xs font-mono text-red-600 shrink-0">{{ ticket.ticket_number }}</span>
              <div class="flex items-center gap-1 shrink-0">
                <span v-if="ticket.sla_resolution_breached"
                  class="px-1.5 py-0.5 text-xs bg-red-100 text-red-700 rounded font-medium">SLA</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="priorityClass(ticket.priority)">
                  {{ t(`ticket.${ticket.priority}`) }}
                </span>
              </div>
            </div>
            <p class="text-sm font-medium text-gray-800 mb-2 leading-snug">{{ ticket.title }}</p>
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs mb-2">
              <span class="px-2 py-0.5 rounded-full font-medium" :class="statusClass(ticket.status)">
                {{ t(`ticket.${ticket.status}`) }}
              </span>
              <span class="text-gray-300">•</span>
              <span class="text-gray-500">{{ locale === 'zh' ? ticket.department?.name_zh : ticket.department?.name }}</span>
              <span class="text-gray-300">•</span>
              <span class="text-gray-400">{{ formatDate(ticket.created_at) }}</span>
            </div>
            <!-- Mobile action buttons -->
            <div class="flex gap-2" @click.stop>
              <router-link v-if="canEdit(ticket)" :to="`/tickets/${ticket.id}/edit`"
                class="flex items-center gap-1 px-2.5 py-1 text-xs text-gray-600 bg-gray-100 hover:bg-blue-50 hover:text-blue-600 rounded-lg transition">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                {{ t('common.edit') }}
              </router-link>
              <button v-if="auth.isAdmin" @click.stop="handleDelete(ticket)"
                class="flex items-center gap-1 px-2.5 py-1 text-xs text-gray-600 bg-gray-100 hover:bg-red-50 hover:text-red-600 rounded-lg transition">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                {{ t('common.delete') }}
              </button>
            </div>
          </div>
        </div>

        <!-- Desktop table -->
        <table class="w-full hidden md:table">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('ticket.ticketNumber') }}</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('ticket.title') }}</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('ticket.status') }}</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('ticket.priority') }}</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('ticket.department') }}</th>
              <th v-if="auth.isItStaff" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('ticket.assignee') }}</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('ticket.createdAt') }}</th>
              <th class="px-4 py-3 w-20"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="ticket in ticketStore.tickets" :key="ticket.id"
              @click="$router.push(`/tickets/${ticket.id}`)"
              class="hover:bg-gray-50 cursor-pointer transition">
              <td class="px-4 py-3 text-sm font-mono text-red-600">{{ ticket.ticket_number }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <span class="text-sm font-medium text-gray-800">{{ ticket.title }}</span>
                  <span v-if="ticket.sla_resolution_breached" class="px-1.5 py-0.5 text-xs bg-red-100 text-red-700 rounded">SLA</span>
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="px-2 py-1 rounded-full text-xs font-medium" :class="statusClass(ticket.status)">
                  {{ t(`ticket.${ticket.status}`) }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span class="px-2 py-1 rounded-full text-xs font-medium" :class="priorityClass(ticket.priority)">
                  {{ t(`ticket.${ticket.priority}`) }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600">
                {{ locale === 'zh' ? ticket.department?.name_zh : ticket.department?.name }}
              </td>
              <td v-if="auth.isItStaff" class="px-4 py-3 text-sm text-gray-600">
                {{ ticket.assignee?.name || t('ticket.unassigned') }}
              </td>
              <td class="px-4 py-3 text-sm text-gray-400">{{ formatDate(ticket.created_at) }}</td>

              <!-- Actions -->
              <td class="px-3 py-3" @click.stop>
                <div class="flex items-center gap-1 justify-end">
                  <router-link v-if="canEdit(ticket)" :to="`/tickets/${ticket.id}/edit`"
                    class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
                    :title="t('ticket.actions.edit')">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                  </router-link>
                  <button v-if="auth.isAdmin" @click="handleDelete(ticket)"
                    class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                    :title="t('ticket.actions.delete')">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </template>

      <!-- Pagination -->
      <div v-if="ticketStore.pagination.last_page > 1"
        class="px-4 py-3 border-t flex items-center justify-between text-sm text-gray-600">
        <span>{{ ticketStore.pagination.total }} total</span>
        <div class="flex gap-1 sm:gap-2 flex-wrap">
          <button v-for="page in ticketStore.pagination.last_page" :key="page"
            @click="goToPage(page)"
            class="w-8 h-8 rounded-lg text-sm transition"
            :class="page === ticketStore.pagination.current_page ? 'bg-red-600 text-white' : 'hover:bg-gray-100'">
            {{ page }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTicketStore } from '@/stores/tickets'
import { useAuthStore } from '@/stores/auth'
import { departmentApi, ticketApi } from '@/api'
import { useDebounceFn } from '@vueuse/core'
import type { Ticket } from '@/stores/tickets'

const { t, locale } = useI18n()
const ticketStore = useTicketStore()
const auth = useAuthStore()

const departments = ref<any[]>([])
const currentPage = ref(1)

const statuses = ['open', 'in_progress', 'pending', 'resolved', 'closed']
const priorities = ['critical', 'high', 'medium', 'low']

const filters = reactive({
  search: '',
  status: '',
  priority: '',
  department_id: ''
})

function fetchData() {
  ticketStore.fetchTickets({ ...filters, page: currentPage.value })
}

const debouncedFetch = useDebounceFn(fetchData, 350)

function goToPage(page: number) {
  currentPage.value = page
  fetchData()
}

function canEdit(ticket: Ticket): boolean {
  return auth.isItStaff || ticket.created_by === auth.user?.id
}

async function handleDelete(ticket: Ticket) {
  if (!confirm(`Delete ${ticket.ticket_number}? This cannot be undone.`)) return
  try {
    await ticketApi.delete(ticket.id)
    fetchData()
  } catch (e: any) {
    alert(e?.response?.data?.message || 'Failed to delete ticket.')
  }
}

function statusClass(s: string) {
  const map: Record<string, string> = {
    open: 'bg-sky-100 text-sky-700',
    in_progress: 'bg-yellow-100 text-yellow-700',
    pending: 'bg-purple-100 text-purple-700',
    resolved: 'bg-green-100 text-green-700',
    closed: 'bg-gray-100 text-gray-600'
  }
  return map[s] || 'bg-gray-100 text-gray-600'
}

function priorityClass(p: string) {
  const map: Record<string, string> = {
    critical: 'bg-red-100 text-red-700',
    high: 'bg-orange-100 text-orange-700',
    medium: 'bg-yellow-100 text-yellow-700',
    low: 'bg-gray-100 text-gray-600'
  }
  return map[p] || 'bg-gray-100 text-gray-600'
}

function formatDate(dt: string) {
  return new Date(dt).toLocaleDateString()
}

onMounted(async () => {
  fetchData()
  const { data } = await departmentApi.list()
  departments.value = data
})
</script>
