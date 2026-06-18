<template>
  <div class="min-h-screen bg-gray-100 flex">

    <!-- Mobile sidebar overlay -->
    <div v-if="sidebarOpen" @click="sidebarOpen = false"
      class="fixed inset-0 bg-black/50 z-20 lg:hidden"></div>

    <!-- Sidebar -->
    <aside
      class="w-64 bg-white text-gray-700 dark:bg-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-800 flex flex-col fixed h-full z-30 transition-transform duration-300 ease-in-out"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">

      <div class="h-16 flex items-center px-4 sm:px-6 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between w-full">
          <div class="flex items-center gap-3">
            <img src="/SEG Logo.png" alt="SEG Solar" class="h-8 w-auto object-contain" />
            <span class="font-bold text-sm leading-tight text-gray-900 dark:text-white">IT Ticketing<br/>System</span>
          </div>
          <button @click="sidebarOpen = false"
            class="lg:hidden p-1.5 rounded text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700 transition">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>

      <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        <router-link v-for="item in navItems" :key="item.name" :to="item.to"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="navLinkClass(item)">
          <img v-if="item.iconImg" :src="item.iconImg" class="w-5 h-5 object-contain" :class="navImageClass(item)" />
          <component v-else :is="item.icon" class="w-5 h-5" />
          {{ t(item.label) }}
        </router-link>

        <template v-if="auth.isItStaff">
          <div class="pt-3 pb-1 px-3 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">
            {{ t('admin.users.title').split(' ')[0] }}
          </div>
          <router-link v-for="item in adminItems" :key="item.name" :to="item.to"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
            :class="navLinkClass(item)">
            <img v-if="item.iconImg" :src="item.iconImg" class="w-5 h-5 object-contain" :class="navImageClass(item)" />
            <component v-else :is="item.icon" class="w-5 h-5" />
            {{ t(item.label) }}
          </router-link>
        </template>
      </nav>

      <!-- User section -->
      <div class="px-3 py-4 border-t border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-3 px-3 mb-3">
          <img v-if="auth.user?.avatar" :src="auth.user.avatar" class="w-8 h-8 rounded-full" />
          <div v-else class="w-8 h-8 rounded-full bg-red-600 flex items-center justify-center text-xs font-bold flex-shrink-0">
            {{ auth.user?.name?.[0]?.toUpperCase() }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ auth.user?.name }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ roleLabel }}</p>
          </div>
        </div>
        <div class="flex items-center justify-between px-3">
          <div class="flex items-center gap-3 text-xs">
            <ThemeToggle />
            <div class="flex gap-2">
              <button @click="setLocale('en')"
                :class="currentLocale === 'en' ? 'text-red-600 dark:text-red-400 font-bold' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'">EN</button>
              <span class="text-gray-300 dark:text-gray-600">|</span>
              <button @click="setLocale('zh')"
                :class="currentLocale === 'zh' ? 'text-red-600 dark:text-red-400 font-bold' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'">中文</button>
            </div>
          </div>
          <button @click="handleLogout" class="text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white text-xs">{{ t('nav.logout') }}</button>
        </div>
      </div>
    </aside>

    <!-- Main content -->
    <div class="flex-1 lg:ml-64 flex flex-col min-h-screen">

      <!-- Top bar -->
      <header class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-4 sm:px-6 h-16 flex items-center justify-between sticky top-0 z-10">
        <div class="flex items-center gap-3">
          <!-- Hamburger (mobile only) -->
          <button @click="sidebarOpen = true"
            class="lg:hidden -ml-1 p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
          </button>
          <h1 class="text-base sm:text-xl font-semibold text-gray-800 dark:text-gray-100 truncate">{{ pageTitle }}</h1>
        </div>
        <div class="flex items-center gap-2 sm:gap-4 flex-shrink-0">
          <NotificationBell />
          <router-link to="/tickets/create"
            class="bg-red-600 text-white px-3 sm:px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition flex items-center">
            <span class="hidden sm:inline">+ {{ t('ticket.actions.create') }}</span>
            <span class="sm:hidden text-xl font-bold leading-none">+</span>
          </router-link>
        </div>
      </header>

      <!-- Page content -->
      <main class="flex-1 p-4 sm:p-6">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ComputerDesktopIcon, AdjustmentsHorizontalIcon, ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'
import NotificationBell from '@/components/ui/NotificationBell.vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const notifStore = useNotificationStore()

const sidebarOpen = ref(false)
const currentLocale = computed(() => locale.value)

watch(() => route.name, () => {
  sidebarOpen.value = false
})

type NavItem = { name: string; to: string; label: string; icon?: any; iconImg?: string; prefix?: string }

function isNavActive(item: NavItem) {
  return route.name === item.name || String(route.name).startsWith(item.prefix || item.name)
}

function navLinkClass(item: NavItem) {
  return isNavActive(item)
    ? 'bg-red-600 text-white'
    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white'
}

function navImageClass(item: NavItem) {
  return isNavActive(item)
    ? 'brightness-0 invert'
    : 'brightness-0 opacity-75 dark:brightness-100 dark:invert-0 dark:opacity-100'
}

const navItems = computed((): NavItem[] => {
  // Dashboard shows IT-only KPIs/charts, so regular users don't see the link
  // (the route itself redirects them to Tickets anyway).
  const items: NavItem[] = [
    { name: 'tickets', to: '/tickets', label: 'nav.tickets', iconImg: '/icons8-ticket-50.png', prefix: 'ticket' },
  ]
  if (auth.isItStaff) {
    items.unshift(
      { name: 'dashboard', to: '/', label: 'nav.dashboard', iconImg: '/Dash.png' },
      { name: 'my-tasks', to: '/my-tasks', label: 'nav.myTasks', icon: ClipboardDocumentCheckIcon, prefix: 'my-task' }
    )
    items.push({ name: 'assets', to: '/assets', label: 'nav.assets', icon: ComputerDesktopIcon, prefix: 'asset' })
  }
  return items
})

const adminItems = computed((): NavItem[] => {
  const items: NavItem[] = [
    { name: 'admin-departments', to: '/admin/departments', label: 'nav.departments', iconImg: '/Dept.png' },
    { name: 'admin-sla', to: '/admin/sla', label: 'nav.sla', iconImg: '/SLA.png' },
  ]
  if (auth.isAdmin) {
    items.unshift({ name: 'admin-users', to: '/admin/users', label: 'nav.users', iconImg: '/Users.png' })
    items.push({ name: 'admin-approval', to: '/admin/approval-levels', label: 'nav.approval', iconImg: '/Approved.png' })
    items.push({ name: 'admin-asset-options', to: '/admin/asset-options', label: 'nav.assetOptions', icon: AdjustmentsHorizontalIcon })
  }
  return items
})

const roleLabel = computed(() => {
  const map: Record<string, string> = { admin: 'Administrator', it_staff: 'IT Staff', user: 'User' }
  return map[auth.user?.role || 'user']
})

const pageTitle = computed(() => {
  const map: Record<string, string> = {
    dashboard: t('nav.dashboard'),
    tickets: t('nav.tickets'),
    'my-tasks': t('nav.myTasks'),
    'ticket-create': t('ticket.actions.create'),
    'ticket-edit': t('ticket.actions.edit'),
    'ticket-detail': `${t('ticket.ticketNumber')}`,
    assets: t('nav.assets'),
    'asset-create': t('asset.actions.create'),
    'asset-edit': t('asset.actions.edit'),
    'asset-detail': t('asset.title'),
    'admin-users': t('admin.users.title'),
    'admin-departments': t('admin.departments.title'),
    'admin-sla': t('admin.sla.title'),
    'admin-approval': t('admin.approval.title'),
    'admin-asset-options': t('admin.assetOptions.title'),
  }
  return map[String(route.name)] || ''
})

async function setLocale(l: string) {
  locale.value = l
  await auth.changeLocale(l)
}

function handleLogout() {
  auth.logout()
  router.push({ name: 'login' })
}

onMounted(() => {
  if (auth.isAuthenticated) {
    notifStore.fetchUnreadCount()
  }
})
</script>
