import { defineStore } from 'pinia'
import { ref } from 'vue'
import { notificationApi } from '@/api'

export interface AppNotification {
  id: string
  type: string
  read_at: string | null
  created_at: string
  data: {
    ticket_id?: number
    ticket_number?: string
    title?: string
    type?: string
    old_status?: string
    new_status?: string
    comment_by?: string
    breach_type?: string
  }
}

export const useNotificationStore = defineStore('notifications', () => {
  const notifications = ref<AppNotification[]>([])
  const unreadCount = ref(0)

  async function fetchUnreadCount() {
    const { data } = await notificationApi.unreadCount()
    unreadCount.value = data.count
  }

  async function fetchAll() {
    const { data } = await notificationApi.list()
    notifications.value = data.data
  }

  async function markRead(id: string) {
    await notificationApi.markRead(id)
    const n = notifications.value.find(n => n.id === id)
    if (n) n.read_at = new Date().toISOString()
    if (unreadCount.value > 0) unreadCount.value--
  }

  async function markAllRead() {
    await notificationApi.markAllRead()
    notifications.value.forEach(n => { n.read_at = new Date().toISOString() })
    unreadCount.value = 0
  }

  async function clearRead() {
    await notificationApi.clearRead()
    notifications.value = notifications.value.filter(n => !n.read_at)
  }

  return { notifications, unreadCount, fetchUnreadCount, fetchAll, markRead, markAllRead, clearRead }
})
