<template>
  <div v-if="loading" class="p-8 text-center text-gray-400">
    <div class="w-8 h-8 border-4 border-red-600 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
    {{ t('common.loading') }}
  </div>
  <AssetForm v-else :prefill="prefill" />
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import AssetForm from '@/components/assets/AssetForm.vue'
import { assetApi } from '@/api'
import type { Asset } from '@/stores/assets'

const { t } = useI18n()
const route = useRoute()

// ?from=<id> duplicates an existing asset: its common fields prefill the form.
const fromId = Number(route.query.from) || null
const prefill = ref<Asset | null>(null)
const loading = ref(!!fromId)

onMounted(async () => {
  if (!fromId) return
  try {
    const { data } = await assetApi.get(fromId)
    prefill.value = data
  } catch { /* source gone: fall through to a blank form */ } finally {
    loading.value = false
  }
})
</script>
