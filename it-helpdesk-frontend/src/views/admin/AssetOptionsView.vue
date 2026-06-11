<template>
  <div class="space-y-6">
    <div v-for="section in sections" :key="section.key"
      class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="px-6 py-4 border-b flex items-center justify-between">
        <h2 class="font-semibold text-gray-800">{{ t(`admin.assetOptions.${section.key}`) }}</h2>
        <button @click="openModal(section)"
          class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-1.5 rounded-lg text-sm font-medium transition">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          {{ t(section.addLabel) }}
        </button>
      </div>

      <table class="w-full">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('admin.assetOptions.name') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('admin.assetOptions.nameChinese') }}</th>
            <th class="px-4 py-3 w-24"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="item in section.items.value" :key="item.id" class="hover:bg-gray-50 transition">
            <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ item.name }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ item.name_zh || '—' }}</td>
            <td class="px-3 py-3">
              <div class="flex items-center gap-1 justify-end">
                <button @click="openModal(section, item)"
                  class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
                  :title="t('common.edit')">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                  </svg>
                </button>
                <button @click="deleteItem(section, item)"
                  class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                  :title="t('common.delete')">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!section.items.value.length">
            <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-400">—</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="showModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="font-semibold text-gray-800">
              {{ editing ? t(activeSection!.editLabel) : t(activeSection!.addLabel) }}
            </h3>
            <button @click="showModal = false" class="p-1 rounded-lg text-gray-400 hover:bg-gray-100 transition">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin.assetOptions.name') }} *</label>
              <input v-model="form.name"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin.assetOptions.nameChinese') }}</label>
              <input v-model="form.name_zh"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:outline-none" />
            </div>
            <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
          </div>

          <div class="flex gap-3 px-6 pb-5">
            <button @click="showModal = false"
              class="flex-1 px-4 py-2 border rounded-lg text-sm text-gray-700 hover:bg-gray-50">
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
import { ref, shallowRef, reactive, onMounted, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { assetCategoryApi, assetLocationApi } from '@/api'

type Option = { id: number; name: string; name_zh: string | null }
type OptionApi = typeof assetCategoryApi

const { t } = useI18n()

const categories = ref<Option[]>([])
const locations = ref<Option[]>([])

type Section = { key: string; api: OptionApi; items: Ref<Option[]>; addLabel: string; editLabel: string }
const sections: Section[] = [
  { key: 'categories', api: assetCategoryApi, items: categories, addLabel: 'admin.assetOptions.addCategory', editLabel: 'admin.assetOptions.editCategory' },
  { key: 'locations', api: assetLocationApi, items: locations, addLabel: 'admin.assetOptions.addLocation', editLabel: 'admin.assetOptions.editLocation' },
]

const showModal = ref(false)
const activeSection = shallowRef<Section | null>(null)
const editing = ref<Option | null>(null)
const saving = ref(false)
const error = ref('')
const form = reactive({ name: '', name_zh: '' })

async function load(section: Section) {
  const { data } = await section.api.list()
  section.items.value = data
}

function openModal(section: Section, item?: Option) {
  activeSection.value = section
  editing.value = item || null
  form.name = item?.name || ''
  form.name_zh = item?.name_zh || ''
  error.value = ''
  showModal.value = true
}

async function save() {
  if (!activeSection.value) return
  saving.value = true
  error.value = ''
  try {
    if (editing.value) {
      await activeSection.value.api.update(editing.value.id, form)
    } else {
      await activeSection.value.api.create(form)
    }
    showModal.value = false
    await load(activeSection.value)
  } catch (e: any) {
    error.value = e?.response?.data?.message || t('common.error')
  } finally {
    saving.value = false
  }
}

async function deleteItem(section: Section, item: Option) {
  if (!confirm(`Delete "${item.name}"?`)) return
  await section.api.delete(item.id)
  await load(section)
}

onMounted(() => sections.forEach(load))
</script>
