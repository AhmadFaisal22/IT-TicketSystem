<template>
  <div v-if="asset" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main -->
    <div class="lg:col-span-2 space-y-6">
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-sm font-mono text-red-600">{{ asset.asset_tag }}</p>
            <h1 class="text-xl font-semibold text-gray-800">{{ asset.name || t(`asset.category_labels.${asset.category}`) }}</h1>
          </div>
          <div class="flex gap-2">
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
          <div><dt class="text-gray-400">{{ t('asset.category') }}</dt><dd class="text-gray-800">{{ t(`asset.category_labels.${asset.category}`) }}</dd></div>
          <div><dt class="text-gray-400">{{ t('asset.status') }}</dt>
            <dd><span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusClass(asset.status)">{{ t(`asset.status_labels.${asset.status}`) }}</span></dd></div>
          <div><dt class="text-gray-400">{{ t('asset.lastName') }}</dt><dd class="text-gray-800">{{ asset.last_name || '—' }}</dd></div>
          <div><dt class="text-gray-400">{{ t('asset.firstName') }}</dt><dd class="text-gray-800">{{ asset.first_name || '—' }}</dd></div>
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
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-3">
          <h2 class="font-semibold text-gray-800">{{ t('asset.attachments') }}</h2>
          <button @click="fileInput?.click()" class="text-sm text-red-600 hover:text-red-800">+ {{ t('asset.actions.uploadFile') }}</button>
          <input ref="fileInput" type="file" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" class="hidden" @change="onUpload" />
        </div>
        <ul class="space-y-2">
          <li v-for="att in asset.attachments" :key="att.id" class="flex items-center justify-between text-sm">
            <a :href="att.url" target="_blank" class="text-blue-600 hover:underline truncate">{{ att.original_name }}</a>
            <button @click="removeAttachment(att.id)" class="text-gray-400 hover:text-red-600">✕</button>
          </li>
          <li v-if="!asset.attachments?.length" class="text-sm text-gray-400">—</li>
        </ul>
      </div>

      <!-- History -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
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
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.actions.assign') }}</label>
          <select v-model="assignTo" class="input">
            <option :value="null">{{ t('asset.actions.returnToStock') }}</option>
            <option v-for="u in itStaff" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
          <button @click="doAssign" class="mt-2 w-full px-3 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700">
            {{ t('asset.actions.assign') }}
          </button>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.actions.changeStatus') }}</label>
          <select v-model="newStatus" @change="doStatus" class="input">
            <option v-for="s in statuses" :key="s" :value="s">{{ t(`asset.status_labels.${s}`) }}</option>
          </select>
        </div>
      </div>

      <!-- QR label -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center">
        <h2 class="font-semibold text-gray-800 mb-3">{{ t('asset.qrLabel') }}</h2>
        <img v-if="qrDataUrl" :src="qrDataUrl" class="mx-auto w-40 h-40" />
        <button @click="printLabel" class="mt-3 px-3 py-1.5 text-sm border rounded-lg text-gray-700 hover:bg-gray-50">
          {{ t('asset.print') }}
        </button>
      </div>

      <!-- Related tickets -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
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
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import QRCode from 'qrcode'
import { useAssetStore, ASSET_STATUSES } from '@/stores/assets'
import { useAuthStore } from '@/stores/auth'
import { assetApi, userApi } from '@/api'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const store = useAssetStore()
const auth = useAuthStore()

const asset = computed(() => store.currentAsset)
const statuses = ASSET_STATUSES
const itStaff = ref<any[]>([])
const assignTo = ref<number | null>(null)
const newStatus = ref<string>('in_stock')
const qrDataUrl = ref('')
const fileInput = ref<HTMLInputElement | null>(null)

async function reload() {
  const a = await store.fetchAsset(Number(route.params.id))
  assignTo.value = a.assigned_to
  newStatus.value = a.status
  qrDataUrl.value = await QRCode.toDataURL(`${window.location.origin}/assets/${a.id}`)
}

async function handleDelete() {
  if (!confirm(t('asset.deleteConfirm', { tag: asset.value!.asset_tag }))) return
  try {
    await assetApi.remove(asset.value!.id)
    router.replace('/assets')
  } catch (e: any) {
    alert(e?.response?.data?.message || t('common.error'))
  }
}

async function doAssign() {
  await store.assignAsset(asset.value!.id, assignTo.value)
  await reload()
}

async function doStatus() {
  await store.changeStatus(asset.value!.id, newStatus.value)
  await reload()
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
    in_stock: 'bg-sky-100 text-sky-700', assigned: 'bg-green-100 text-green-700',
    in_repair: 'bg-yellow-100 text-yellow-700', retired: 'bg-gray-100 text-gray-600',
    lost: 'bg-red-100 text-red-700',
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

watch(() => route.params.id, reload)
onMounted(async () => {
  await reload()
  const { data } = await userApi.itStaff()
  itStaff.value = data
})
</script>

<style scoped>
.input {
  @apply w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:outline-none;
}
</style>
