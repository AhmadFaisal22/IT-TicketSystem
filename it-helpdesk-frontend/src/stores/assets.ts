import { defineStore } from 'pinia'
import { ref } from 'vue'
import { assetApi } from '@/api'

export const ASSET_CATEGORIES = [
  'laptop', 'desktop', 'monitor', 'printer', 'network',
  'phone', 'peripheral', 'software_license', 'other',
] as const

export const ASSET_STATUSES = [
  'in_stock', 'assigned', 'in_repair', 'retired', 'lost',
] as const

export type AssetStatus = typeof ASSET_STATUSES[number]

export interface AssetHistory {
  id: number
  action: string
  field: string | null
  old_value: string | null
  new_value: string | null
  created_at: string
  user: { id: number; name: string }
}

export interface AssetAttachment {
  id: number
  original_name: string
  mime_type: string
  size: number
  url: string
}

export interface Asset {
  id: number
  asset_tag: string
  last_name: string | null
  first_name: string | null
  name: string | null
  category: string
  manufacturer: string | null
  model: string | null
  serial_number: string | null
  status: AssetStatus
  assigned_to: number | null
  department_id: number | null
  location: string | null
  purchase_date: string | null
  purchase_cost: string | null
  warranty_expiry: string | null
  notes: string | null
  created_at: string
  assignee?: { id: number; name: string; avatar?: string | null } | null
  department?: { id: number; name: string; name_zh: string } | null
  histories?: AssetHistory[]
  attachments?: AssetAttachment[]
  tickets?: { id: number; ticket_number: string; title: string; status: string }[]
}

export const useAssetStore = defineStore('assets', () => {
  const assets = ref<Asset[]>([])
  const currentAsset = ref<Asset | null>(null)
  const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
  const loading = ref(false)

  async function fetchAssets(params?: object) {
    loading.value = true
    try {
      const { data } = await assetApi.list(params)
      assets.value = data.data
      pagination.value = { current_page: data.current_page, last_page: data.last_page, total: data.total }
    } finally {
      loading.value = false
    }
  }

  async function fetchAsset(id: number) {
    const { data } = await assetApi.get(id)
    currentAsset.value = data
    return data as Asset
  }

  async function createAsset(payload: object) {
    const { data } = await assetApi.create(payload)
    return data as Asset
  }

  async function updateAsset(id: number, payload: object) {
    const { data } = await assetApi.update(id, payload)
    if (currentAsset.value?.id === id) currentAsset.value = data
    return data as Asset
  }

  async function assignAsset(id: number, assignedTo: number | null, departmentId?: number | null) {
    const { data } = await assetApi.assign(id, assignedTo, departmentId)
    if (currentAsset.value?.id === id) currentAsset.value = { ...currentAsset.value, ...data }
    return data as Asset
  }

  async function changeStatus(id: number, status: string) {
    const { data } = await assetApi.updateStatus(id, status)
    if (currentAsset.value?.id === id) currentAsset.value = { ...currentAsset.value, ...data }
    return data as Asset
  }

  return {
    assets, currentAsset, pagination, loading,
    fetchAssets, fetchAsset, createAsset, updateAsset, assignAsset, changeStatus,
  }
})
