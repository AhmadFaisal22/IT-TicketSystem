<template>
  <div class="relative" ref="dropdownRef">
    <button @click="toggle" class="relative p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition">
      <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
      </svg>
      <span v-if="notifStore.unreadCount > 0"
        class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center">
        {{ notifStore.unreadCount > 9 ? '9+' : notifStore.unreadCount }}
      </span>
    </button>

    <div v-if="open"
      class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden z-50">
      <div class="flex items-center justify-between px-4 py-3 border-b">
        <span class="font-semibold text-gray-800">Notifications</span>
        <button v-if="notifStore.unreadCount > 0" @click="notifStore.markAllRead()"
          class="text-xs text-red-600 hover:text-red-800">Mark all read</button>
      </div>

      <div class="max-h-96 overflow-y-auto">
        <div v-if="!notifStore.notifications.length" class="px-4 py-8 text-center text-gray-400 text-sm">
          No notifications
        </div>
        <button v-for="n in notifStore.notifications" :key="n.id"
          @click="handleClick(n)"
          class="w-full text-left px-4 py-3 hover:bg-gray-50 border-b border-gray-100 transition"
          :class="!n.read_at ? 'bg-red-50' : ''">
          <div class="flex items-start gap-3">
            <div class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0" :class="!n.read_at ? 'bg-red-500' : 'bg-gray-300'"></div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-800">{{ n.data?.ticket_number }}</p>
              <p class="text-xs text-gray-500 truncate">{{ getNotifMessage(n) }}</p>
              <p class="text-xs text-gray-400 mt-1">{{ formatTime(n.created_at) }}</p>
            </div>
          </div>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationStore, type AppNotification } from '@/stores/notifications'
import { onClickOutside } from '@vueuse/core'

const notifStore = useNotificationStore()
const router = useRouter()
const open = ref(false)
const dropdownRef = ref()

onClickOutside(dropdownRef, () => { open.value = false })

async function toggle() {
  open.value = !open.value
  if (open.value) await notifStore.fetchAll()
}

async function handleClick(n: AppNotification) {
  if (!n.read_at) await notifStore.markRead(n.id)
  if (n.data?.ticket_id) {
    router.push(`/tickets/${n.data.ticket_id}`)
  }
  open.value = false
}

function getNotifMessage(n: AppNotification) {
  const type = n.data?.type
  if (type === 'ticket_created') return `New ticket: ${n.data.title}`
  if (type === 'status_changed') return `Status: ${n.data.old_status} → ${n.data.new_status}`
  if (type === 'ticket_assigned') return `Assigned: ${n.data.title}`
  if (type === 'new_comment') return `New reply from ${n.data.comment_by}`
  return n.data?.title || ''
}

function formatTime(dt: string) {
  return new Date(dt).toLocaleString()
}
</script>
