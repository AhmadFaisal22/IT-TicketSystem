<template>
  <div class="pw-card" :class="padded ? 'p-5 sm:p-6' : ''">
    <div v-if="$slots.header" class="mb-4 flex items-center justify-between gap-3">
      <slot name="header" />
    </div>
    <slot />
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ padded?: boolean }>(), { padded: true })
</script>
