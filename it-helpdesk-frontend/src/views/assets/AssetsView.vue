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
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('asset.lastName') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('asset.firstName') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('asset.name') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('asset.category') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">{{ t('asset.manufacturer') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">{{ t('asset.model') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">{{ t('asset.serialNumber') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('asset.status') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="a in store.assets" :key="a.id"
            @click="$router.push(`/assets/${a.id}`)"
            class="hover:bg-gray-50 cursor-pointer transition">
            <td class="px-4 py-3 text-sm font-mono text-red-600">{{ a.asset_tag }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ a.last_name || '—' }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ a.first_name || '—' }}</td>
            <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ a.name }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ t(`asset.category_labels.${a.category}`) }}</td>
            <td class="px-4 py-3 text-sm text-gray-600 hidden md:table-cell">{{ a.manufacturer || '—' }}</td>
            <td class="px-4 py-3 text-sm text-gray-600 hidden md:table-cell">{{ a.model || '—' }}</td>
            <td class="px-4 py-3 text-sm text-gray-500 hidden md:table-cell">{{ a.serial_number || '—' }}</td>
            <td class="px-4 py-3">
              <span class="px-2 py-1 rounded-full text-xs font-medium" :class="statusClass(a.status)">
                {{ t(`asset.status_labels.${a.status}`) }}
              </span>
            </td>
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
