<template>
  <AssetForm v-if="asset" :asset="asset" />
  <div v-else class="p-8 text-center text-gray-400">{{ t('common.loading') }}</div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import AssetForm from '@/components/assets/AssetForm.vue'
import { useAssetStore, type Asset } from '@/stores/assets'

const { t } = useI18n()
const route = useRoute()
const store = useAssetStore()
const asset = ref<Asset | null>(null)

onMounted(async () => {
  asset.value = await store.fetchAsset(Number(route.params.id))
})
</script>
