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

  next()
})

export default router
