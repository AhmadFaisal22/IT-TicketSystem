import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi } from '@/api'

export interface User {
  id: number
  name: string
  email: string
  avatar: string | null
  role: 'admin' | 'it_staff' | 'user'
  department_id: number | null
  department?: { id: number; name: string; name_zh: string }
  locale: string
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('token'))

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isItStaff = computed(() => user.value?.role === 'admin' || user.value?.role === 'it_staff')

  async function fetchUser() {
    if (!token.value) return
    try {
      const { data } = await authApi.me()
      user.value = data
    } catch {
      logout()
    }
  }

  function setToken(t: string) {
    token.value = t
    localStorage.setItem('token', t)
  }

  function logout() {
    if (token.value) authApi.logout().catch(() => {})
    token.value = null
    user.value = null
    localStorage.removeItem('token')
  }

  async function changeLocale(locale: string) {
    localStorage.setItem('locale', locale)
    if (user.value) {
      await authApi.updateLocale(locale)
      user.value.locale = locale
    }
  }

  return { user, token, isAuthenticated, isAdmin, isItStaff, fetchUser, setToken, logout, changeLocale }
})
