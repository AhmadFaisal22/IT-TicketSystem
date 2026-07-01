<template>
  <span class="pw-chip" :class="cls">{{ label }}</span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = withDefaults(defineProps<{
  kind?: 'status' | 'priority'
  value: string
}>(), { kind: 'status' })

const { t } = useI18n()

// Tint classes reuse the `-50` palette, which the .dark cascade in main.css
// already darkens — so badges stay legible in both themes.
const statusMap: Record<string, string> = {
  open: 'bg-red-50 text-red-700',
  in_progress: 'bg-blue-50 text-blue-700',
  pending: 'bg-yellow-50 text-yellow-700',
  resolved: 'bg-green-50 text-green-700',
  closed: 'bg-gray-100 text-gray-600',
  pending_approval: 'bg-purple-50 text-purple-700',
  rejected: 'bg-red-50 text-red-700',
}

const priorityMap: Record<string, string> = {
  critical: 'bg-red-50 text-red-700',
  high: 'bg-orange-50 text-orange-700',
  medium: 'bg-blue-50 text-blue-700',
  low: 'bg-gray-100 text-gray-600',
}

const cls = computed(() =>
  (props.kind === 'priority' ? priorityMap : statusMap)[props.value] || 'bg-gray-100 text-gray-600'
)

const label = computed(() => t(`ticket.${props.value}`))
</script>
