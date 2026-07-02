<template>
  <div v-if="!ticket && loading" class="flex items-center justify-center py-20 text-gray-400">
    <div class="w-8 h-8 border-4 border-brand-600 border-t-transparent rounded-full animate-spin mr-3"></div>
    {{ t('common.loading') }}
  </div>

  <div v-else-if="notFound" class="flex flex-col items-center justify-center py-20 text-center">
    <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
        d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <p class="text-gray-500 mb-4">{{ t('common.ticketNotFound') }}</p>
    <router-link to="/tickets"
      class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition">
      {{ t('common.backToTickets') }}
    </router-link>
  </div>

  <div v-else-if="ticket" class="max-w-5xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-4">
    <!-- Back -->
    <div class="lg:col-span-3 -mb-1">
      <button @click="goBack" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        ← {{ t('common.backToTickets') }}
      </button>
    </div>

    <!-- Main content (left 2/3) -->
    <div class="lg:col-span-2 space-y-4">

      <!-- Ticket info -->
      <div class="bg-white rounded-card shadow-soft border border-gray-100 p-4 sm:p-6">
        <div class="flex items-start justify-between mb-4 gap-3">
          <div class="min-w-0">
            <p class="text-sm text-gray-400 font-mono mb-1">{{ ticket.ticket_number }}</p>
            <h2 class="text-lg sm:text-xl font-semibold text-gray-900">{{ ticket.title }}</h2>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0 flex-wrap justify-end">
            <StatusBadge kind="status" :value="ticket.status" />
            <span v-if="ticket.sla_resolution_breached" class="px-2 py-1 text-xs bg-red-50 text-red-700 rounded-full">
              {{ t('common.breached') }}
            </span>
          </div>
        </div>
        <p class="text-gray-700 whitespace-pre-wrap text-sm sm:text-base">{{ ticket.description }}</p>
      </div>

      <!-- Attachments -->
      <div v-if="ticket.attachments && ticket.attachments.length"
        class="bg-white rounded-card shadow-soft border border-gray-100 p-4 sm:p-6">
        <h3 class="font-semibold text-gray-700 mb-4">{{ t('ticket.attachments') }}</h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <button v-for="att in ticket.attachments" :key="att.id" type="button"
            @click="downloadAttachment(att)"
            class="group block w-full text-left rounded-lg border border-gray-200 overflow-hidden hover:border-red-300 transition cursor-pointer">
            <img v-if="att.mime_type.startsWith('image/') && previewUrls[att.id]" :src="previewUrls[att.id]" :alt="att.original_name"
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
          </button>
        </div>
      </div>

      <!-- Comments -->
      <div class="bg-white rounded-card shadow-soft border border-gray-100 p-4 sm:p-6">
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
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 resize-none mb-2" />
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
      <div class="bg-white rounded-card shadow-soft border border-gray-100 p-4 sm:p-6">
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
      <div class="bg-white rounded-card shadow-soft border border-gray-100 p-4 sm:p-5">
        <h3 class="font-semibold text-gray-700 mb-4">Details</h3>
        <dl class="space-y-3 text-sm">
          <div>
            <dt class="text-gray-500">{{ t('ticket.priority') }}</dt>
            <dd class="mt-0.5">
              <StatusBadge kind="priority" :value="ticket.priority" />
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
          <div v-if="ticket.asset">
            <dt class="text-gray-500">{{ t('asset.title') }}</dt>
            <dd class="font-medium">
              <router-link :to="`/assets/${ticket.asset.id}`" class="text-blue-600 hover:underline">
                {{ ticket.asset.asset_tag }}{{ ticket.asset.name ? ` — ${ticket.asset.name}` : '' }}
              </router-link>
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

      <!-- Approval card (shown when ticket is pending_approval or has approvals) -->
      <div v-if="ticket.approvals && ticket.approvals.length"
        class="bg-white rounded-card shadow-soft border border-amber-200 p-4 sm:p-5">
        <h3 class="font-semibold text-gray-700 mb-3 flex items-center gap-2">
          <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
          </svg>
          {{ t('admin.approval.approvalChain') }}
        </h3>

        <!-- Steps timeline -->
        <div class="space-y-2 mb-4">
          <div v-for="approval in ticket.approvals" :key="approval.id"
            class="flex items-start gap-3 text-sm">
            <!-- Status icon -->
            <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
              :class="{
                'bg-green-100': approval.status === 'approved',
                'bg-red-100': approval.status === 'rejected',
                'bg-gray-100': approval.status === 'cancelled',
                'bg-amber-100': approval.status === 'pending',
              }">
              <svg v-if="approval.status === 'approved'" class="w-3.5 h-3.5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
              </svg>
              <svg v-else-if="approval.status === 'rejected'" class="w-3.5 h-3.5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
              </svg>
              <svg v-else-if="approval.status === 'cancelled'" class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
              </svg>
              <div v-else class="w-2 h-2 rounded-full bg-amber-400"></div>
            </div>

            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-medium text-gray-800">{{ approval.approver?.name }}</span>
                <span class="text-xs px-1.5 py-0.5 rounded font-medium"
                  :class="{
                    'bg-green-100 text-green-700': approval.status === 'approved',
                    'bg-red-100 text-red-700': approval.status === 'rejected',
                    'bg-gray-100 text-gray-500': approval.status === 'cancelled',
                    'bg-amber-100 text-amber-700': approval.status === 'pending',
                  }">
                  {{ t(`admin.approval.${approval.status === 'pending' ? 'pendingLabel' : approval.status === 'approved' ? 'approved' : approval.status === 'rejected' ? 'rejectedLabel' : 'cancelledLabel'}`) }}
                </span>
                <span v-if="approval.responded_at" class="text-xs text-gray-400 ml-auto">
                  {{ formatDateTime(approval.responded_at) }}
                </span>
              </div>
              <p v-if="approval.notes" class="text-xs text-gray-500 mt-0.5 italic">{{ approval.notes }}</p>
            </div>
          </div>
        </div>

        <!-- Approve / Reject buttons — only for the current pending approver -->
        <div v-if="isCurrentApprover" class="border-t pt-4 space-y-3">
          <p class="text-xs font-semibold text-amber-700 flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ t('admin.approval.approvalRequired') }}
          </p>
          <textarea v-model="approvalNotes" rows="2"
            :placeholder="t('admin.approval.notesPlaceholder')"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-400 focus:outline-none resize-none" />
          <div v-if="approvalError" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
            {{ approvalError }}
          </div>
          <div class="flex gap-2">
            <button @click="handleApprove" :disabled="approvalLoading"
              class="flex-1 px-3 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 disabled:opacity-50 transition flex items-center justify-center gap-1.5">
              <svg v-if="!approvalLoading" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
              </svg>
              <div v-else class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
              {{ t('admin.approval.approveBtn') }}
            </button>
            <button @click="openRejectModal" :disabled="approvalLoading"
              class="flex-1 px-3 py-2.5 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 disabled:opacity-50 transition flex items-center justify-center gap-1.5">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
              </svg>
              {{ t('admin.approval.rejectBtn') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Reject confirmation modal -->
      <Teleport to="body">
        <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/50" @click="showRejectModal = false"></div>
          <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4">
            <h3 class="font-semibold text-gray-800">{{ t('admin.approval.rejectBtn') }} {{ ticket.ticket_number }}</h3>
            <textarea v-model="rejectNotes" rows="3" :placeholder="t('admin.approval.rejectNotesPlaceholder')"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none resize-none" />
            <div class="flex gap-3">
              <button @click="showRejectModal = false"
                class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                {{ t('common.cancel') }}
              </button>
              <button @click="handleReject" :disabled="!rejectNotes.trim() || approvalLoading"
                class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50">
                {{ t('admin.approval.rejectBtn') }}
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- IT Actions (staff only) -->
      <div v-if="auth.isItStaff" class="bg-white rounded-card shadow-soft border border-gray-100 p-4 sm:p-5">
        <h3 class="font-semibold text-gray-700 mb-4">Actions</h3>

        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('ticket.assignee') }}</label>
          <select v-model="assignedTo" @change="handleAssign"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
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
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useTicketStore } from '@/stores/tickets'
import { useAuthStore } from '@/stores/auth'
import { userApi, commentApi, approvalApi } from '@/api'
import type { Ticket, Comment, TicketApproval } from '@/stores/tickets'
import { CATEGORIES, getCategoryLabel, getSubCategoryLabel } from '@/constants/categories'
import { downloadAttachment, attachmentPreviewUrl } from '@/utils/attachments'
import { backToList } from '@/utils/backToList'
import StatusBadge from '@/components/ui/StatusBadge.vue'

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const ticketStore = useTicketStore()
const auth = useAuthStore()

function goBack() {
  backToList(router, '/tickets')
}

const loading = ref(true)
const notFound = ref(false)
const ticket = computed(() => ticketStore.currentTicket)
let pollTimer: ReturnType<typeof setInterval> | null = null
const comments = ref<Comment[]>([])
const itStaff = ref<any[]>([])
const newComment = ref('')
const isInternal = ref(false)
const submitting = ref(false)
const assignedTo = ref<number | null>(null)

const statuses = ['open', 'in_progress', 'pending', 'resolved', 'closed']
const availableStatuses = computed(() => statuses.filter(s => s !== ticket.value?.status))

// Approval
const approvalNotes = ref('')
const rejectNotes = ref('')
const approvalError = ref('')
const showRejectModal = ref(false)
const approvalLoading = ref(false)

// Use Number() on both sides — JSON can return IDs as strings in some edge cases
const currentPendingApproval = computed((): TicketApproval | null =>
  ticket.value?.approvals?.find(a => a.status === 'pending') ?? null
)
const isCurrentApprover = computed(() =>
  !!currentPendingApproval.value &&
  Number(currentPendingApproval.value.approver_id) === Number(auth.user?.id)
)
const categoryEmoji = computed(() =>
  CATEGORIES.find(c => c.id === ticket.value?.category)?.emoji ?? ''
)

const previewUrls = ref<Record<number, string>>({})

watch(() => ticket.value?.attachments, async (attachments) => {
  for (const att of attachments ?? []) {
    if (att.mime_type.startsWith('image/') && !previewUrls.value[att.id]) {
      try {
        previewUrls.value[att.id] = await attachmentPreviewUrl(att.id)
      } catch {
        // leave the generic file tile if the preview fails to load
      }
    }
  }
}, { immediate: true })

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

async function handleApprove() {
  approvalLoading.value = true
  approvalError.value = ''
  try {
    await approvalApi.approve(Number(route.params.id), approvalNotes.value || undefined)
    approvalNotes.value = ''
    await ticketStore.fetchTicket(Number(route.params.id))
  } catch (e: any) {
    approvalError.value = e?.response?.data?.message || 'Failed to approve. Please try again.'
  } finally {
    approvalLoading.value = false
  }
}

function openRejectModal() {
  rejectNotes.value = ''
  showRejectModal.value = true
}

async function handleReject() {
  if (!rejectNotes.value.trim()) return
  approvalLoading.value = true
  approvalError.value = ''
  try {
    await approvalApi.reject(Number(route.params.id), rejectNotes.value)
    showRejectModal.value = false
    rejectNotes.value = ''
    await ticketStore.fetchTicket(Number(route.params.id))
  } catch (e: any) {
    showRejectModal.value = false
    approvalError.value = e?.response?.data?.message || 'Failed to reject. Please try again.'
  } finally {
    approvalLoading.value = false
  }
}

function formatDateTime(dt: string) {
  return new Date(dt).toLocaleString()
}

onMounted(async () => {
  const id = Number(route.params.id)
  ticketStore.currentTicket = null
  try {
    await ticketStore.fetchTicket(id)
    assignedTo.value = ticket.value?.assigned_to ?? null
    await loadComments()
    if (auth.isItStaff) {
      const { data } = await userApi.itStaff()
      itStaff.value = data
    }
  } catch (e: any) {
    if (e?.response?.status === 404) notFound.value = true
    else throw e
  } finally {
    loading.value = false
  }

  if (notFound.value) return

  pollTimer = setInterval(async () => {
    try {
      await ticketStore.fetchTicket(id)
      await loadComments()
    } catch (e: any) {
      if (e?.response?.status === 404) {
        notFound.value = true
        ticketStore.currentTicket = null
        if (pollTimer) clearInterval(pollTimer)
      }
    }
  }, 30000)
})

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
  Object.values(previewUrls.value).forEach(url => URL.revokeObjectURL(url))
})
</script>
