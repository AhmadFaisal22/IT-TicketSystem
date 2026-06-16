import { defineStore } from 'pinia'
import { ref } from 'vue'
import { ticketApi, commentApi } from '@/api'

export interface Attachment {
  id: number
  original_name: string
  mime_type: string
  size: number
  url: string
}

export interface TicketApproval {
  id: number
  ticket_id: number
  approval_level_id: number
  level_order: number
  approver_id: number
  status: 'pending' | 'approved' | 'rejected' | 'cancelled'
  notes: string | null
  responded_by: number | null
  responded_at: string | null
  created_at: string
  approver?: { id: number; name: string; avatar: string | null }
  responder?: { id: number; name: string } | null
}

export interface Ticket {
  id: number
  ticket_number: string
  title: string
  description: string
  status: 'open' | 'in_progress' | 'pending' | 'resolved' | 'closed' | 'pending_approval' | 'rejected'
  priority: 'low' | 'medium' | 'high' | 'critical'
  category: string | null
  subcategory: string | null
  department_id: number
  asset_id: number | null
  created_by: number
  assigned_to: number | null
  sla_response_due_at: string | null
  sla_resolution_due_at: string | null
  first_response_at: string | null
  resolved_at: string | null
  sla_response_breached: boolean
  sla_resolution_breached: boolean
  created_at: string
  creator?: { id: number; name: string; avatar: string | null }
  assignee?: { id: number; name: string; avatar: string | null } | null
  department?: { id: number; name: string; name_zh: string }
  asset?: { id: number; asset_tag: string; name: string | null } | null
  comments?: Comment[]
  histories?: TicketHistory[]
  attachments?: Attachment[]
  approvals?: TicketApproval[]
}

export interface Comment {
  id: number
  ticket_id: number
  user_id: number
  body: string
  is_internal: boolean
  created_at: string
  user: { id: number; name: string; avatar: string | null }
}

export interface TicketHistory {
  id: number
  action: string
  field: string | null
  old_value: string | null
  new_value: string | null
  created_at: string
  user: { id: number; name: string }
}

export const useTicketStore = defineStore('tickets', () => {
  const tickets = ref<Ticket[]>([])
  const currentTicket = ref<Ticket | null>(null)
  const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
  const loading = ref(false)

  async function fetchTickets(params?: object) {
    loading.value = true
    try {
      const { data } = await ticketApi.list(params)
      tickets.value = data.data
      pagination.value = { current_page: data.current_page, last_page: data.last_page, total: data.total }
    } finally {
      loading.value = false
    }
  }

  async function fetchTicket(id: number) {
    const { data } = await ticketApi.get(id)
    currentTicket.value = data
    return data
  }

  async function createTicket(payload: object | FormData) {
    const { data } = await ticketApi.create(payload)
    return data
  }

  async function updateStatus(id: number, status: string) {
    await ticketApi.updateStatus(id, status)
    if (currentTicket.value?.id === id) {
      currentTicket.value.status = status as Ticket['status']
      // Re-fetch so the History timeline (and SLA fields) reflect the change
      // immediately, instead of only after a manual page reload.
      await fetchTicket(id)
    }
  }

  async function assignTicket(id: number, userId: number | null) {
    const { data } = await ticketApi.assign(id, userId)
    if (currentTicket.value?.id === id) {
      currentTicket.value.assignee = data.assignee
      currentTicket.value.assigned_to = userId
    }
  }

  async function addComment(ticketId: number, body: string, isInternal = false) {
    const { data } = await commentApi.create(ticketId, { body, is_internal: isInternal })
    if (currentTicket.value?.id === ticketId) {
      currentTicket.value.comments?.push(data)
    }
    return data
  }

  return {
    tickets, currentTicket, pagination, loading,
    fetchTickets, fetchTicket, createTicket, updateStatus, assignTicket, addComment
  }
})
