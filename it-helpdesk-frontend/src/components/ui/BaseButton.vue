<template>
  <component
    :is="tag"
    :to="to"
    :type="tag === 'button' ? type : undefined"
    :disabled="tag === 'button' ? disabled : undefined"
    :class="classes">
    <slot />
  </component>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'ghost'
  size?: 'sm' | 'md'
  to?: string
  type?: 'button' | 'submit'
  disabled?: boolean
  block?: boolean
}>(), { variant: 'primary', size: 'md', type: 'button' })

const tag = computed(() => (props.to ? 'router-link' : 'button'))

const base =
  'inline-flex items-center justify-center gap-2 font-semibold rounded-btn transition ' +
  'focus:outline-none focus:ring-2 focus:ring-brand-600/30 ' +
  'disabled:opacity-50 disabled:cursor-not-allowed'

const sizes: Record<string, string> = {
  sm: 'px-3 py-1.5 text-xs',
  md: 'px-4 py-2 text-sm',
}

const variants: Record<string, string> = {
  primary: 'bg-brand-600 text-white hover:bg-brand-700 shadow-soft',
  secondary:
    'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 ' +
    'dark:bg-slate-800 dark:text-gray-200 dark:border-slate-600 dark:hover:bg-slate-700',
  ghost: 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-slate-700',
}

const classes = computed(() => [
  base,
  sizes[props.size],
  variants[props.variant],
  props.block ? 'w-full' : '',
])
</script>
