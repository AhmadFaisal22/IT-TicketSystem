<template>
  <div>
    <div class="bg-white rounded-card shadow-soft border border-gray-100 overflow-hidden">
      <div class="px-6 py-4 border-b flex items-center justify-between">
        <h2 class="font-semibold text-gray-800">{{ t('admin.sla.title') }}</h2>
        <button @click="openModal()"
          class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-1.5 rounded-lg text-sm font-medium transition">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          {{ t('admin.sla.add') }}
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('admin.sla.department') }}</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('admin.sla.priority') }}</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('admin.sla.responseHours') }}</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('admin.sla.resolutionHours') }}</th>
              <th class="px-4 py-3 w-24"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="policy in policies" :key="policy.id" class="hover:bg-gray-50 transition">
              <td class="px-4 py-3 text-sm text-gray-700">
                {{ policy.department ? (locale === 'zh' ? policy.department.name_zh : policy.department.name) : t('admin.sla.default') }}
              </td>
              <td class="px-4 py-3">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="priorityClass(policy.priority)">
                  {{ t(`ticket.${policy.priority}`) }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ policy.response_hours }}h</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ policy.resolution_hours }}h</td>
              <td class="px-3 py-3">
                <div class="flex items-center gap-1 justify-end">
                  <button @click="openModal(policy)"
                    class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
                    :title="t('common.edit')">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                  </button>
                  <button @click="deletePolicy(policy)"
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
              {{ editing ? t('admin.sla.edit') : t('admin.sla.add') }}
            </h3>
            <button @click="showModal = false" class="p-1 rounded-lg text-gray-400 hover:bg-gray-100 transition">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="px-6 py-5 space-y-4">
            <!-- When editing: show department + priority as read-only (they are the composite key) -->
            <div v-if="editing" class="p-3 bg-gray-50 rounded-lg space-y-1.5">
              <p class="text-sm text-gray-500">
                {{ t('admin.sla.department') }}:
                <span class="font-medium text-gray-800">
                  {{ editing.department ? (locale === 'zh' ? editing.department.name_zh : editing.department.name) : t('admin.sla.default') }}
                </span>
              </p>
              <p class="text-sm text-gray-500 flex items-center gap-2">
                {{ t('admin.sla.priority') }}:
                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="priorityClass(editing.priority)">
                  {{ t(`ticket.${editing.priority}`) }}
                </span>
              </p>
            </div>

            <!-- When adding: selectable department + priority -->
            <template v-else>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin.sla.department') }}</label>
                <select v-model="form.department_id"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                  <option value="">{{ t('admin.sla.default') }}</option>
                  <option v-for="d in departments" :key="d.id" :value="d.id">
                    {{ locale === 'zh' ? d.name_zh : d.name }}
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin.sla.priority') }}</label>
                <select v-model="form.priority"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                  <option value="critical">{{ t('ticket.critical') }}</option>
                  <option value="high">{{ t('ticket.high') }}</option>
                  <option value="medium">{{ t('ticket.medium') }}</option>
                  <option value="low">{{ t('ticket.low') }}</option>
                </select>
              </div>
            </template>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin.sla.responseHours') }}</label>
                <input v-model.number="form.response_hours" type="number" min="1"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin.sla.resolutionHours') }}</label>
                <input v-model.number="form.resolution_hours" type="number" min="1"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none" />
              </div>
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
import { slaApi, departmentApi } from '@/api'

const { t, locale } = useI18n()
const policies = ref<any[]>([])
const departments = ref<any[]>([])
const showModal = ref(false)
const saving = ref(false)
const editing = ref<any>(null)
const form = reactive({ department_id: '' as any, priority: 'medium', response_hours: 8, resolution_hours: 24 })

async function load() {
  const [{ data: p }, { data: d }] = await Promise.all([slaApi.list(), departmentApi.list()])
  policies.value = p
  departments.value = d
}

function openModal(policy?: any) {
  editing.value = policy || null
  if (policy) {
    form.department_id = policy.department_id ?? ''
    form.priority = policy.priority
    form.response_hours = policy.response_hours
    form.resolution_hours = policy.resolution_hours
  } else {
    form.department_id = ''
    form.priority = 'medium'
    form.response_hours = 8
    form.resolution_hours = 24
  }
  showModal.value = true
}

async function save() {
  saving.value = true
  try {
    await slaApi.save({ ...form, department_id: form.department_id || null })
    showModal.value = false
    await load()
  } finally {
    saving.value = false
  }
}

async function deletePolicy(policy: any) {
  if (confirm('Delete this SLA policy?')) {
    await slaApi.delete(policy.id)
    await load()
  }
}

function priorityClass(p: string) {
  const map: Record<string, string> = {
    critical: 'bg-red-100 text-red-700', high: 'bg-orange-100 text-orange-700',
    medium: 'bg-yellow-100 text-yellow-700', low: 'bg-gray-100 text-gray-600'
  }
  return map[p] || 'bg-gray-100'
}

onMounted(load)
</script>
