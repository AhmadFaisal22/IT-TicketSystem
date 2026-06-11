# IT Asset Management — Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Vue 3 SPA for the IT asset register — list, create/edit, detail (with assign/return/status, history, attachments, QR label, related tickets), IT-only nav, EN/中文 i18n, and an optional asset picker on tickets — against the backend API already built.

**Architecture:** Mirror the existing Ticket UI. A Pinia setup-store (`stores/assets.ts`), an `assetApi` block in `api/index.ts`, four routed views under `AppLayout` guarded by a new `requiresItStaff` route meta, and a shared `AssetForm` component reused by create/edit. Category/status labels resolve through i18n. QR labels are generated client-side with the `qrcode` package.

**Tech Stack:** Vue 3.5, Vite 5, TypeScript, Tailwind 3, Pinia, vue-router 4, vue-i18n, axios, `qrcode`, `@heroicons/vue` (already installed).

**Backend contract (already implemented):** All routes are IT-only (regular users get 403).
`GET /api/assets` (paginated: `{data, current_page, last_page, total}`; filters `status,category,department_id,assigned_to,search,page`) · `GET /api/assets/meta` (`{categories, statuses, status_counts}`) · `GET /api/assets/export` (xlsx) · `POST /api/assets/import` (`file`; returns `{created, rejected}`) · `GET /api/assets/{id}` (loads `assignee,department,histories.user,attachments,tickets`) · `POST /api/assets` · `PUT /api/assets/{id}` · `DELETE /api/assets/{id}` (admin only) · `PATCH /api/assets/{id}/assign` (`assigned_to,department_id`) · `PATCH /api/assets/{id}/status` (`status`) · `POST /api/assets/{id}/attachments` (`attachments[]`) · `DELETE /api/assets/{id}/attachments/{attachmentId}`.

**Conventions:** Run all commands from `it-helpdesk-frontend/` (Node runs on the host — no Docker needed for the frontend). The verification gate for every task is `npm run build` (runs `vue-tsc` typecheck + Vite build); the project has no JS unit-test runner, so type-check + a final manual smoke test stand in for tests. The category/status key lists are fixed and must match the backend `AssetCategories`.

**Scope note:** Spec: `docs/superpowers/specs/2026-06-10-inventory-design.md`. Backend plan (done): `docs/superpowers/plans/2026-06-10-inventory-backend.md`.

---

## File Structure

**Create:**
- `src/stores/assets.ts` — Pinia store + `Asset`/`AssetHistory` interfaces
- `src/views/assets/AssetsView.vue` — list + filters + pagination + import/export
- `src/components/assets/AssetForm.vue` — shared create/edit form
- `src/views/assets/CreateAssetView.vue` — wraps AssetForm (create mode)
- `src/views/assets/EditAssetView.vue` — wraps AssetForm (edit mode)
- `src/views/assets/AssetDetailView.vue` — detail, actions, history, attachments, QR, related tickets

**Modify:**
- `src/api/index.ts` — add `assetApi`
- `src/locales/en.ts`, `src/locales/zh.ts` — add `asset.*` + `nav.assets`
- `src/router/index.ts` — add 4 routes + `requiresItStaff` guard
- `src/components/layout/AppLayout.vue` — nav entry + pageTitle entries
- `src/views/tickets/CreateTicketView.vue`, `src/views/tickets/TicketDetailView.vue`, `src/stores/tickets.ts` — optional asset picker/link

---

## Task 1: Dependencies + API + store

**Files:**
- Modify: `src/api/index.ts`
- Create: `src/stores/assets.ts`
- Run: `package.json`

- [ ] **Step 1: Install deps**

Run: `npm install` then `npm install qrcode && npm install -D @types/qrcode`
Expected: `qrcode` in `dependencies`, `@types/qrcode` in `devDependencies`.

- [ ] **Step 2: Add `assetApi` to `src/api/index.ts`**

Append after the existing `approvalApi` block:

```ts
export const assetApi = {
  list: (params?: object) => api.get('/assets', { params }),
  meta: () => api.get('/assets/meta'),
  get: (id: number) => api.get(`/assets/${id}`),
  create: (data: object) => api.post('/assets', data),
  update: (id: number, data: object) => api.put(`/assets/${id}`, data),
  remove: (id: number) => api.delete(`/assets/${id}`),
  assign: (id: number, assigned_to: number | null, department_id?: number | null) =>
    api.patch(`/assets/${id}/assign`, { assigned_to, department_id }),
  updateStatus: (id: number, status: string) => api.patch(`/assets/${id}/status`, { status }),
  uploadAttachments: (id: number, formData: FormData) => api.post(`/assets/${id}/attachments`, formData),
  deleteAttachment: (id: number, attachmentId: number) =>
    api.delete(`/assets/${id}/attachments/${attachmentId}`),
  export: (params?: object) => api.get('/assets/export', { params, responseType: 'blob' }),
  import: (formData: FormData) => api.post('/assets/import', formData),
}
```

- [ ] **Step 3: Create `src/stores/assets.ts`**

```ts
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { assetApi } from '@/api'

export const ASSET_CATEGORIES = [
  'laptop', 'desktop', 'monitor', 'printer', 'network',
  'phone', 'peripheral', 'software_license', 'other',
] as const

export const ASSET_STATUSES = [
  'in_stock', 'assigned', 'in_repair', 'retired', 'lost',
] as const

export type AssetStatus = typeof ASSET_STATUSES[number]

export interface AssetHistory {
  id: number
  action: string
  field: string | null
  old_value: string | null
  new_value: string | null
  created_at: string
  user: { id: number; name: string }
}

export interface AssetAttachment {
  id: number
  original_name: string
  mime_type: string
  size: number
  url: string
}

export interface Asset {
  id: number
  asset_tag: string
  name: string
  category: string
  manufacturer: string | null
  model: string | null
  serial_number: string | null
  status: AssetStatus
  assigned_to: number | null
  department_id: number | null
  location: string | null
  purchase_date: string | null
  purchase_cost: string | null
  warranty_expiry: string | null
  notes: string | null
  created_at: string
  assignee?: { id: number; name: string; avatar?: string | null } | null
  department?: { id: number; name: string; name_zh: string } | null
  histories?: AssetHistory[]
  attachments?: AssetAttachment[]
  tickets?: { id: number; ticket_number: string; title: string; status: string }[]
}

export const useAssetStore = defineStore('assets', () => {
  const assets = ref<Asset[]>([])
  const currentAsset = ref<Asset | null>(null)
  const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
  const loading = ref(false)

  async function fetchAssets(params?: object) {
    loading.value = true
    try {
      const { data } = await assetApi.list(params)
      assets.value = data.data
      pagination.value = { current_page: data.current_page, last_page: data.last_page, total: data.total }
    } finally {
      loading.value = false
    }
  }

  async function fetchAsset(id: number) {
    const { data } = await assetApi.get(id)
    currentAsset.value = data
    return data as Asset
  }

  async function createAsset(payload: object) {
    const { data } = await assetApi.create(payload)
    return data as Asset
  }

  async function updateAsset(id: number, payload: object) {
    const { data } = await assetApi.update(id, payload)
    if (currentAsset.value?.id === id) currentAsset.value = data
    return data as Asset
  }

  async function assignAsset(id: number, assignedTo: number | null, departmentId?: number | null) {
    const { data } = await assetApi.assign(id, assignedTo, departmentId)
    if (currentAsset.value?.id === id) currentAsset.value = { ...currentAsset.value, ...data }
    return data as Asset
  }

  async function changeStatus(id: number, status: string) {
    const { data } = await assetApi.updateStatus(id, status)
    if (currentAsset.value?.id === id) currentAsset.value = { ...currentAsset.value, ...data }
    return data as Asset
  }

  return {
    assets, currentAsset, pagination, loading,
    fetchAssets, fetchAsset, createAsset, updateAsset, assignAsset, changeStatus,
  }
})
```

- [ ] **Step 4: Build**

Run: `npm run build`
Expected: build succeeds, no TS errors.

- [ ] **Step 5: Commit**

```bash
git add it-helpdesk-frontend/package.json it-helpdesk-frontend/package-lock.json it-helpdesk-frontend/src/api/index.ts it-helpdesk-frontend/src/stores/assets.ts
git commit -m "feat(inventory-ui): add qrcode dep, assetApi, and assets store"
```

---

## Task 2: i18n keys (EN + 中文)

**Files:**
- Modify: `src/locales/en.ts`, `src/locales/zh.ts`

- [ ] **Step 1: Add `nav.assets` and the `asset` block to `src/locales/en.ts`**

In the `nav` object add `assets: 'Assets',` (after `tickets`). Then add this top-level block (sibling of `ticket`, before `common`):

```ts
  asset: {
    title: 'Assets',
    assetTag: 'Fixed Assets Tag#',
    name: 'Name',
    category: 'Category',
    manufacturer: 'Manufacturer',
    model: 'Model',
    serialNumber: 'Serial Number',
    status: 'Status',
    assignee: 'Assigned To',
    department: 'Department',
    location: 'Location',
    purchaseDate: 'Purchase Date',
    purchaseCost: 'Purchase Cost',
    warrantyExpiry: 'Warranty Expiry',
    notes: 'Notes',
    unassigned: 'Unassigned',
    search: 'Search assets...',
    noAssets: 'No assets found',
    addFirst: 'Add your first asset',
    relatedTickets: 'Related Tickets',
    history: 'History',
    attachments: 'Attachments',
    qrLabel: 'QR Label',
    print: 'Print',
    importBtn: 'Import',
    exportBtn: 'Export',
    importResult: '{created} created, {rejected} rejected',
    selectUser: 'Select a user',
    selectDept: 'Select a department',
    category_labels: {
      laptop: 'Laptop', desktop: 'Desktop', monitor: 'Monitor', printer: 'Printer',
      network: 'Network Device', phone: 'Phone', peripheral: 'Peripheral',
      software_license: 'Software License', other: 'Other',
    },
    status_labels: {
      in_stock: 'In Stock', assigned: 'Assigned', in_repair: 'In Repair',
      retired: 'Retired', lost: 'Lost',
    },
    actions: {
      create: 'Add Asset', edit: 'Edit Asset', assign: 'Assign',
      returnToStock: 'Return to Stock', changeStatus: 'Change Status', uploadFile: 'Upload File',
    },
  },
```

- [ ] **Step 2: Add the mirrored block to `src/locales/zh.ts`**

In `nav` add `assets: '资产',`. Then add the sibling block:

```ts
  asset: {
    title: '资产',
    assetTag: '资产编号',
    name: '名称',
    category: '类别',
    manufacturer: '厂商',
    model: '型号',
    serialNumber: '序列号',
    status: '状态',
    assignee: '使用人',
    department: '部门',
    location: '位置',
    purchaseDate: '采购日期',
    purchaseCost: '采购金额',
    warrantyExpiry: '保修到期',
    notes: '备注',
    unassigned: '未分配',
    search: '搜索资产...',
    noAssets: '暂无资产',
    addFirst: '添加第一台资产',
    relatedTickets: '关联工单',
    history: '流转记录',
    attachments: '附件',
    qrLabel: '二维码标签',
    print: '打印',
    importBtn: '导入',
    exportBtn: '导出',
    importResult: '成功 {created} 条，失败 {rejected} 条',
    selectUser: '选择用户',
    selectDept: '选择部门',
    category_labels: {
      laptop: '笔记本', desktop: '台式机', monitor: '显示器', printer: '打印机',
      network: '网络设备', phone: '手机', peripheral: '外设',
      software_license: '软件许可', other: '其他',
    },
    status_labels: {
      in_stock: '在库', assigned: '已分配', in_repair: '维修中',
      retired: '已报废', lost: '丢失',
    },
    actions: {
      create: '添加资产', edit: '编辑资产', assign: '分配',
      returnToStock: '回收入库', changeStatus: '改状态', uploadFile: '上传文件',
    },
  },
```

- [ ] **Step 3: Build**

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 4: Commit**

```bash
git add it-helpdesk-frontend/src/locales/en.ts it-helpdesk-frontend/src/locales/zh.ts
git commit -m "feat(inventory-ui): add asset i18n strings (EN + 中文)"
```

---

## Task 3: Assets list view

**Files:**
- Create: `src/views/assets/AssetsView.vue`

- [ ] **Step 1: Create `src/views/assets/AssetsView.vue`**

```vue
<template>
  <div>
    <!-- Toolbar -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
      <div class="flex flex-wrap gap-3 items-center">
        <input v-model="filters.search" @input="debouncedFetch" :placeholder="t('asset.search')"
          class="flex-1 min-w-48 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />

        <select v-model="filters.status" @change="fetchData"
          class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
          <option value="">{{ t('common.all') }} {{ t('asset.status') }}</option>
          <option v-for="s in statuses" :key="s" :value="s">{{ t(`asset.status_labels.${s}`) }}</option>
        </select>

        <select v-model="filters.category" @change="fetchData"
          class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
          <option value="">{{ t('common.all') }} {{ t('asset.category') }}</option>
          <option v-for="c in categories" :key="c" :value="c">{{ t(`asset.category_labels.${c}`) }}</option>
        </select>

        <button @click="triggerImport"
          class="px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
          {{ t('asset.importBtn') }}
        </button>
        <input ref="importInput" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="onImport" />

        <button @click="onExport"
          class="px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
          {{ t('asset.exportBtn') }}
        </button>

        <router-link to="/assets/create"
          class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium">
          + {{ t('asset.actions.create') }}
        </router-link>
      </div>
    </div>

    <!-- List -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div v-if="store.loading" class="p-8 text-center text-gray-400">
        <div class="w-8 h-8 border-4 border-red-600 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
        {{ t('common.loading') }}
      </div>

      <div v-else-if="!store.assets.length" class="p-12 text-center">
        <p class="text-gray-400 mb-3">{{ t('asset.noAssets') }}</p>
        <router-link to="/assets/create" class="text-red-600 hover:text-red-800 text-sm font-medium">
          {{ t('asset.addFirst') }}
        </router-link>
      </div>

      <table v-else class="w-full">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('asset.assetTag') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('asset.name') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('asset.category') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('asset.status') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('asset.assignee') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">{{ t('asset.location') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="a in store.assets" :key="a.id"
            @click="$router.push(`/assets/${a.id}`)"
            class="hover:bg-gray-50 cursor-pointer transition">
            <td class="px-4 py-3 text-sm font-mono text-red-600">{{ a.asset_tag }}</td>
            <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ a.name }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ t(`asset.category_labels.${a.category}`) }}</td>
            <td class="px-4 py-3">
              <span class="px-2 py-1 rounded-full text-xs font-medium" :class="statusClass(a.status)">
                {{ t(`asset.status_labels.${a.status}`) }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ a.assignee?.name || t('asset.unassigned') }}</td>
            <td class="px-4 py-3 text-sm text-gray-500 hidden md:table-cell">{{ a.location || '—' }}</td>
          </tr>
        </tbody>
      </table>

      <div v-if="store.pagination.last_page > 1"
        class="px-4 py-3 border-t flex items-center justify-between text-sm text-gray-600">
        <span>{{ store.pagination.total }} total</span>
        <div class="flex gap-1 flex-wrap">
          <button v-for="page in store.pagination.last_page" :key="page" @click="goToPage(page)"
            class="w-8 h-8 rounded-lg text-sm transition"
            :class="page === store.pagination.current_page ? 'bg-red-600 text-white' : 'hover:bg-gray-100'">
            {{ page }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDebounceFn } from '@vueuse/core'
import { useAssetStore, ASSET_CATEGORIES, ASSET_STATUSES } from '@/stores/assets'
import { assetApi } from '@/api'

const { t } = useI18n()
const store = useAssetStore()

const categories = ASSET_CATEGORIES
const statuses = ASSET_STATUSES
const currentPage = ref(1)
const importInput = ref<HTMLInputElement | null>(null)

const filters = reactive({ search: '', status: '', category: '' })

function fetchData() {
  store.fetchAssets({ ...filters, page: currentPage.value })
}
const debouncedFetch = useDebounceFn(fetchData, 350)

function goToPage(page: number) {
  currentPage.value = page
  fetchData()
}

function triggerImport() {
  importInput.value?.click()
}

async function onImport(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  const fd = new FormData()
  fd.append('file', file)
  try {
    const { data } = await assetApi.import(fd)
    alert(t('asset.importResult', { created: data.created, rejected: data.rejected.length }))
    fetchData()
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Import failed.')
  } finally {
    if (importInput.value) importInput.value.value = ''
  }
}

async function onExport() {
  const { data } = await assetApi.export({ ...filters })
  const url = URL.createObjectURL(data)
  const link = document.createElement('a')
  link.href = url
  link.download = 'assets.xlsx'
  link.click()
  URL.revokeObjectURL(url)
}

function statusClass(s: string) {
  const map: Record<string, string> = {
    in_stock: 'bg-sky-100 text-sky-700',
    assigned: 'bg-green-100 text-green-700',
    in_repair: 'bg-yellow-100 text-yellow-700',
    retired: 'bg-gray-100 text-gray-600',
    lost: 'bg-red-100 text-red-700',
  }
  return map[s] || 'bg-gray-100 text-gray-600'
}

onMounted(fetchData)
</script>
```

- [ ] **Step 2: Build**

Run: `npm run build`
Expected: build succeeds (the view is type-checked even before it is routed).

- [ ] **Step 3: Commit**

```bash
git add it-helpdesk-frontend/src/views/assets/AssetsView.vue
git commit -m "feat(inventory-ui): assets list view with filters, import/export"
```

---

## Task 4: Shared form + create/edit views

**Files:**
- Create: `src/components/assets/AssetForm.vue`, `src/views/assets/CreateAssetView.vue`, `src/views/assets/EditAssetView.vue`

- [ ] **Step 1: Create `src/components/assets/AssetForm.vue`**

```vue
<template>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-2xl">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.name') }} *</label>
        <input v-model="form.name" class="input" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.category') }} *</label>
        <select v-model="form.category" class="input">
          <option v-for="c in categories" :key="c" :value="c">{{ t(`asset.category_labels.${c}`) }}</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.serialNumber') }}</label>
        <input v-model="form.serial_number" class="input" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.manufacturer') }}</label>
        <input v-model="form.manufacturer" class="input" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.model') }}</label>
        <input v-model="form.model" class="input" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.location') }}</label>
        <input v-model="form.location" class="input" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.department') }}</label>
        <select v-model="form.department_id" class="input">
          <option :value="null">—</option>
          <option v-for="d in departments" :key="d.id" :value="d.id">
            {{ locale === 'zh' ? d.name_zh : d.name }}
          </option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.purchaseDate') }}</label>
        <input v-model="form.purchase_date" type="date" class="input" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.purchaseCost') }}</label>
        <input v-model="form.purchase_cost" type="number" step="0.01" min="0" class="input" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.warrantyExpiry') }}</label>
        <input v-model="form.warranty_expiry" type="date" class="input" />
      </div>

      <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.notes') }}</label>
        <textarea v-model="form.notes" rows="3" class="input"></textarea>
      </div>
    </div>

    <p v-if="error" class="text-sm text-red-600 mt-3">{{ error }}</p>

    <div class="flex gap-3 mt-6">
      <button @click="$router.back()" class="px-4 py-2 border rounded-lg text-sm text-gray-700 hover:bg-gray-50">
        {{ t('common.cancel') }}
      </button>
      <button @click="submit" :disabled="saving"
        class="px-5 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 disabled:opacity-50">
        {{ saving ? t('common.loading') : t('common.save') }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAssetStore, ASSET_CATEGORIES, type Asset } from '@/stores/assets'
import { departmentApi } from '@/api'

const props = defineProps<{ asset?: Asset | null }>()
const { t, locale } = useI18n()
const router = useRouter()
const store = useAssetStore()

const categories = ASSET_CATEGORIES
const departments = ref<any[]>([])
const saving = ref(false)
const error = ref('')

const form = reactive({
  name: props.asset?.name ?? '',
  category: props.asset?.category ?? 'laptop',
  serial_number: props.asset?.serial_number ?? '',
  manufacturer: props.asset?.manufacturer ?? '',
  model: props.asset?.model ?? '',
  location: props.asset?.location ?? '',
  department_id: props.asset?.department_id ?? null,
  purchase_date: props.asset?.purchase_date?.slice(0, 10) ?? '',
  purchase_cost: props.asset?.purchase_cost ?? '',
  warranty_expiry: props.asset?.warranty_expiry?.slice(0, 10) ?? '',
  notes: props.asset?.notes ?? '',
})

function payload() {
  return {
    name: form.name,
    category: form.category,
    serial_number: form.serial_number || null,
    manufacturer: form.manufacturer || null,
    model: form.model || null,
    location: form.location || null,
    department_id: form.department_id,
    purchase_date: form.purchase_date || null,
    purchase_cost: form.purchase_cost === '' ? null : form.purchase_cost,
    warranty_expiry: form.warranty_expiry || null,
    notes: form.notes || null,
  }
}

async function submit() {
  error.value = ''
  saving.value = true
  try {
    if (props.asset) {
      await store.updateAsset(props.asset.id, payload())
      router.push(`/assets/${props.asset.id}`)
    } else {
      const created = await store.createAsset(payload())
      router.push(`/assets/${created.id}`)
    }
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('common.error')
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  const { data } = await departmentApi.list()
  departments.value = data
})
</script>

<style scoped>
.input {
  @apply w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:outline-none;
}
</style>
```

- [ ] **Step 2: Create `src/views/assets/CreateAssetView.vue`**

```vue
<template>
  <AssetForm />
</template>

<script setup lang="ts">
import AssetForm from '@/components/assets/AssetForm.vue'
</script>
```

- [ ] **Step 3: Create `src/views/assets/EditAssetView.vue`**

```vue
<template>
  <AssetForm v-if="asset" :asset="asset" />
  <div v-else class="p-8 text-center text-gray-400">{{ t('common.loading') }}</div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import AssetForm from '@/components/assets/AssetForm.vue'
import { useAssetStore, type Asset } from '@/stores/assets'

const { t } = useI18n()
const route = useRoute()
const store = useAssetStore()
const asset = ref<Asset | null>(null)

onMounted(async () => {
  asset.value = await store.fetchAsset(Number(route.params.id))
})
</script>
```

- [ ] **Step 4: Build**

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 5: Commit**

```bash
git add it-helpdesk-frontend/src/components/assets/AssetForm.vue it-helpdesk-frontend/src/views/assets/CreateAssetView.vue it-helpdesk-frontend/src/views/assets/EditAssetView.vue
git commit -m "feat(inventory-ui): asset create/edit form views"
```

---

## Task 5: Detail view (actions, history, attachments, QR, tickets)

**Files:**
- Create: `src/views/assets/AssetDetailView.vue`

- [ ] **Step 1: Create `src/views/assets/AssetDetailView.vue`**

```vue
<template>
  <div v-if="asset" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main -->
    <div class="lg:col-span-2 space-y-6">
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-sm font-mono text-red-600">{{ asset.asset_tag }}</p>
            <h1 class="text-xl font-semibold text-gray-800">{{ asset.name }}</h1>
          </div>
          <router-link :to="`/assets/${asset.id}/edit`"
            class="px-3 py-1.5 text-sm border rounded-lg text-gray-700 hover:bg-gray-50">
            {{ t('asset.actions.edit') }}
          </router-link>
        </div>

        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 mt-5 text-sm">
          <div><dt class="text-gray-400">{{ t('asset.category') }}</dt><dd class="text-gray-800">{{ t(`asset.category_labels.${asset.category}`) }}</dd></div>
          <div><dt class="text-gray-400">{{ t('asset.status') }}</dt>
            <dd><span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusClass(asset.status)">{{ t(`asset.status_labels.${asset.status}`) }}</span></dd></div>
          <div><dt class="text-gray-400">{{ t('asset.serialNumber') }}</dt><dd class="text-gray-800">{{ asset.serial_number || '—' }}</dd></div>
          <div><dt class="text-gray-400">{{ t('asset.assignee') }}</dt><dd class="text-gray-800">{{ asset.assignee?.name || t('asset.unassigned') }}</dd></div>
          <div><dt class="text-gray-400">{{ t('asset.manufacturer') }}</dt><dd class="text-gray-800">{{ asset.manufacturer || '—' }}</dd></div>
          <div><dt class="text-gray-400">{{ t('asset.model') }}</dt><dd class="text-gray-800">{{ asset.model || '—' }}</dd></div>
          <div><dt class="text-gray-400">{{ t('asset.location') }}</dt><dd class="text-gray-800">{{ asset.location || '—' }}</dd></div>
          <div><dt class="text-gray-400">{{ t('asset.warrantyExpiry') }}</dt><dd class="text-gray-800">{{ asset.warranty_expiry?.slice(0,10) || '—' }}</dd></div>
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
              <b>{{ h.user?.name }}</b> — {{ h.action }}
              <template v-if="h.field">({{ h.old_value || '∅' }} → {{ h.new_value || '∅' }})</template>
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
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import QRCode from 'qrcode'
import { useAssetStore, ASSET_STATUSES } from '@/stores/assets'
import { assetApi, userApi } from '@/api'

const { t } = useI18n()
const route = useRoute()
const store = useAssetStore()

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
    <p>${asset.value!.name}</p></div>`)
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
```

- [ ] **Step 2: Build**

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 3: Commit**

```bash
git add it-helpdesk-frontend/src/views/assets/AssetDetailView.vue
git commit -m "feat(inventory-ui): asset detail view with assign/status, history, attachments, QR"
```

---

## Task 6: Router + nav wiring

**Files:**
- Modify: `src/router/index.ts`, `src/components/layout/AppLayout.vue`

- [ ] **Step 1: Add routes + guard in `src/router/index.ts`**

Inside the `children` array of the `/` (AppLayout) route, after the ticket routes and before the `admin` child, add:

```ts
        {
          path: 'assets',
          name: 'assets',
          component: () => import('@/views/assets/AssetsView.vue'),
          meta: { requiresItStaff: true }
        },
        {
          path: 'assets/create',
          name: 'asset-create',
          component: () => import('@/views/assets/CreateAssetView.vue'),
          meta: { requiresItStaff: true }
        },
        {
          path: 'assets/:id',
          name: 'asset-detail',
          component: () => import('@/views/assets/AssetDetailView.vue'),
          meta: { requiresItStaff: true }
        },
        {
          path: 'assets/:id/edit',
          name: 'asset-edit',
          component: () => import('@/views/assets/EditAssetView.vue'),
          meta: { requiresItStaff: true }
        },
```

In `router.beforeEach`, after the `requiresAdmin` block, add:

```ts
  if (to.meta.requiresItStaff && !auth.isItStaff) {
    return next({ name: 'dashboard' })
  }
```

- [ ] **Step 2: Add the nav entry + pageTitle in `src/components/layout/AppLayout.vue`**

Add the heroicon import near the other imports:

```ts
import { ComputerDesktopIcon } from '@heroicons/vue/24/outline'
```

Change the `NavItem` type so `icon` can be a component: change `icon?: string` to `icon?: any`.

Replace the `const navItems: NavItem[] = [...]` declaration with a computed that adds Assets for IT staff:

```ts
const navItems = computed((): NavItem[] => {
  const items: NavItem[] = [
    { name: 'dashboard', to: '/', label: 'nav.dashboard', iconImg: '/Dash.png' },
    { name: 'tickets', to: '/tickets', label: 'nav.tickets', iconImg: '/icons8-ticket-50.png', prefix: 'ticket' },
  ]
  if (auth.isItStaff) {
    items.push({ name: 'assets', to: '/assets', label: 'nav.assets', icon: ComputerDesktopIcon, prefix: 'asset' })
  }
  return items
})
```

In the `pageTitle` computed's `map`, add:

```ts
    assets: t('asset.title'),
    'asset-create': t('asset.actions.create'),
    'asset-edit': t('asset.actions.edit'),
    'asset-detail': t('asset.title'),
```

- [ ] **Step 3: Build**

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 4: Commit**

```bash
git add it-helpdesk-frontend/src/router/index.ts it-helpdesk-frontend/src/components/layout/AppLayout.vue
git commit -m "feat(inventory-ui): wire asset routes, IT-only guard, and nav entry"
```

---

## Task 7: Ticket ↔ asset picker

**Files:**
- Modify: `src/stores/tickets.ts`, `src/views/tickets/CreateTicketView.vue`, `src/views/tickets/TicketDetailView.vue`

- [ ] **Step 1: Add `asset_id` + asset to the Ticket interface in `src/stores/tickets.ts`**

In the `Ticket` interface, after `department_id: number`, add:

```ts
  asset_id: number | null
```

and after the `department?: {...}` line add:

```ts
  asset?: { id: number; asset_tag: string; name: string } | null
```

- [ ] **Step 2: Add an optional asset select to `src/views/tickets/CreateTicketView.vue`**

Load assets and bind an `asset_id`. In `<script setup>`, add the import and state:

```ts
import { assetApi } from '@/api'
const assets = ref<any[]>([])
const selectedAssetId = ref<number | null>(null)
```

In the existing `onMounted` (where departments are loaded), also load assets:

```ts
  const { data: assetData } = await assetApi.list({ /* no filter: first page */ })
  assets.value = assetData.data
```

When building the ticket payload that is sent on submit, include `asset_id: selectedAssetId.value` (add the key to the object/FormData the same way `department_id` is added; if FormData, `formData.append('asset_id', String(selectedAssetId.value ?? ''))` and skip when null).

In the template, after the department `<select>` block, add:

```vue
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.title') }}</label>
          <select v-model="selectedAssetId"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
            <option :value="null">—</option>
            <option v-for="a in assets" :key="a.id" :value="a.id">{{ a.asset_tag }} — {{ a.name }}</option>
          </select>
        </div>
```

> Note: the asset list endpoint is IT-only. End users creating tickets will get an empty list (the request 403s and is caught), which is acceptable — the field simply shows only "—" for them. If you prefer to hide the field entirely for non-IT users, wrap the block in `v-if="auth.isItStaff"`.

- [ ] **Step 3: Show the linked asset in `src/views/tickets/TicketDetailView.vue`**

Where the ticket's department/meta is displayed, add a read-only line (only when present):

```vue
        <div v-if="ticket.asset">
          <span class="text-gray-400 text-sm">{{ t('asset.title') }}</span>
          <router-link :to="`/assets/${ticket.asset.id}`" class="text-sm text-blue-600 hover:underline">
            {{ ticket.asset.asset_tag }} — {{ ticket.asset.name }}
          </router-link>
        </div>
```

(`ticket` is the current ticket object already used in that view; place this next to the existing department display.)

- [ ] **Step 4: Build**

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 5: Commit**

```bash
git add it-helpdesk-frontend/src/stores/tickets.ts it-helpdesk-frontend/src/views/tickets/CreateTicketView.vue it-helpdesk-frontend/src/views/tickets/TicketDetailView.vue
git commit -m "feat(inventory-ui): optional asset link on tickets"
```

---

## Final manual verification

The frontend has no automated UI tests, so finish with a manual smoke test against the running backend.

- [ ] Start the backend (Docker dev container already built in the backend plan):
  `docker start helpdesk-dev` then serve it, **or** run the stack per `docker-compose.yml`. Ensure `php artisan migrate --seed` has been run so sample assets exist.
- [ ] Start the frontend: from `it-helpdesk-frontend/`, `npm run dev` (proxy/baseURL is `/api`).
- [ ] Log in as an **IT staff / admin** user (e.g. seeded `staff@helpdesk.local` / `password`).
- [ ] Verify: "Assets" appears in the sidebar; list loads with seeded assets; filters work; create an asset; open it; assign to a user (status → Assigned, history row appears); change status; upload a PDF; QR label renders and prints; export downloads an `.xlsx`; import a small file reports created/rejected.
- [ ] Log in as a **regular user**: "Assets" is hidden and visiting `/assets` redirects to the dashboard.
- [ ] On a ticket, link an asset and confirm it shows on the ticket detail and under the asset's "Related Tickets".

> Optional: the `run-it-helpdesk` skill can launch the app and screenshot it for a faster check.

---

## Self-Review notes (already applied)

- **Spec coverage:** list/filter/import/export (Task 3), create/edit (Task 4), detail + assign/return/status + history + attachments + QR + related tickets (Task 5), IT-only nav + route guard (Task 6), ticket↔asset picker (Task 7), i18n EN/中文 (Task 2), deps + api + store (Task 1). Category/status key lists are duplicated as frontend constants that must match backend `AssetCategories` (kept in `stores/assets.ts`).
- **Type consistency:** `useAssetStore` exposes `fetchAssets/fetchAsset/createAsset/updateAsset/assignAsset/changeStatus` — used with those exact names in Tasks 3–5. `assetApi` method names (`list/meta/get/create/update/remove/assign/updateStatus/uploadAttachments/deleteAttachment/export/import`) match their call sites. Attachment upload uses `attachments[]` to match the backend `attachments` array validation.
- **Verification gate:** `npm run build` (vue-tsc) after every task; no JS test runner exists, so the final manual smoke test covers behavior.
- **Deferred per spec:** warranty-expiry alerts; consumables; end-user "my devices" view.
