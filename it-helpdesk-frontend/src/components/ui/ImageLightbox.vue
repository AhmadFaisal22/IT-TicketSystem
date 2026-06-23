<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/70" @click="emit('close')"></div>
      <button
        type="button"
        :aria-label="t('asset.actions.closePreview')"
        class="absolute top-4 right-4 text-3xl leading-none text-white/80 hover:text-white"
        @click="emit('close')"
      >✕</button>
      <div class="relative flex flex-col items-center gap-3">
        <img
          :src="src"
          :alt="name"
          class="max-h-[85vh] max-w-full rounded-lg object-contain shadow-xl"
        />
        <div class="flex items-center gap-3 text-sm text-white">
          <button
            type="button"
            class="inline-flex items-center gap-1 rounded-md bg-white/10 px-3 py-1.5 hover:bg-white/20"
            @click="onDownload"
          >
            <ArrowDownTrayIcon class="h-4 w-4" />
            {{ t('asset.actions.download') }}
          </button>
          <span class="max-w-[60vw] truncate">{{ name }}</span>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline'

const props = defineProps<{ src: string; name: string }>()
const emit = defineEmits<{ close: [] }>()
const { t } = useI18n()

function onDownload() {
  const a = document.createElement('a')
  a.href = props.src
  a.download = props.name
  a.click()
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') emit('close')
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
</script>
