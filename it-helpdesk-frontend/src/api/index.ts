import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: { 'Accept': 'application/json' }
})

api.interceptors.request.use(config => {
  const token = localStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  // Set JSON content-type for non-FormData requests
  if (!(config.data instanceof FormData)) {
    config.headers['Content-Type'] = 'application/json'
  }
  return config
})

api.interceptors.response.use(
  res => res,
  err => {
    // Only redirect on 401 if a token exists — meaning a valid session expired.
    // If there's no token, this is a login attempt failure; let the caller handle it.
    if (err.response?.status === 401 && localStorage.getItem('token')) {
      localStorage.removeItem('token')
      window.location.href = '/login'
    }
    return Promise.reject(err)
  }
)

export default api

export const authApi = {
  login: (email: string, password: string) => api.post('/auth/login', { email, password }),
  redirectGoogle: () => api.get('/auth/redirect/google'),
  redirectMicrosoft: () => api.get('/auth/redirect/microsoft'),
  me: () => api.get('/auth/me'),
  logout: () => api.post('/auth/logout'),
  updateLocale: (locale: string) => api.patch('/auth/locale', { locale }),
  forgotPassword: (email: string) => api.post('/auth/forgot-password', { email }),
  resetPassword: (token: string, email: string, password: string, password_confirmation: string) =>
    api.post('/auth/reset-password', { token, email, password, password_confirmation }),
}

export const ticketApi = {
  list: (params?: object) => api.get('/tickets', { params }),
  get: (id: number) => api.get(`/tickets/${id}`),
  create: (data: object | FormData) => api.post('/tickets', data),
  update: (id: number, data: object) => api.put(`/tickets/${id}`, data),
  updateStatus: (id: number, status: string) => api.patch(`/tickets/${id}/status`, { status }),
  assign: (id: number, assigned_to: number | null) => api.patch(`/tickets/${id}/assign`, { assigned_to }),
  delete: (id: number) => api.delete(`/tickets/${id}`)
}

export const commentApi = {
  list: (ticketId: number) => api.get(`/tickets/${ticketId}/comments`),
  create: (ticketId: number, data: object) => api.post(`/tickets/${ticketId}/comments`, data),
  delete: (ticketId: number, commentId: number) => api.delete(`/tickets/${ticketId}/comments/${commentId}`)
}

export const departmentApi = {
  list: () => api.get('/departments'),
  create: (data: object) => api.post('/departments', data),
  update: (id: number, data: object) => api.put(`/departments/${id}`, data),
  delete: (id: number) => api.delete(`/departments/${id}`)
}

export const userApi = {
  list: (params?: object) => api.get('/users', { params }),
  itStaff: () => api.get('/users/it-staff'),
  assignable: (params?: object) => api.get('/users/assignable', { params }),
  create: (data: object) => api.post('/users', data),
  update: (id: number, data: object) => api.put(`/users/${id}`, data),
  delete: (id: number) => api.delete(`/users/${id}`),
  updateRole: (id: number, role: string) => api.patch(`/users/${id}/role`, { role }),
  updateDepartment: (id: number, department_id: number | null) =>
    api.patch(`/users/${id}/department`, { department_id }),
  toggleActive: (id: number) => api.patch(`/users/${id}/toggle-active`)
}

export const dashboardApi = {
  stats: (range?: number) => api.get('/dashboard/stats', { params: { range } }),
  sla: (range?: number) => api.get('/dashboard/sla', { params: { range } })
}

export const notificationApi = {
  list: () => api.get('/notifications'),
  unreadCount: () => api.get('/notifications/unread-count'),
  markRead: (id: string) => api.patch(`/notifications/${id}/read`),
  markAllRead: () => api.patch('/notifications/mark-all-read')
}

export const slaApi = {
  list: () => api.get('/sla-policies'),
  save: (data: object) => api.post('/sla-policies', data),
  delete: (id: number) => api.delete(`/sla-policies/${id}`)
}

export const approvalApi = {
  list: () => api.get('/approval-levels'),
  create: (data: object) => api.post('/approval-levels', data),
  update: (id: number, data: object) => api.put(`/approval-levels/${id}`, data),
  delete: (id: number) => api.delete(`/approval-levels/${id}`),
  reorder: (levels: { id: number; level_order: number }[]) =>
    api.post('/approval-levels/reorder', { levels }),
  approve: (ticketId: number, notes?: string) =>
    api.post(`/tickets/${ticketId}/approve`, { notes }),
  reject: (ticketId: number, notes: string) =>
    api.post(`/tickets/${ticketId}/reject`, { notes }),
}

export const assetCategoryApi = {
  list: () => api.get('/asset-categories'),
  create: (data: object) => api.post('/asset-categories', data),
  update: (id: number, data: object) => api.put(`/asset-categories/${id}`, data),
  delete: (id: number) => api.delete(`/asset-categories/${id}`),
}

export const assetLocationApi = {
  list: () => api.get('/asset-locations'),
  create: (data: object) => api.post('/asset-locations', data),
  update: (id: number, data: object) => api.put(`/asset-locations/${id}`, data),
  delete: (id: number) => api.delete(`/asset-locations/${id}`),
}

export const assetApi = {
  list: (params?: object) => api.get('/assets', { params }),
  meta: () => api.get('/assets/meta'),
  get: (id: number) => api.get(`/assets/${id}`),
  create: (data: object) => api.post('/assets', data),
  update: (id: number, data: object) => api.put(`/assets/${id}`, data),
  remove: (id: number) => api.delete(`/assets/${id}`),
  assign: (id: number, assigned_to: number | null, department_id?: number | null) =>
    api.patch(`/assets/${id}/assign`, { assigned_to, department_id }),
  updateStatus: (id: number, status: string) => api.patch(`/assets/${id}/status`, { status }),
  uploadAttachments: (id: number, formData: FormData) => api.post(`/assets/${id}/attachments`, formData),
  deleteAttachment: (id: number, attachmentId: number) =>
    api.delete(`/assets/${id}/attachments/${attachmentId}`),
  export: (params?: object) => api.get('/assets/export', { params, responseType: 'blob' }),
  import: (formData: FormData) => api.post('/assets/import', formData),
}
