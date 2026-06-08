<template>
  <div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="px-6 py-4 border-b flex items-center justify-between">
        <h2 class="font-semibold text-gray-800">{{ t('admin.departments.title') }}</h2>
        <button @click="openModal()"
          class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-1.5 rounded-lg text-sm font-medium transition">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          {{ t('admin.departments.add') }}
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('admin.departments.name') }}</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('admin.departments.nameChinese') }}</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">{{ t('admin.departments.description') }}</th>
              <th class="px-4 py-3 w-24"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="dept in departments" :key="dept.id" class="hover:bg-gray-50 transition">
              <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ dept.name }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ dept.name_zh }}</td>
              <td class="px-4 py-3 text-sm text-gray-500 hidden md:table-cell">{{ dept.description || '—' }}</td>
              <td class="px-3 py-3">
                <div class="flex items-center gap-1 justify-end">
                  <button @click="openModal(dept)"
                    class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
                    :title="t('common.edit')">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                  </button>
                  <button @click="deleteDept(dept)"
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
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="showModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md">

          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="font-semibold text-gray-800">
              {{ editing ? t('admin.departments.edit') : t('admin.departments.add') }}
            </h3>
            <button @click="showModal = false" class="p-1 rounded-lg text-gray-400 hover:bg-gray-100 transition">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="px-6 py-5 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin.departments.name') }} *</label>
              <input v-model="form.name"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin.departments.nameChinese') }} *</label>
              <input v-model="form.name_zh"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin.departments.description') }}</label>
              <input v-model="form.description"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:outline-none" />
            </div>
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
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { departmentApi } from '@/api'

const { t } = useI18n()
const departments = ref<any[]>([])
const showModal = ref(false)
const editing = ref<any>(null)
const saving = ref(false)
const form = reactive({ name: '', name_zh: '', description: '' })

async function load() {
  const { data } = await departmentApi.list()
  departments.value = data
}

function openModal(dept?: any) {
  editing.value = dept || null
  form.name = dept?.name || ''
  form.name_zh = dept?.name_zh || ''
  form.description = dept?.description || ''
  showModal.value = true
}

async function save() {
  saving.value = true
  try {
    if (editing.value) {
      await departmentApi.update(editing.value.id, form)
    } else {
      await departmentApi.create(form)
    }
    showModal.value = false
    await load()
  } finally {
    saving.value = false
  }
}

async function deleteDept(dept: any) {
  if (confirm(`Delete "${dept.name}"?`)) {
    await departmentApi.delete(dept.id)
    await load()
  }
}

onMounted(load)
</script>
