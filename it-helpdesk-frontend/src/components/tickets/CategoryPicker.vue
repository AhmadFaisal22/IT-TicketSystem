<template>
  <div>
    <div class="flex items-center justify-between mb-2">
      <label class="block text-sm font-medium text-gray-700">{{ t('ticket.category') }}</label>
      <button v-if="category" type="button" @click="clearAll"
        class="text-xs text-red-500 hover:text-red-700 flex items-center gap-1 transition">
        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
        {{ t('ticket.clearCategory') }}
      </button>
    </div>

    <!-- Category cards grid -->
    <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-5 gap-2">
      <button
        v-for="cat in CATEGORIES"
        :key="cat.id"
        type="button"
        @click="selectCategory(cat.id)"
        class="relative flex flex-col items-center justify-center p-2 sm:p-3 rounded-xl border-2 transition-all duration-150 text-center min-h-[72px] sm:min-h-[80px] group"
        :class="category === cat.id
          ? 'border-red-500 bg-red-50 shadow-sm'
          : 'border-gray-200 bg-white hover:border-red-300 hover:bg-red-50/40'"
      >
        <span class="text-xl sm:text-2xl mb-1 leading-none">{{ cat.emoji }}</span>
        <span class="text-xs leading-tight font-medium line-clamp-2"
          :class="category === cat.id ? 'text-red-700' : 'text-gray-600 group-hover:text-red-600'">
          {{ locale === 'zh' ? cat.short_zh : cat.short_en }}
        </span>
        <span v-if="category === cat.id"
          class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full"></span>
      </button>
    </div>

    <!-- Subcategory panel -->
    <Transition
      enter-active-class="overflow-hidden transition-[max-height,opacity,transform,margin-top] duration-200 ease-out"
      enter-from-class="max-h-0 opacity-0 -translate-y-1 mt-0"
      enter-to-class="max-h-96 opacity-100 translate-y-0 mt-3"
      leave-active-class="overflow-hidden transition-[max-height,opacity,transform,margin-top] duration-150 ease-in"
      leave-from-class="max-h-96 opacity-100 translate-y-0 mt-3"
      leave-to-class="max-h-0 opacity-0 -translate-y-1 mt-0">
      <div v-if="selectedCat" class="bg-gray-50 border border-gray-200 rounded-xl p-3 sm:p-4">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2.5">
          {{ selectedCat.emoji }}&nbsp;
          <span class="normal-case font-medium text-gray-700">
            {{ locale === 'zh' ? selectedCat.zh : selectedCat.en }}
          </span>
          &nbsp;- {{ t('ticket.selectSubcategory') }}
        </p>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="sub in selectedCat.subs"
            :key="sub.id"
            type="button"
            @click="selectSub(sub.id)"
            class="px-3 py-1.5 rounded-lg text-xs font-medium border transition-all duration-100"
            :class="subcategory === sub.id
              ? 'bg-red-600 border-red-600 text-white shadow-sm'
              : 'bg-white border-gray-300 text-gray-700 hover:border-red-400 hover:text-red-600'">
            {{ locale === 'zh' ? sub.zh : sub.en }}
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { CATEGORIES } from '@/constants/categories'

const props = defineProps<{
  category: string
  subcategory: string
}>()

const emit = defineEmits<{
  'update:category': [string]
  'update:subcategory': [string]
}>()

const { t, locale } = useI18n()

const selectedCat = computed(() => CATEGORIES.find(c => c.id === props.category) ?? null)

function selectCategory(id: string) {
  if (props.category === id) {
    clearAll()
    return
  }
  emit('update:category', id)
  emit('update:subcategory', '')
}

function selectSub(id: string) {
  emit('update:subcategory', props.subcategory === id ? '' : id)
}

function clearAll() {
  emit('update:category', '')
  emit('update:subcategory', '')
}
</script>
