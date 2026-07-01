<template>
  <div class="space-y-6">

    <!-- Page header -->
    <div class="bg-white rounded-card shadow-soft border border-gray-100 px-6 py-4 flex items-center justify-between">
      <div>
        <h2 class="font-semibold text-gray-800 text-base">{{ t('admin.approval.title') }}</h2>
        <p class="text-xs text-gray-400 mt-0.5">{{ t('admin.approval.subtitle') }}</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-8 h-8 border-4 border-red-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Department cards — always show all departments -->
    <div v-else class="space-y-4">
      <div v-for="dept in allDepartments" :key="dept.id"
        class="bg-white rounded-card shadow-soft border border-gray-100 overflow-hidden">

        <!-- Department header -->
        <div class="px-6 py-3 bg-gray-50 border-b flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <div class="w-2 h-2 rounded-full bg-red-500"></div>
            <span class="font-semibold text-gray-800 text-sm">
              {{ locale === 'zh' ? dept.name_zh : dept.name }}
            </span>
            <span class="text-xs text-gray-400 bg-white border border-gray-200 px-2 py-0.5 rounded-full">
              {{ levelsByDept(dept.id).length }}
              {{ levelsByDept(dept.id).length === 1 ? 'step' : 'steps' }}
            </span>
          </div>
          <button @click="openModal(dept.id)"
            class="text-xs font-medium text-red-600 hover:text-red-700 flex items-center gap-1 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg transition">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add step
          </button>
        </div>

        <!-- Approval flow -->
        <div class="p-5">

          <!-- No levels: show empty placeholder -->
          <div v-if="!levelsByDept(dept.id).length"
            class="flex items-center gap-3 border-2 border-dashed border-gray-200 rounded-xl px-5 py-4">
            <!-- Submitter -->
            <div class="flex flex-col items-center gap-1 flex-shrink-0">
              <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center">
                <svg class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
              </div>
              <span class="text-[10px] text-gray-400 font-medium">Submitter</span>
            </div>
            <!-- Arrow → -->
            <div class="flex items-center flex-shrink-0">
              <div class="w-5 h-0.5 bg-gray-300"></div>
              <svg class="w-3 h-3 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
              </svg>
            </div>
            <!-- Placeholder -->
            <button @click="openModal(dept.id)"
              class="flex flex-col items-center gap-1 border-2 border-dashed border-gray-300 rounded-xl px-5 py-3 hover:border-red-400 hover:bg-red-50 transition group">
              <svg class="w-5 h-5 text-gray-300 group-hover:text-red-400 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              <span class="text-xs text-gray-400 group-hover:text-red-500">Add approval step</span>
            </button>
            <!-- Arrow → -->
            <div class="flex items-center flex-shrink-0">
              <div class="w-5 h-0.5 bg-gray-300"></div>
              <svg class="w-3 h-3 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
              </svg>
            </div>
            <!-- IT -->
            <div class="flex flex-col items-center gap-1 flex-shrink-0">
              <div class="w-9 h-9 rounded-full bg-green-100 flex items-center justify-center">
                <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </div>
              <span class="text-[10px] text-green-700 font-semibold">IT Staff</span>
            </div>
          </div>

          <!-- Has levels: flow visualization -->
          <div v-else>

            <!-- Horizontal flow row -->
            <div class="flex flex-wrap items-start gap-2">

              <!-- Submitter node -->
              <div class="flex flex-col items-center gap-1 flex-shrink-0 mt-2">
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                  <svg class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                  </svg>
                </div>
                <span class="text-[10px] text-gray-500 font-medium">Submitter</span>
              </div>

              <template v-for="(level, idx) in levelsByDept(dept.id)" :key="level.id">
                <!-- Arrow -->
                <div class="flex items-center self-center mt-[-10px]">
                  <div class="w-5 h-0.5 bg-gray-300"></div>
                  <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                      d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z"
                      clip-rule="evenodd"/>
                  </svg>
                </div>

                <!-- Level card -->
                <div class="relative group flex-shrink-0">
                  <div class="border rounded-xl px-3 py-3 w-44 transition"
                    :class="level.is_active
                      ? 'border-amber-200 bg-amber-50'
                      : 'border-gray-200 bg-gray-50 opacity-55'">

                    <!-- Top row: order badge + reorder + inactive tag -->
                    <div class="flex items-center justify-between mb-2.5">
                      <span class="text-[10px] font-bold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded-md leading-none">
                        Step {{ idx + 1 }}
                      </span>
                      <div class="flex items-center gap-0.5">
                        <button v-if="idx > 0"
                          @click.stop="moveUp(level, dept.id)"
                          title="Move up"
                          class="p-0.5 rounded text-gray-300 hover:text-gray-600 hover:bg-white transition">
                          <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/>
                          </svg>
                        </button>
                        <button v-if="idx < levelsByDept(dept.id).length - 1"
                          @click.stop="moveDown(level, dept.id)"
                          title="Move down"
                          class="p-0.5 rounded text-gray-300 hover:text-gray-600 hover:bg-white transition">
                          <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                          </svg>
                        </button>
                        <span v-if="!level.is_active"
                          class="text-[9px] text-gray-400 bg-gray-200 px-1 py-0.5 rounded leading-none ml-1">
                          Off
                        </span>
                      </div>
                    </div>

                    <!-- Approver avatar + name -->
                    <div class="flex items-center gap-2 mb-2.5">
                      <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center text-sm font-bold text-red-700 flex-shrink-0">
                        {{ level.approver?.name?.[0]?.toUpperCase() ?? '?' }}
                      </div>
                      <div class="min-w-0">
                        <p class="text-xs font-semibold text-gray-800 leading-tight truncate">
                          {{ level.approver?.name ?? '—' }}
                        </p>
                        <p class="text-[10px] text-gray-400 truncate">{{ level.name }}</p>
                      </div>
                    </div>

                    <!-- Action row -->
                    <div class="flex items-center gap-1 pt-2 border-t border-amber-100">
                      <button @click.stop="openEditModal(level)"
                        class="flex-1 text-[10px] text-gray-400 hover:text-blue-600 flex items-center justify-center gap-1 py-1 rounded hover:bg-white transition">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit
                      </button>
                      <button @click.stop="toggleActive(level)"
                        class="flex-1 text-[10px] flex items-center justify-center gap-1 py-1 rounded hover:bg-white transition"
                        :class="level.is_active ? 'text-gray-400 hover:text-yellow-600' : 'text-gray-400 hover:text-green-600'">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        {{ level.is_active ? 'Disable' : 'Enable' }}
                      </button>
                      <button @click.stop="deleteLevel(level)"
                        class="flex-1 text-[10px] text-gray-400 hover:text-red-600 flex items-center justify-center gap-1 py-1 rounded hover:bg-white transition">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Delete
                      </button>
                    </div>
                  </div>
                </div>
              </template>

              <!-- Final arrow + IT node -->
              <div class="flex items-center self-center mt-[-10px]">
                <div class="w-5 h-0.5 bg-gray-300"></div>
                <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd"
                    d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z"
                    clip-rule="evenodd"/>
                </svg>
              </div>

              <div class="flex flex-col items-center gap-1 flex-shrink-0 mt-2">
                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                  <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                </div>
                <span class="text-[10px] text-green-700 font-semibold">IT Staff</span>
              </div>

              <!-- Inline add button after IT node -->
              <button @click="openModal(dept.id)"
                title="Add another step"
                class="self-center mt-[-10px] ml-1 w-7 h-7 rounded-full border-2 border-dashed border-gray-300 flex items-center justify-center text-gray-300 hover:border-red-400 hover:text-red-400 transition">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Level Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="closeModal"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">

          <div class="flex items-center justify-between px-6 py-4 border-b">
            <div>
              <h3 class="font-semibold text-gray-800">
                {{ editingLevel ? 'Edit approval step' : 'Add approval step' }}
              </h3>
              <p v-if="modalDeptName" class="text-xs text-gray-400 mt-0.5">{{ modalDeptName }}</p>
            </div>
            <button @click="closeModal"
              class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 transition">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="px-6 py-5 space-y-4">

            <!-- Step name -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Step name <span class="text-red-500">*</span>
              </label>
              <input v-model="form.name"
                placeholder="e.g. Department Head Approval"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none"
                @keydown.enter="save" />
            </div>

            <!-- Department (add mode only) -->
            <div v-if="!editingLevel">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Department <span class="text-red-500">*</span>
              </label>
              <select v-model="form.department_id"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                <option value="">Select department…</option>
                <option v-for="d in allDepartments" :key="d.id" :value="d.id">
                  {{ locale === 'zh' ? d.name_zh : d.name }}
                </option>
              </select>
            </div>

            <!-- Approver -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Approver <span class="text-red-500">*</span>
              </label>
              <select v-model="form.approver_id"
                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none">
                <option value="">Select approver…</option>
                <option v-for="u in allUsers" :key="u.id" :value="u.id">
                  {{ u.name }}
                  <template v-if="u.department?.name"> — {{ u.department.name }}</template>
                  <template v-if="u.role !== 'user'"> ({{ u.role === 'admin' ? 'Admin' : 'IT Staff' }})</template>
                </option>
              </select>
              <p v-if="allUsers.length === 0 && !loading" class="text-xs text-red-500 mt-1">
                No users found. Make sure users exist in the system.
              </p>
            </div>

            <!-- Active toggle -->
            <label class="flex items-center gap-3 cursor-pointer p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition">
              <div class="relative flex-shrink-0">
                <input v-model="form.is_active" type="checkbox" class="sr-only peer" />
                <div class="w-9 h-5 bg-gray-200 rounded-full peer-checked:bg-green-500 transition-colors"></div>
                <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-700">Active</p>
                <p class="text-xs text-gray-400">Include this step in the approval chain</p>
              </div>
            </label>
          </div>

          <!-- Error -->
          <div v-if="formError" class="mx-6 mb-3">
            <p class="text-xs text-red-600 bg-red-50 border border-red-200 px-3 py-2 rounded-lg">{{ formError }}</p>
          </div>

          <div class="flex gap-3 px-6 pb-6">
            <button @click="closeModal"
              class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition">
              Cancel
            </button>
            <button @click="save" :disabled="saving"
              class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 disabled:opacity-50 transition">
              {{ saving ? 'Saving…' : (editingLevel ? 'Save changes' : 'Add step') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { approvalApi, departmentApi, userApi } from '@/api'

const { t, locale } = useI18n()

const loading    = ref(true)
const saving     = ref(false)
const formError  = ref('')
const showModal  = ref(false)
const editingLevel = ref<any>(null)
const modalDeptId  = ref<any>(null)

const levels         = ref<any[]>([])
const allDepartments = ref<any[]>([])
const allUsers       = ref<any[]>([])

const form = reactive({
  name:          '',
  department_id: '' as any,
  approver_id:   '' as any,
  is_active:     true,
})

const modalDeptName = computed(() => {
  if (!modalDeptId.value) return ''
  const d = allDepartments.value.find(d => d.id === modalDeptId.value)
  return d ? (locale.value === 'zh' ? d.name_zh : d.name) : ''
})

function levelsByDept(deptId: number) {
  return levels.value
    .filter(l => l.department_id === deptId)
    .sort((a, b) => a.level_order - b.level_order)
}

// ── Modal helpers ────────────────────────────────────────────────────────────

function openModal(deptId: number) {
  editingLevel.value  = null
  modalDeptId.value   = deptId
  formError.value     = ''
  const existing      = levelsByDept(deptId)
  form.name           = ''
  form.department_id  = deptId
  form.approver_id    = ''
  form.is_active      = true
  showModal.value     = true
}

function openEditModal(level: any) {
  editingLevel.value  = level
  modalDeptId.value   = level.department_id
  formError.value     = ''
  form.name           = level.name
  form.department_id  = level.department_id
  form.approver_id    = level.approver_id
  form.is_active      = level.is_active
  showModal.value     = true
}

function closeModal() {
  showModal.value    = false
  editingLevel.value = null
  formError.value    = ''
}

// ── CRUD ─────────────────────────────────────────────────────────────────────

async function save() {
  if (!form.name.trim())      { formError.value = 'Step name is required.'; return }
  if (!form.department_id)    { formError.value = 'Department is required.'; return }
  if (!form.approver_id)      { formError.value = 'Approver is required.'; return }

  saving.value    = true
  formError.value = ''
  try {
    if (editingLevel.value) {
      await approvalApi.update(editingLevel.value.id, {
        name:        form.name,
        approver_id: form.approver_id,
        is_active:   form.is_active,
      })
    } else {
      // Auto-assign level_order as next in chain for this department
      const existing   = levelsByDept(form.department_id)
      const nextOrder  = existing.length ? Math.max(...existing.map(l => l.level_order)) + 1 : 1
      await approvalApi.create({
        name:          form.name,
        department_id: form.department_id,
        approver_id:   form.approver_id,
        level_order:   nextOrder,
        is_active:     form.is_active,
      })
    }
    closeModal()
    await load()
  } catch (e: any) {
    const errors = e?.response?.data?.errors
    if (errors) {
      formError.value = Object.values(errors).flat().join(' ')
    } else {
      formError.value = e?.response?.data?.message || 'Failed to save. Please try again.'
    }
  } finally {
    saving.value = false
  }
}

async function deleteLevel(level: any) {
  if (!confirm(`Delete "${level.name}" from the approval chain?`)) return
  try {
    await approvalApi.delete(level.id)
    await load()
  } catch {
    alert('Failed to delete step.')
  }
}

async function toggleActive(level: any) {
  try {
    await approvalApi.update(level.id, { is_active: !level.is_active })
    // Optimistic update
    const idx = levels.value.findIndex(l => l.id === level.id)
    if (idx !== -1) levels.value[idx].is_active = !level.is_active
  } catch {
    alert('Failed to update.')
  }
}

// ── Reordering ───────────────────────────────────────────────────────────────

async function moveUp(level: any, deptId: number) {
  const list = levelsByDept(deptId)
  const idx  = list.findIndex(l => l.id === level.id)
  if (idx <= 0) return
  await swapOrder(list[idx], list[idx - 1])
}

async function moveDown(level: any, deptId: number) {
  const list = levelsByDept(deptId)
  const idx  = list.findIndex(l => l.id === level.id)
  if (idx >= list.length - 1) return
  await swapOrder(list[idx], list[idx + 1])
}

async function swapOrder(a: any, b: any) {
  try {
    await approvalApi.reorder([
      { id: a.id, level_order: b.level_order },
      { id: b.id, level_order: a.level_order },
    ])
    // Optimistic swap
    const aIdx = levels.value.findIndex(l => l.id === a.id)
    const bIdx = levels.value.findIndex(l => l.id === b.id)
    if (aIdx !== -1 && bIdx !== -1) {
      const tmpOrder = levels.value[aIdx].level_order
      levels.value[aIdx].level_order = levels.value[bIdx].level_order
      levels.value[bIdx].level_order = tmpOrder
    }
  } catch {
    await load()
  }
}

// ── Data loading ──────────────────────────────────────────────────────────────

async function load() {
  loading.value = true
  try {
    const [lvlRes, deptRes, userRes] = await Promise.all([
      approvalApi.list(),
      departmentApi.list(),
      userApi.list(),
    ])
    levels.value         = lvlRes.data
    allDepartments.value = deptRes.data

    // userApi.list() returns paginated { data: [...] }
    const userData = userRes.data
    allUsers.value = Array.isArray(userData) ? userData : (userData.data ?? [])
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
