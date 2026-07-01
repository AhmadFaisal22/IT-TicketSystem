<template>
  <div>
    <!-- Header -->
    <div class="bg-white rounded-card shadow-soft border border-gray-100 overflow-hidden">
      <div class="px-6 py-4 border-b flex flex-wrap items-center gap-3 justify-between">
        <h2 class="font-semibold text-gray-800">{{ t('admin.users.title') }}</h2>
        <div class="flex flex-wrap gap-3">
          <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/>
            </svg>
            <input v-model="filters.search" @input="onSearchInput" type="text"
              :placeholder="t('admin.users.searchPlaceholder')"
              class="w-full sm:w-56 pl-9 pr-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" />
          </div>
          <select v-model="filters.role" @change="loadUsers"
            class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            <option value="">{{ t('common.all') }}</option>
            <option value="admin">{{ t('admin.users.admin') }}</option>
            <option value="it_staff">{{ t('admin.users.it_staff') }}</option>
            <option value="user">{{ t('admin.users.user') }}</option>
          </select>
          <button @click="openCreate"
            class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-1.5 rounded-lg text-sm font-medium transition">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ t('admin.users.add') }}
          </button>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="p-8 text-center text-gray-400">
        <div class="w-7 h-7 border-4 border-red-600 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
        {{ t('common.loading') }}
      </div>

      <!-- Desktop table -->
      <div v-else class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('admin.users.name') }}</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden sm:table-cell">{{ t('admin.users.email') }}</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ t('admin.users.role') }}</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">{{ t('admin.users.department') }}</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden lg:table-cell">{{ t('admin.users.status') }}</th>
              <th class="px-4 py-3 w-24"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50 transition">
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <img v-if="user.avatar" :src="user.avatar" class="w-8 h-8 rounded-full object-cover" />
                  <div v-else class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center text-sm font-bold text-red-700 flex-shrink-0">
                    {{ user.name[0]?.toUpperCase() }}
                  </div>
                  <div>
                    <p class="text-sm font-medium text-gray-800">{{ user.name }}</p>
                    <p class="text-xs text-gray-400 sm:hidden">{{ user.email }}</p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600 hidden sm:table-cell">{{ user.email }}</td>
              <td class="px-4 py-3">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="roleClass(user.role)">
                  {{ t(`admin.users.${user.role}`) }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600 hidden md:table-cell">
                {{ locale === 'zh' ? user.department?.name_zh : user.department?.name || '—' }}
              </td>
              <td class="px-4 py-3 hidden lg:table-cell">
                <button @click="toggleActive(user)"
                  class="px-2 py-0.5 rounded-full text-xs font-medium transition"
                  :class="user.active ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'">
                  {{ user.active ? t('admin.users.active') : t('admin.users.inactive') }}
                </button>
              </td>
              <td class="px-3 py-3">
                <div class="flex items-center gap-1 justify-end">
                  <button @click="openEdit(user)"
                    class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
                    :title="t('admin.users.editUser')">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                  </button>
                  <button @click="handleDelete(user)"
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
            <tr v-if="!users.length">
              <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">
                {{ t('admin.users.noResults') }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal overlay -->
    <Teleport to="body">
      <div v-if="modal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="closeModal"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">

          <!-- Modal header -->
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="font-semibold text-gray-800">
              {{ modal.editing ? t('admin.users.editUser') : t('admin.users.add') }}
            </h3>
            <button @click="closeModal" class="p-1 rounded-lg text-gray-400 hover:bg-gray-100 transition">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Modal body -->
          <form @submit.prevent="submitModal" class="px-6 py-5 space-y-4">
            <!-- Name -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin.users.name') }} *</label>
              <input v-model="form.name" type="text" required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
                :placeholder="t('admin.users.name')" />
            </div>

            <!-- Email -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin.users.email') }} *</label>
              <input v-model="form.email" type="email" required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
                placeholder="user@company.com" />
            </div>

            <!-- Password -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                {{ t('admin.users.password') }} <span v-if="!modal.editing">*</span>
              </label>
              <input v-model="form.password" type="password"
                :required="!modal.editing"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
                :placeholder="modal.editing ? t('admin.users.passwordHint') : '••••••••'" />
              <p v-if="modal.editing" class="text-xs text-gray-400 mt-1">{{ t('admin.users.passwordHint') }}</p>
            </div>

            <!-- Role -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin.users.role') }} *</label>
              <select v-model="form.role" required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                <option value="user">{{ t('admin.users.user') }}</option>
                <option value="it_staff">{{ t('admin.users.it_staff') }}</option>
                <option value="admin">{{ t('admin.users.admin') }}</option>
              </select>
            </div>

            <!-- Department -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('admin.users.department') }}</label>
              <select v-model="form.department_id"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                <option :value="null">—</option>
                <option v-for="d in departments" :key="d.id" :value="d.id">
                  {{ locale === 'zh' ? d.name_zh : d.name }}
                </option>
              </select>
            </div>

            <!-- Error -->
            <p v-if="modal.error" class="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{{ modal.error }}</p>

            <!-- Actions -->
            <div class="flex gap-3 pt-2">
              <button type="button" @click="closeModal"
                class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                {{ t('common.cancel') }}
              </button>
              <button type="submit" :disabled="modal.saving"
                class="flex-1 bg-red-600 hover:bg-red-700 disabled:opacity-60 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                {{ modal.saving ? t('common.loading') : t('common.save') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { userApi, departmentApi } from '@/api'

const { t, locale } = useI18n()
const users = ref<any[]>([])
const departments = ref<any[]>([])
const loading = ref(false)
const filters = reactive({ role: '', search: '' })

let searchTimer: ReturnType<typeof setTimeout> | undefined
function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(loadUsers, 300)
}

const modal = reactive({
  open: false,
  editing: false,
  editId: null as number | null,
  saving: false,
  error: ''
})

const form = reactive({
  name: '',
  email: '',
  password: '',
  role: 'user',
  department_id: null as number | null
})

async function loadUsers() {
  loading.value = true
  try {
    const { data } = await userApi.list(filters)
    users.value = data.data
  } finally {
    loading.value = false
  }
}

function openCreate() {
  modal.editing = false
  modal.editId = null
  modal.error = ''
  form.name = ''
  form.email = ''
  form.password = ''
  form.role = 'user'
  form.department_id = null
  modal.open = true
}

function openEdit(user: any) {
  modal.editing = true
  modal.editId = user.id
  modal.error = ''
  form.name = user.name
  form.email = user.email
  form.password = ''
  form.role = user.role
  form.department_id = user.department_id ?? null
  modal.open = true
}

function closeModal() {
  modal.open = false
}

async function submitModal() {
  modal.saving = true
  modal.error = ''
  try {
    const payload: Record<string, any> = {
      name: form.name,
      email: form.email,
      role: form.role,
      department_id: form.department_id || null
    }
    if (!modal.editing || form.password) {
      payload.password = form.password
    }

    if (modal.editing && modal.editId) {
      await userApi.update(modal.editId, payload)
    } else {
      await userApi.create(payload)
    }
    modal.open = false
    await loadUsers()
  } catch (e: any) {
    const errors = e?.response?.data?.errors
    if (errors) {
      modal.error = Object.values(errors).flat().join(' ')
    } else {
      modal.error = e?.response?.data?.message || t('common.error')
    }
  } finally {
    modal.saving = false
  }
}

async function toggleActive(user: any) {
  await userApi.toggleActive(user.id)
  user.active = !user.active
}

async function handleDelete(user: any) {
  const msg = t('admin.users.deleteConfirm', { name: user.name })
  if (!confirm(msg)) return
  try {
    await userApi.delete(user.id)
    await loadUsers()
  } catch (e: any) {
    alert(e?.response?.data?.message || t('common.error'))
  }
}

function roleClass(role: string) {
  const map: Record<string, string> = {
    admin: 'bg-red-100 text-red-700',
    it_staff: 'bg-blue-100 text-blue-700',
    user: 'bg-gray-100 text-gray-600'
  }
  return map[role] || 'bg-gray-100 text-gray-600'
}

onMounted(async () => {
  await loadUsers()
  const { data } = await departmentApi.list()
  departments.value = data
})
</script>
