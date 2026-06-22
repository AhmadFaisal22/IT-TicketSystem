<template>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b flex items-center justify-between">
      <h2 class="font-semibold text-gray-800">{{ t('admin.manufacturers.title') }}</h2>
      <button @click="openModal()"
        class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-1.5 rounded-lg text-sm font-medium transition">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        {{ t('admin.manufacturers.add') }}
      </button>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('admin.manufacturers.name') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('admin.manufacturers.shortName') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('admin.manufacturers.contact') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('admin.manufacturers.countryOfOrigin') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('admin.manufacturers.status') }}</th>
            <th class="px-4 py-3 w-24"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="m in items" :key="m.id" class="hover:bg-gray-50 transition">
            <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ m.name }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ m.short_name || '—' }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ m.contact || '—' }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ m.country_of_origin || '—' }}</td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                :class="m.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
                {{ t(`admin.manufacturers.${m.status}`) }}
              </span>
            </td>
            <td class="px-3 py-3">
              <div class="flex items-center gap-1 justify-end">
                <button @click="openModal(m)"
                  class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" :title="t('common.edit')">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                  </svg>
                </button>
                <button @click="deleteItem(m)"
                  class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" :title="t('common.delete')">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!items.length">
            <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-400">{{ t('admin.manufacturers.empty') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="showModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between px-6 py-4 border-b sticky top-0 bg-white">
            <h3 class="font-semibold text-gray-800">{{ editing ? t('admin.manufacturers.edit') : t('admin.manufacturers.add') }}</h3>
            <button @click="showModal = false" class="p-1 rounded-lg text-gray-400 hover:bg-gray-100 transition">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="px-6 py-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin.manufacturers.name') }} *</label>
              <input v-model="form.name" class="fld" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin.manufacturers.shortName') }}</label>
              <input v-model="form.short_name" class="fld" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin.manufacturers.contact') }}</label>
              <input v-model="form.contact" class="fld" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin.manufacturers.supportPhone') }}</label>
              <input v-model="form.support_phone" class="fld" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin.manufacturers.supportEmail') }}</label>
              <input v-model="form.support_email" type="email" class="fld" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin.manufacturers.countryOfOrigin') }}</label>
              <input v-model="form.country_of_origin" class="fld" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin.manufacturers.status') }}</label>
              <select v-model="form.status" class="fld">
                <option value="active">{{ t('admin.manufacturers.active') }}</option>
                <option value="inactive">{{ t('admin.manufacturers.inactive') }}</option>
              </select>
            </div>
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin.manufacturers.notes') }}</label>
              <textarea v-model="form.notes" rows="3" class="fld"></textarea>
            </div>
            <p v-if="error" class="sm:col-span-2 text-sm text-red-600">{{ error }}</p>
          </div>

          <div class="flex gap-3 px-6 pb-5">
            <button @click="showModal = false" class="flex-1 px-4 py-2 border rounded-lg text-sm text-gray-700 hover:bg-gray-50">
              {{ t('common.cancel') }}
            </button>
            <button @click="save" :disabled="saving"
              class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 disabled:opacity-50">
              {{ saving ? t('common.loading') : t('common.save') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { assetManufacturerApi } from '@/api'

type Manufacturer = {
  id: number; name: string; short_name: string | null; contact: string | null
  support_phone: string | null; support_email: string | null
  country_of_origin: string | null; notes: string | null; status: string
}

const { t } = useI18n()

const items = ref<Manufacturer[]>([])
const showModal = ref(false)
const editing = ref<Manufacturer | null>(null)
const saving = ref(false)
const error = ref('')

const blank = () => ({
  name: '', short_name: '', contact: '', support_phone: '',
  support_email: '', country_of_origin: '', notes: '', status: 'active',
})
const form = reactive(blank())

async function load() {
  const { data } = await assetManufacturerApi.list()
  items.value = data
}

function openModal(m?: Manufacturer) {
  editing.value = m || null
  Object.assign(form, blank(), m ? {
    name: m.name, short_name: m.short_name ?? '', contact: m.contact ?? '',
    support_phone: m.support_phone ?? '', support_email: m.support_email ?? '',
    country_of_origin: m.country_of_origin ?? '', notes: m.notes ?? '', status: m.status,
  } : {})
  error.value = ''
  showModal.value = true
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    if (editing.value) {
      await assetManufacturerApi.update(editing.value.id, { ...form })
    } else {
      await assetManufacturerApi.create({ ...form })
    }
    showModal.value = false
    await load()
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('common.error')
  } finally {
    saving.value = false
  }
}

async function deleteItem(m: Manufacturer) {
  if (!confirm(`Delete "${m.name}"?`)) return
  await assetManufacturerApi.delete(m.id)
  await load()
}

onMounted(load)
</script>

<style scoped>
.fld { @apply w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:outline-none; }
</style>
