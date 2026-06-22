<template>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-2xl">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.assetTag') }} *</label>
        <input v-model="form.asset_tag" placeholder="US02-ADOM001-011" class="input" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.lastName') }}</label>
        <input v-model="form.last_name" class="input" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('asset.firstName') }}</label>
        <input v-model="form.first_name" class="input" />
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
        <input v-model="form.serial_number" class="input" />
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
        <input v-model="form.assign_date" type="date" class="input" />
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

    <div class="flex gap-3 mt-6">
      <button @click="$router.back()" class="px-4 py-2 border rounded-lg text-sm text-gray-700 hover:bg-gray-50">
        {{ t('common.cancel') }}
      </button>
      <button @click="submit" :disabled="saving"
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
import { departmentApi, assetCategoryApi, assetLocationApi, assetManufacturerApi } from '@/api'

const props = defineProps<{ asset?: Asset | null }>()
const { t, locale } = useI18n()
const router = useRouter()
const store = useAssetStore()

const categories = ref<any[]>([])
const locations = ref<any[]>([])
const departments = ref<any[]>([])
const manufacturers = ref<any[]>([])
const saving = ref(false)
const error = ref('')

// Active manufacturers for the dropdown; keep the asset's current value selectable
// even if it's inactive or a legacy free-text value not in the managed list.
const manufacturerOptions = computed(() => {
  const names = manufacturers.value.filter(m => m.status === 'active').map(m => m.name)
  if (form.manufacturer && !names.includes(form.manufacturer)) names.unshift(form.manufacturer)
  return names
})

const form = reactive({
  asset_tag: props.asset?.asset_tag ?? '',
  last_name: props.asset?.last_name ?? '',
  first_name: props.asset?.first_name ?? '',
  category: props.asset?.category ?? '',
  serial_number: props.asset?.serial_number ?? '',
  manufacturer: props.asset?.manufacturer ?? '',
  model: props.asset?.model ?? '',
  location: props.asset?.location ?? '',
  department_id: props.asset?.department_id ?? null,
  assign_date: props.asset?.assign_date?.slice(0, 10) ?? '',
  purchase_cost: props.asset?.purchase_cost ?? '',
  purchase_link: props.asset?.purchase_link ?? '',
  warranty_expiry: props.asset?.warranty_expiry?.slice(0, 10) ?? '',
  notes: props.asset?.notes ?? '',
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

async function submit() {
  error.value = ''
  saving.value = true
  try {
    if (props.asset) {
      await store.updateAsset(props.asset.id, payload())
      router.push(`/assets/${props.asset.id}`)
    } else {
      const created = await store.createAsset(payload())
      router.push(`/assets/${created.id}`)
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
  @apply w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:outline-none;
}
</style>
