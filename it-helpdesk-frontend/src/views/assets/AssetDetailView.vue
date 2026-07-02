<template>
  <div v-if="asset" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Back -->
    <div class="lg:col-span-3 -mb-2">
      <button @click="goBack" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        ← {{ t('common.backToAssets') }}
      </button>
    </div>

    <!-- Main -->
    <div class="lg:col-span-2 space-y-6">
      <div class="bg-white rounded-card shadow-soft border border-gray-100 p-6">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-sm font-mono text-red-600">{{ asset.asset_tag }}</p>
            <h1 class="text-xl font-semibold text-gray-800">{{ asset.name || categoryLabel(asset.category) }}</h1>
          </div>
          <div class="flex gap-2">
            <router-link :to="`/assets/create?from=${asset.id}`"
              class="px-3 py-1.5 text-sm border rounded-lg text-gray-700 hover:bg-gray-50">
              {{ t('asset.actions.duplicate') }}
            </router-link>
            <router-link :to="`/assets/${asset.id}/edit`"
              class="px-3 py-1.5 text-sm border rounded-lg text-gray-700 hover:bg-gray-50">
              {{ t('asset.actions.edit') }}
            </router-link>
            <button v-if="auth.isAdmin" @click="handleDelete"
              class="px-3 py-1.5 text-sm border border-red-300 rounded-lg text-red-600 hover:bg-red-50">
              {{ t('common.delete') }}
            </button>
          </div>
        </div>

        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 mt-5 text-sm">
          <div><dt class="text-gray-400">{{ t('asset.category') }}</dt><dd class="text-gray-800">{{ categoryLabel(asset.category) }}</dd></div>
          <div><dt class="text-gray-400">{{ t('asset.status') }}</dt>
            <dd><span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusClass(asset.status)">{{ t(`asset.status_labels.${asset.status}`) }}</span></dd></div>
          <div><dt class="text-gray-400">{{ t('asset.lastName') }}</dt><dd class="text-gray-800">{{ asset.last_name || '—' }}</dd></div>
          <div><dt class="text-gray-400">{{ t('asset.firstName') }}</dt><dd class="text-gray-800">{{ asset.first_name || '—' }}</dd></div>
          <div><dt class="text-gray-400">{{ t('asset.department') }}</dt><dd class="text-gray-800">{{ (locale === 'zh' ? asset.department?.name_zh : asset.department?.name) || '—' }}</dd></div>
          <div><dt class="text-gray-400">{{ t('asset.serialNumber') }}</dt><dd class="text-gray-800">{{ asset.serial_number || '—' }}</dd></div>
          <div><dt class="text-gray-400">{{ t('asset.assignee') }}</dt><dd class="text-gray-800">{{ asset.assignee?.name || t('asset.unassigned') }}</dd></div>
          <div><dt class="text-gray-400">{{ t('asset.manufacturer') }}</dt><dd class="text-gray-800">{{ asset.manufacturer || '—' }}</dd></div>
          <div><dt class="text-gray-400">{{ t('asset.model') }}</dt><dd class="text-gray-800">{{ asset.model || '—' }}</dd></div>
          <div><dt class="text-gray-400">{{ t('asset.location') }}</dt><dd class="text-gray-800">{{ asset.location || '—' }}</dd></div>
          <div><dt class="text-gray-400">{{ t('asset.warrantyExpiry') }}</dt><dd class="text-gray-800">{{ asset.warranty_expiry?.slice(0,10) || '—' }}</dd></div>
          <div><dt class="text-gray-400">{{ t('asset.assignDate') }}</dt><dd class="text-gray-800">{{ asset.assign_date?.slice(0,10) || '—' }}</dd></div>
          <div><dt class="text-gray-400">{{ t('asset.purchaseLink') }}</dt>
            <dd><a v-if="asset.purchase_link" :href="asset.purchase_link" target="_blank" class="text-blue-600 hover:underline break-all">{{ asset.purchase_link }}</a><span v-else class="text-gray-800">—</span></dd></div>
          <div class="col-span-2" v-if="asset.notes"><dt class="text-gray-400">{{ t('asset.notes') }}</dt><dd class="text-gray-800 whitespace-pre-wrap">{{ asset.notes }}</dd></div>
        </dl>
      </div>

      <!-- Attachments -->
      <div class="bg-white rounded-card shadow-soft border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-3">
          <h2 class="font-semibold text-gray-800">{{ t('asset.attachments') }}</h2>
          <button @click="fileInput?.click()" class="text-sm text-red-600 hover:text-red-800">+ {{ t('asset.actions.uploadFile') }}</button>
          <input ref="fileInput" type="file" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" class="hidden" @change="onUpload" />
        </div>
        <ul class="space-y-2">
          <li v-for="att in asset.attachments" :key="att.id" class="flex items-center justify-between gap-2 text-sm">
            <button type="button" @click="openPreview(att)" :title="t('asset.actions.preview')"
              class="text-blue-600 hover:underline truncate text-left cursor-pointer">{{ att.original_name }}</button>
            <div class="flex shrink-0 items-center gap-2">
              <button type="button" @click="downloadAttachment(att)" :aria-label="t('asset.actions.download')"
                class="text-gray-400 hover:text-red-600"><ArrowDownTrayIcon class="h-4 w-4" /></button>
              <button @click="removeAttachment(att.id)" class="text-gray-400 hover:text-red-600">✕</button>
            </div>
          </li>
          <li v-if="!asset.attachments?.length" class="text-sm text-gray-400">—</li>
        </ul>
      </div>

      <!-- History -->
      <div class="bg-white rounded-card shadow-soft border border-gray-100 p-6">
        <h2 class="font-semibold text-gray-800 mb-3">{{ t('asset.history') }}</h2>
        <ul class="space-y-3">
          <li v-for="h in asset.histories" :key="h.id" class="text-sm flex gap-3">
            <span class="text-gray-400 whitespace-nowrap">{{ formatDate(h.created_at) }}</span>
            <span class="text-gray-700">
              <b>{{ h.user?.name }}</b> — {{ actionLabel(h.action) }}
              <template v-if="h.field">({{ valueLabel(h, h.old_value) }} → {{ valueLabel(h, h.new_value) }})</template>
            </span>
          </li>
          <li v-if="!asset.histories?.length" class="text-sm text-gray-400">—</li>
        </ul>
      </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
      <!-- Actions -->
      <div class="bg-white rounded-card shadow-soft border border-gray-100 p-6 space-y-4">
        <div ref="assignPicker" class="relative">
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.actions.assign') }}</label>
          <div class="flex flex-col sm:flex-row gap-2">
            <div class="relative flex-1 min-w-0">
              <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
              <input
                v-model="assigneeSearch"
                type="search"
                class="assignee-search-input"
                :placeholder="selectedAssigneeName || t('asset.selectUser')"
                autocomplete="off"
                @focus="openAssigneePicker"
                @input="onAssigneeSearchInput"
                @keydown.down.prevent="moveAssigneeHighlight(1)"
                @keydown.up.prevent="moveAssigneeHighlight(-1)"
                @keydown.enter.prevent="chooseHighlightedAssignee"
                @keydown.esc="closeAssigneePicker"
              />
              <div
                v-if="assigneePickerOpen"
                class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg"
              >
                <button
                  type="button"
                  class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-gray-50"
                  :class="highlightedAssigneeIndex === 0 ? 'bg-gray-50' : ''"
                  @mouseenter="highlightedAssigneeIndex = 0"
                  @mousedown.prevent="selectAssignee(null)"
                >
                  <span class="font-medium text-gray-700">{{ t('asset.actions.returnToStock') }}</span>
                  <CheckIcon v-if="assignTo === null" class="h-4 w-4 text-red-600" />
                </button>

                <div class="max-h-64 overflow-y-auto border-t border-gray-100">
                  <button
                    v-for="(u, index) in assignableUsers"
                    :key="u.id"
                    type="button"
                    class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-gray-50"
                    :class="highlightedAssigneeIndex === index + 1 ? 'bg-gray-50' : ''"
                    @mouseenter="highlightedAssigneeIndex = index + 1"
                    @mousedown.prevent="selectAssignee(u)"
                  >
                    <span class="min-w-0">
                      <span class="block truncate font-medium text-gray-800">{{ u.name }}</span>
                      <span v-if="u.email" class="block truncate text-xs text-gray-500">{{ u.email }}</span>
                    </span>
                    <span class="flex shrink-0 items-center gap-2">
                      <span v-if="u.role" class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600">
                        {{ roleLabel(u.role) }}
                      </span>
                      <CheckIcon v-if="assignTo === u.id" class="h-4 w-4 text-red-600" />
                    </span>
                  </button>
                  <div v-if="assignableLoading" class="px-3 py-2 text-sm text-gray-400">
                    {{ t('common.loading') }}
                  </div>
                  <div v-else-if="!assignableUsers.length" class="px-3 py-2 text-sm text-gray-400">
                    {{ t('asset.noAssignableUsers') }}
                  </div>
                </div>
              </div>
            </div>

            <button
              type="button"
              @click="doAssign"
              :disabled="assignButtonDisabled"
              class="inline-flex min-w-[8.5rem] items-center justify-center gap-1.5 rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <CheckIcon class="h-4 w-4" />
              <span>{{ assignSaving ? t('common.loading') : assignButtonLabel }}</span>
            </button>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.actions.changeStatus') }}</label>
          <select v-model="newStatus" @change="doStatus" class="input">
            <option v-for="s in statuses" :key="s" :value="s">{{ t(`asset.status_labels.${s}`) }}</option>
          </select>
        </div>
      </div>

      <!-- QR label -->
      <div class="bg-white rounded-card shadow-soft border border-gray-100 p-6 text-center">
        <h2 class="font-semibold text-gray-800 mb-3">{{ t('asset.qrLabel') }}</h2>
        <img v-if="qrDataUrl" :src="qrDataUrl" class="mx-auto w-40 h-40" />
        <button @click="printLabel" class="mt-3 px-3 py-1.5 text-sm border rounded-lg text-gray-700 hover:bg-gray-50">
          {{ t('asset.print') }}
        </button>
      </div>

      <!-- Related tickets -->
      <div class="bg-white rounded-card shadow-soft border border-gray-100 p-6">
        <h2 class="font-semibold text-gray-800 mb-3">{{ t('asset.relatedTickets') }}</h2>
        <ul class="space-y-2">
          <li v-for="tk in asset.tickets" :key="tk.id">
            <router-link :to="`/tickets/${tk.id}`" class="text-sm text-blue-600 hover:underline">
              {{ tk.ticket_number }} — {{ tk.title }}
            </router-link>
          </li>
          <li v-if="!asset.tickets?.length" class="text-sm text-gray-400">—</li>
        </ul>
      </div>
    </div>
  </div>
  <div v-else class="p-8 text-center text-gray-400">{{ t('common.loading') }}</div>

  <ImageLightbox v-if="lightbox" :src="lightbox.url" :name="lightbox.name" @close="closeLightbox" />
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import QRCode from 'qrcode'
import { CheckIcon, MagnifyingGlassIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline'
import { useAssetStore, ASSET_STATUSES, type AssetAttachment } from '@/stores/assets'
import { useAuthStore } from '@/stores/auth'
import { assetApi, userApi, assetCategoryApi } from '@/api'
import { downloadAttachment, attachmentPreviewUrl } from '@/utils/attachments'
import { backToList } from '@/utils/backToList'
import ImageLightbox from '@/components/ui/ImageLightbox.vue'

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const store = useAssetStore()
const auth = useAuthStore()

interface AssignableUser {
  id: number
  name: string
  email?: string | null
  role?: string | null
  avatar?: string | null
}

const asset = computed(() => store.currentAsset)
const statuses = ASSET_STATUSES
const assignableUsers = ref<AssignableUser[]>([])
const assignPicker = ref<HTMLElement | null>(null)
const assigneeSearch = ref('')
const assigneePickerOpen = ref(false)
const assignableLoading = ref(false)
const assignSaving = ref(false)
const highlightedAssigneeIndex = ref(0)
const categories = ref<any[]>([])
let assignableRequestId = 0
let assigneeSearchTimer: ReturnType<typeof setTimeout> | null = null

function categoryLabel(name: string) {
  if (locale.value === 'zh') {
    const match = categories.value.find(c => c.name === name)
    if (match?.name_zh) return match.name_zh
  }
  return name
}
const assignTo = ref<number | null>(null)
const newStatus = ref<string>('in_stock')
const qrDataUrl = ref('')
const fileInput = ref<HTMLInputElement | null>(null)
const selectedAssigneeName = computed(() => {
  if (assignTo.value === null) return ''
  return assignableUsers.value.find(u => u.id === assignTo.value)?.name || asset.value?.assignee?.name || ''
})
const assignButtonLabel = computed(() =>
  assignTo.value === null ? t('asset.actions.returnToStock') : t('asset.actions.assign')
)
const assignButtonDisabled = computed(() =>
  assignSaving.value || !asset.value || assignTo.value === asset.value.assigned_to
)

async function reload() {
  const a = await store.fetchAsset(Number(route.params.id))
  assignTo.value = a.assigned_to
  assigneeSearch.value = a.assignee?.name ?? ''
  newStatus.value = a.status
  // Use the configured public URL so a printed label scans on phones — the
  // current origin is often localhost (dev) or an internal host unreachable
  // from a phone. Falls back to the current origin when unset.
  const base = import.meta.env.VITE_PUBLIC_URL || window.location.origin
  qrDataUrl.value = await QRCode.toDataURL(`${base}/assets/${a.id}`)
  await loadAssignableUsers(assigneeSearch.value, a.assigned_to)
}

function goBack() {
  backToList(router, '/assets')
}

async function handleDelete() {
  if (!confirm(t('asset.deleteConfirm', { tag: asset.value!.asset_tag }))) return
  try {
    await assetApi.remove(asset.value!.id)
    goBack()
  } catch (e: any) {
    alert(e?.response?.data?.message || t('common.error'))
  }
}

async function doAssign() {
  if (!asset.value || assignSaving.value) return

  assignSaving.value = true
  try {
    await store.assignAsset(asset.value.id, assignTo.value)
    assigneePickerOpen.value = false
    await reload()
  } finally {
    assignSaving.value = false
  }
}

async function doStatus() {
  await store.changeStatus(asset.value!.id, newStatus.value)
  await reload()
}

async function loadAssignableUsers(search = assigneeSearch.value, selectedId = assignTo.value) {
  const requestId = ++assignableRequestId
  assignableLoading.value = true

  try {
    const { data } = await userApi.assignable({
      search: search.trim() || undefined,
      selected_id: selectedId ?? undefined,
      limit: 25,
    })

    if (requestId === assignableRequestId) {
      assignableUsers.value = data
      highlightedAssigneeIndex.value = 0
    }
  } finally {
    if (requestId === assignableRequestId) {
      assignableLoading.value = false
    }
  }
}

function openAssigneePicker() {
  assigneePickerOpen.value = true
  void loadAssignableUsers()
}

function closeAssigneePicker() {
  assigneePickerOpen.value = false
}

function onAssigneeSearchInput() {
  assigneePickerOpen.value = true
  if (!assigneeSearch.value.trim()) {
    assignTo.value = null
  }
  if (assigneeSearchTimer) clearTimeout(assigneeSearchTimer)
  assigneeSearchTimer = setTimeout(() => {
    void loadAssignableUsers()
  }, 200)
}

function selectAssignee(user: AssignableUser | null) {
  assignTo.value = user?.id ?? null
  assigneeSearch.value = user?.name ?? ''
  assigneePickerOpen.value = false
}

function moveAssigneeHighlight(delta: number) {
  assigneePickerOpen.value = true
  const optionCount = assignableUsers.value.length + 1
  highlightedAssigneeIndex.value = (highlightedAssigneeIndex.value + delta + optionCount) % optionCount
}

function chooseHighlightedAssignee() {
  if (!assigneePickerOpen.value) {
    openAssigneePicker()
    return
  }

  if (highlightedAssigneeIndex.value === 0) {
    selectAssignee(null)
    return
  }

  const user = assignableUsers.value[highlightedAssigneeIndex.value - 1]
  if (user) selectAssignee(user)
}

function onDocumentClick(event: MouseEvent) {
  if (!assignPicker.value?.contains(event.target as Node)) {
    closeAssigneePicker()
  }
}

function roleLabel(role: string) {
  if (role === 'admin') return t('admin.users.admin')
  if (role === 'it_staff') return t('admin.users.it_staff')
  return t('admin.users.user')
}

async function onUpload(e: Event) {
  const files = (e.target as HTMLInputElement).files
  if (!files?.length) return
  const fd = new FormData()
  Array.from(files).forEach(f => fd.append('attachments[]', f))
  await assetApi.uploadAttachments(asset.value!.id, fd)
  if (fileInput.value) fileInput.value.value = ''
  await reload()
}

async function removeAttachment(id: number) {
  await assetApi.deleteAttachment(asset.value!.id, id)
  await reload()
}

const lightbox = ref<{ url: string; name: string } | null>(null)

async function openPreview(att: AssetAttachment) {
  if (att.mime_type.startsWith('image/')) {
    try {
      const url = await attachmentPreviewUrl(att.id)
      lightbox.value = { url, name: att.original_name }
    } catch {
      downloadAttachment(att)
    }
    return
  }
  if (att.mime_type === 'application/pdf') {
    // Open the tab synchronously (before await) so the browser keeps the
    // user-gesture context and does not block it as a popup.
    const w = window.open('', '_blank')
    try {
      const url = await attachmentPreviewUrl(att.id)
      if (w) {
        w.location.href = url
        // Give the new tab time to load the blob before reclaiming it.
        setTimeout(() => URL.revokeObjectURL(url), 60_000)
      } else {
        downloadAttachment(att)
      }
    } catch {
      w?.close()
      downloadAttachment(att)
    }
    return
  }
  downloadAttachment(att)
}

function closeLightbox() {
  if (lightbox.value) URL.revokeObjectURL(lightbox.value.url)
  lightbox.value = null
}

function printLabel() {
  const w = window.open('', '_blank', 'width=400,height=400')
  if (!w) return
  w.document.write(`<div style="text-align:center;font-family:sans-serif">
    <img src="${qrDataUrl.value}" style="width:200px;height:200px" />
    <p style="font:14px monospace">${asset.value!.asset_tag}</p>
    <p>${asset.value!.name || ''}</p></div>`)
  w.document.close()
  w.print()
}

function statusClass(s: string) {
  const map: Record<string, string> = {
    in_stock: 'bg-blue-50 text-blue-700', assigned: 'bg-green-50 text-green-700',
    in_repair: 'bg-yellow-50 text-yellow-700', retired: 'bg-gray-100 text-gray-600',
    lost: 'bg-red-50 text-red-700',
  }
  return map[s] || 'bg-gray-100 text-gray-600'
}

function formatDate(dt: string) {
  return new Date(dt).toLocaleString()
}

const KNOWN_ACTIONS = ['created', 'updated', 'assigned', 'returned', 'status_changed']
function actionLabel(action: string) {
  return KNOWN_ACTIONS.includes(action) ? t(`asset.history_actions.${action}`) : action
}

function valueLabel(h: { field: string | null }, value: string | null) {
  if (!value) return '∅'
  if (h.field === 'status' && (ASSET_STATUSES as readonly string[]).includes(value)) {
    return t(`asset.status_labels.${value}`)
  }
  return value
}

watch(() => route.params.id, () => {
  void reload()
})
onMounted(async () => {
  document.addEventListener('click', onDocumentClick)
  const [, catRes] = await Promise.all([reload(), assetCategoryApi.list()])
  categories.value = catRes.data
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
  if (assigneeSearchTimer) clearTimeout(assigneeSearchTimer)
})
</script>

<style scoped>
.input {
  @apply w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none;
}

.assignee-search-input {
  @apply w-full py-2 pl-9 pr-9 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none;
}
</style>
