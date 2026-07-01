<template>
  <div class="relative overflow-hidden rounded-card p-5 text-white shadow-soft-lg" :class="gradientClass">
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <div class="text-3xl font-extrabold leading-none tracking-tight">{{ value }}</div>
        <div class="mt-1.5 text-sm font-medium text-white/90 truncate">{{ label }}</div>
      </div>
      <div v-if="$slots.icon || icon" class="rounded-xl bg-white/20 p-2 flex-shrink-0">
        <slot name="icon">
          <component :is="icon" v-if="icon" class="w-5 h-5" />
        </slot>
      </div>
    </div>
    <div v-if="trend" class="mt-3 text-xs font-medium text-white/85">{{ trend }}</div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  variant?: 'open' | 'progress' | 'pending' | 'resolved' | 'closed' | 'brand'
  value: string | number
  label: string
  icon?: any
  trend?: string
}>(), { variant: 'brand' })

const map: Record<string, string> = {
  open: 'bg-grad-open',
  brand: 'bg-grad-brand',
  progress: 'bg-grad-progress',
  pending: 'bg-grad-pending',
  resolved: 'bg-grad-resolved',
  closed: 'bg-grad-closed',
}

const gradientClass = computed(() => map[props.variant] || map.brand)
</script>
