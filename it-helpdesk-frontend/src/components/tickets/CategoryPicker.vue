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

        <!-- Hint for selected subcategory (e.g. onboarding form link) -->
        <div v-if="selectedSubHint"
          class="mt-3 flex items-start gap-2 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2.5">
          <svg class="w-4 h-4 text-blue-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <div class="text-xs text-gray-700 leading-relaxed">
            {{ locale === 'zh' ? selectedSubHint.zh : selectedSubHint.en }}
            <a :href="selectedSubHint.linkUrl"
              class="text-blue-500 hover:text-blue-400 underline font-medium break-all">
              {{ selectedSubHint.linkText }}</a>
            <button type="button" @click="copyHintPath"
              class="ml-2 inline-flex items-center gap-1 text-blue-500 hover:text-blue-400 transition">
              <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
              </svg>
              {{ copied ? t('ticket.pathCopied') : t('ticket.copyPath') }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
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

const selectedSubHint = computed(() =>
  selectedCat.value?.subs.find(s => s.id === props.subcategory)?.hint ?? null
)

const copied = ref(false)

async function copyHintPath() {
  const path = selectedSubHint.value?.copyPath
  if (!path) return
  try {
    await navigator.clipboard.writeText(path)
  } catch {
    const ta = document.createElement('textarea')
    ta.value = path
    document.body.appendChild(ta)
    ta.select()
    document.execCommand('copy')
    document.body.removeChild(ta)
  }
  copied.value = true
  setTimeout(() => (copied.value = false), 2000)
}

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
