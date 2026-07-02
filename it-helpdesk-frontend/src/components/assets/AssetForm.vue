<template>
  <div class="bg-white rounded-card shadow-soft border border-gray-100 p-6 max-w-2xl">
    <p v-if="successMsg" class="mb-4 px-3 py-2 rounded-lg bg-green-50 text-green-700 text-sm">{{ successMsg }}</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div :class="isEdit ? 'sm:col-span-2' : ''">
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.assetTag') }} *</label>
        <input v-model="form.asset_tag" placeholder="US02-ADOM001-011" class="input" />
        <p v-if="tagNeedsNumber" class="text-xs text-amber-600 mt-1">{{ t('asset.tagNoNumberHint') }}</p>
      </div>

      <div v-if="!isEdit">
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.quantity') }}</label>
        <input v-model.number="quantity" type="number" min="1" max="50" class="input" />
        <p v-if="bulkPreview" class="text-xs text-gray-500 mt-1">{{ bulkPreview }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.lastName') }}</label>
        <input v-model="form.last_name" :disabled="isBulk" class="input disabled:opacity-50 disabled:cursor-not-allowed" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.firstName') }}</label>
        <input v-model="form.first_name" :disabled="isBulk" class="input disabled:opacity-50 disabled:cursor-not-allowed" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.category') }} *</label>
        <select v-model="form.category" class="input">
          <option v-for="c in categories" :key="c.id" :value="c.name">
            {{ locale === 'zh' && c.name_zh ? c.name_zh : c.name }}
          </option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.serialNumber') }}</label>
        <input v-model="form.serial_number" :disabled="isBulk" class="input disabled:opacity-50 disabled:cursor-not-allowed" />
        <p v-if="isBulk" class="text-xs text-gray-500 mt-1">{{ t('asset.bulkSerialHint') }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.manufacturer') }}</label>
        <select v-model="form.manufacturer" class="input">
          <option value="">—</option>
          <option v-for="m in manufacturerOptions" :key="m" :value="m">{{ m }}</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.model') }}</label>
        <input v-model="form.model" class="input" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.location') }}</label>
        <select v-model="form.location" class="input">
          <option value="">—</option>
          <option v-for="l in locations" :key="l.id" :value="l.name">
            {{ locale === 'zh' && l.name_zh ? l.name_zh : l.name }}
          </option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.department') }}</label>
        <select v-model="form.department_id" class="input">
          <option :value="null">—</option>
          <option v-for="d in departments" :key="d.id" :value="d.id">
            {{ locale === 'zh' ? d.name_zh : d.name }}
          </option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.assignDate') }}</label>
        <input v-model="form.assign_date" type="date" :disabled="isBulk" class="input disabled:opacity-50 disabled:cursor-not-allowed" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.purchaseCost') }}</label>
        <input v-model="form.purchase_cost" type="number" step="0.01" min="0" class="input" />
      </div>

      <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.purchaseLink') }}</label>
        <input v-model="form.purchase_link" type="url" placeholder="https://..." class="input" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.warrantyExpiry') }}</label>
        <input v-model="form.warranty_expiry" type="date" class="input" />
      </div>

      <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.notes') }}</label>
        <textarea v-model="form.notes" rows="3" class="input"></textarea>
      </div>
    </div>

    <p v-if="error" class="text-sm text-red-600 mt-3">{{ error }}</p>

    <div class="flex flex-wrap gap-3 mt-6">
      <button @click="$router.back()" class="px-4 py-2 border rounded-lg text-sm text-gray-700 hover:bg-gray-50">
        {{ t('common.cancel') }}
      </button>
      <button v-if="!isEdit" @click="submit(true)" :disabled="saving || submitBlocked"
        class="px-4 py-2 border border-red-300 text-red-600 rounded-lg text-sm hover:bg-red-50 disabled:opacity-50">
        {{ t('asset.actions.saveAndAdd') }}
      </button>
      <button @click="submit(false)" :disabled="saving || submitBlocked"
        class="px-5 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 disabled:opacity-50">
        {{ saving ? t('common.loading') : t('common.save') }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAssetStore, type Asset } from '@/stores/assets'
import { departmentApi, assetCategoryApi, assetLocationApi, assetManufacturerApi, assetApi } from '@/api'

// `asset` = edit mode; `prefill` = duplicate source for a new asset (common
// fields copied, tag/serial left for the new unit).
const props = defineProps<{ asset?: Asset | null; prefill?: Asset | null }>()
const { t, locale } = useI18n()
const router = useRouter()
const store = useAssetStore()

const categories = ref<any[]>([])
const locations = ref<any[]>([])
const departments = ref<any[]>([])
const manufacturers = ref<any[]>([])
const saving = ref(false)
const error = ref('')
const successMsg = ref('')

const isEdit = computed(() => !!props.asset)
const quantity = ref(1)
const isBulk = computed(() => !isEdit.value && quantity.value > 1)

const tagParts = computed(() => {
  const m = form.asset_tag.match(/^(.*?)(\d+)$/)
  return m ? { prefix: m[1], start: parseInt(m[2], 10), width: m[2].length } : null
})
const tagNeedsNumber = computed(() => isBulk.value && form.asset_tag !== '' && !tagParts.value)
const submitBlocked = computed(() => tagNeedsNumber.value)

const bulkPreview = computed(() => {
  if (!isBulk.value || !tagParts.value) return ''
  const { prefix, start, width } = tagParts.value
  const last = prefix + String(start + quantity.value - 1).padStart(width, '0')
  return t('asset.bulkPreview', { count: quantity.value, first: form.asset_tag, last })
})

async function suggestNextTag() {
  try {
    const { data } = await assetApi.nextTag()
    if (data.suggested) form.asset_tag = data.suggested
  } catch { /* suggestion is best-effort; the field stays manual */ }
}

// Active manufacturers for the dropdown; keep the asset's current value selectable
// even if it's inactive or a legacy free-text value not in the managed list.
const manufacturerOptions = computed(() => {
  const names = manufacturers.value.filter(m => m.status === 'active').map(m => m.name)
  if (form.manufacturer && !names.includes(form.manufacturer)) names.unshift(form.manufacturer)
  return names
})

// Common fields come from the edited asset or the duplicate source;
// per-unit identity (tag, serial, owner) is never copied from a duplicate.
const src = props.asset ?? props.prefill ?? null
const form = reactive({
  asset_tag: props.asset?.asset_tag ?? '',
  last_name: props.asset?.last_name ?? '',
  first_name: props.asset?.first_name ?? '',
  category: src?.category ?? '',
  serial_number: props.asset?.serial_number ?? '',
  manufacturer: src?.manufacturer ?? '',
  model: src?.model ?? '',
  location: src?.location ?? '',
  department_id: src?.department_id ?? null,
  assign_date: props.asset?.assign_date?.slice(0, 10) ?? '',
  purchase_cost: src?.purchase_cost ?? '',
  purchase_link: src?.purchase_link ?? '',
  warranty_expiry: src?.warranty_expiry?.slice(0, 10) ?? '',
  notes: src?.notes ?? '',
})

function payload() {
  return {
    asset_tag: form.asset_tag,
    last_name: form.last_name || null,
    first_name: form.first_name || null,
    category: form.category,
    serial_number: form.serial_number || null,
    manufacturer: form.manufacturer || null,
    model: form.model || null,
    location: form.location || null,
    department_id: form.department_id,
    assign_date: form.assign_date || null,
    purchase_cost: form.purchase_cost === '' ? null : form.purchase_cost,
    purchase_link: form.purchase_link || null,
    warranty_expiry: form.warranty_expiry || null,
    notes: form.notes || null,
    // Optimistic-lock guard; only sent when editing an existing asset.
    version: props.asset?.version,
  }
}

function bulkPayload() {
  return {
    asset_tag: form.asset_tag,
    quantity: quantity.value,
    category: form.category,
    manufacturer: form.manufacturer || null,
    model: form.model || null,
    location: form.location || null,
    department_id: form.department_id,
    purchase_cost: form.purchase_cost === '' ? null : form.purchase_cost,
    purchase_link: form.purchase_link || null,
    warranty_expiry: form.warranty_expiry || null,
    notes: form.notes || null,
  }
}

// After a "save & add another", keep common fields and prepare the next unit.
async function prepareNextEntry() {
  form.serial_number = ''
  form.last_name = ''
  form.first_name = ''
  form.assign_date = ''
  form.asset_tag = ''
  await suggestNextTag()
}

async function submit(stay = false) {
  error.value = ''
  successMsg.value = ''
  saving.value = true
  try {
    if (props.asset) {
      await store.updateAsset(props.asset.id, payload())
      router.push(`/assets/${props.asset.id}`)
    } else if (isBulk.value) {
      const { data } = await assetApi.bulkCreate(bulkPayload())
      if (stay) {
        successMsg.value = t('asset.createdMany', { count: data.created, first: data.first_tag, last: data.last_tag })
        await prepareNextEntry()
      } else {
        router.push('/assets')
      }
    } else {
      const created = await store.createAsset(payload())
      if (stay) {
        successMsg.value = t('asset.createdOne', { tag: created.asset_tag })
        await prepareNextEntry()
      } else {
        router.push(`/assets/${created.id}`)
      }
    }
  } catch (e: any) {
    error.value = e?.response?.status === 409
      ? t('common.conflict')
      : (e?.response?.data?.message || t('common.error'))
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  if (!isEdit.value && !form.asset_tag) suggestNextTag()
  const [deptRes, catRes, locRes, mfrRes] = await Promise.all([
    departmentApi.list(), assetCategoryApi.list(), assetLocationApi.list(), assetManufacturerApi.list(),
  ])
  departments.value = deptRes.data
  categories.value = catRes.data
  locations.value = locRes.data
  manufacturers.value = mfrRes.data
  if (!form.category && categories.value.length) {
    form.category = categories.value[0].name
  }
})
</script>

<style scoped>
.input {
  @apply w-full px-3 py-2 border border-gray-300 rounded-input text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none;
}
</style>
