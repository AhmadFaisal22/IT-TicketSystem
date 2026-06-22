import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { guest: true }
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/auth/RegisterView.vue'),
      meta: { guest: true }
    },
    {
      path: '/verify-email',
      name: 'verify-email',
      component: () => import('@/views/auth/VerifyEmailView.vue'),
      meta: { guest: true }
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('@/views/auth/ForgotPasswordView.vue'),
      meta: { guest: true }
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('@/views/auth/ResetPasswordView.vue'),
      meta: { guest: true }
    },
    {
      path: '/auth/callback',
      name: 'auth-callback',
      component: () => import('@/views/auth/CallbackView.vue'),
      meta: { guest: true }
    },
    {
      path: '/',
      component: () => import('@/components/layout/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/views/dashboard/DashboardView.vue')
        },
        {
          path: 'tickets',
          name: 'tickets',
          component: () => import('@/views/tickets/TicketsView.vue')
        },
        {
          path: 'my-tasks',
          name: 'my-tasks',
          component: () => import('@/views/tickets/MyTasksView.vue'),
          meta: { requiresItStaff: true }
        },
        {
          path: 'tickets/create',
          name: 'ticket-create',
          component: () => import('@/views/tickets/CreateTicketView.vue')
        },
        {
          path: 'tickets/:id',
          name: 'ticket-detail',
          component: () => import('@/views/tickets/TicketDetailView.vue')
        },
        {
          path: 'tickets/:id/edit',
          name: 'ticket-edit',
          component: () => import('@/views/tickets/EditTicketView.vue')
        },
        {
          path: 'assets',
          name: 'assets',
          component: () => import('@/views/assets/AssetsView.vue'),
          meta: { requiresItStaff: true }
        },
        {
          path: 'assets/create',
          name: 'asset-create',
          component: () => import('@/views/assets/CreateAssetView.vue'),
          meta: { requiresItStaff: true }
        },
        {
          path: 'assets/:id',
          name: 'asset-detail',
          component: () => import('@/views/assets/AssetDetailView.vue'),
          meta: { requiresItStaff: true }
        },
        {
          path: 'assets/:id/edit',
          name: 'asset-edit',
          component: () => import('@/views/assets/EditAssetView.vue'),
          meta: { requiresItStaff: true }
        },
        {
          path: 'admin',
          meta: { requiresAdmin: true },
          children: [
            {
              path: 'users',
              name: 'admin-users',
              component: () => import('@/views/admin/UsersView.vue')
            },
            {
              path: 'departments',
              name: 'admin-departments',
              component: () => import('@/views/admin/DepartmentsView.vue')
            },
            {
              path: 'sla',
              name: 'admin-sla',
              component: () => import('@/views/admin/SlaView.vue')
            },
            {
              path: 'approval-levels',
              name: 'admin-approval',
              component: () => import('@/views/admin/ApprovalLevelsView.vue')
            },
            {
              path: 'inventory/categories',
              name: 'admin-inventory-categories',
              component: () => import('@/views/admin/OptionCrudView.vue'),
              props: { kind: 'categories' }
            },
            {
              path: 'inventory/locations',
              name: 'admin-inventory-locations',
              component: () => import('@/views/admin/OptionCrudView.vue'),
              props: { kind: 'locations' }
            },
            {
              path: 'inventory/manufacturers',
              name: 'admin-inventory-manufacturers',
              component: () => import('@/views/admin/ManufacturersView.vue')
            },
            {
              // Old single-page route → first sub-page
              path: 'asset-options',
              redirect: { name: 'admin-inventory-categories' }
            }
          ]
        }
      ]
    }
  ]
})

router.beforeEach(async (to, _from, next) => {
  const auth = useAuthStore()

  // Token in localStorage but user not yet loaded (e.g. page reload) — fetch before guarding
  if (auth.token && !auth.user) {
    await auth.fetchUser()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return next({ name: 'login' })
  }
  if (to.meta.guest && auth.isAuthenticated) {
    return next({ name: 'dashboard' })
  }
  if (to.meta.requiresAdmin && !auth.isAdmin) {
    return next({ name: 'dashboard' })
  }
  if (to.meta.requiresItStaff && !auth.isItStaff) {
    return next({ name: 'dashboard' })
  }

  next()
})

// A lazy route component failed to load (stale chunk after redeploy, or a
// blocked fetch e.g. untrusted TLS cert). Reload once to recover instead of
// leaving the tab dead. The sessionStorage flag guards against a reload loop.
router.onError((err) => {
  const msg = (err as Error)?.message ?? ''
  if (/dynamically imported module|Importing a module script failed/i.test(msg)) {
    if (!sessionStorage.getItem('chunkReloaded')) {
      sessionStorage.setItem('chunkReloaded', '1')
      window.location.reload()
    }
  }
})

// Clear the reload guard after any successful navigation.
router.afterEach(() => sessionStorage.removeItem('chunkReloaded'))

export default router
