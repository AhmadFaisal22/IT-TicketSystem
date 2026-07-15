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

    <!-- Search across every category & subcategory (matches EN + 中文) -->
    <div class="relative mb-2">
      <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
        fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/>
      </svg>
      <input v-model="search" type="text" :placeholder="t('ticket.searchCategory')"
        class="w-full pl-9 pr-8 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none" />
      <button v-if="search" type="button" @click="search = ''"
        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <!-- Search results: flat list, one click selects category + subcategory -->
    <div v-if="searching" class="border border-gray-200 rounded-xl divide-y divide-gray-100 overflow-hidden">
      <button v-for="hit in searchResults" :key="hit.catId + '/' + hit.subId" type="button"
        @click="pickSearchResult(hit)"
        class="w-full flex items-center gap-2 px-3 py-2.5 text-left text-sm hover:bg-red-50 transition">
        <span class="text-base leading-none">{{ hit.emoji }}</span>
        <span class="font-medium text-gray-800">{{ hit.subLabel }}</span>
        <span class="ml-auto text-xs text-gray-400 shrink-0">{{ hit.catLabel }}</span>
      </button>
      <p v-if="!searchResults.length" class="px-3 py-3 text-xs text-gray-400">
        {{ t('ticket.noSearchResults') }}
      </p>
    </div>

    <!-- Category cards grid -->
    <div v-else class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-5 gap-2">
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
      <div v-if="selectedCat && !searching" class="bg-gray-50 border border-gray-200 rounded-xl p-3 sm:p-4">
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
            <button v-if="resourceState === 'ready' && resourceMeta"
              type="button" @click="downloadResource" :disabled="downloading"
              class="inline-flex items-center gap-1 text-blue-500 hover:text-blue-400 underline font-medium break-all transition disabled:opacity-60">
              <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
              </svg>
              {{ downloading ? t('ticket.resourceDownloading') : resourceMeta.original_name }}
            </button>
            <span v-if="resourceState === 'ready'" class="block mt-1 text-gray-500">
              {{ t('ticket.resourceAttachHint') }}
            </span>
            <span v-else-if="resourceState === 'loading'" class="text-gray-400">…</span>
            <span v-else-if="resourceState === 'missing'" class="text-gray-500 italic">
              {{ t('ticket.resourceMissing') }}
            </span>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { CATEGORIES } from '@/constants/categories'
import { resourceFileApi } from '@/api'

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

// Search: case-insensitive substring match against BOTH languages of the
// category and subcategory names, so "打印" and "print" both work whatever
// the UI locale. Multi-word queries AND together ("email account" ==
// "account email").
const search = ref('')
const searching = computed(() => search.value.trim().length > 0)

interface SearchHit { catId: string; subId: string; emoji: string; subLabel: string; catLabel: string }

const searchResults = computed<SearchHit[]>(() => {
  const tokens = search.value.trim().toLowerCase().split(/\s+/).filter(Boolean)
  if (!tokens.length) return []
  const hits: SearchHit[] = []
  for (const cat of CATEGORIES) {
    for (const sub of cat.subs) {
      const hay = `${cat.en} ${cat.zh} ${cat.short_en} ${cat.short_zh} ${sub.en} ${sub.zh}`.toLowerCase()
      if (tokens.every(tk => hay.includes(tk))) {
        hits.push({
          catId: cat.id,
          subId: sub.id,
          emoji: cat.emoji,
          subLabel: locale.value === 'zh' ? sub.zh : sub.en,
          catLabel: locale.value === 'zh' ? cat.zh : cat.en,
        })
      }
    }
  }
  return hits.slice(0, 15)
})

function pickSearchResult(hit: SearchHit) {
  emit('update:category', hit.catId)
  emit('update:subcategory', hit.subId)
  search.value = ''
}

const selectedSubHint = computed(() =>
  selectedCat.value?.subs.find(s => s.id === props.subcategory)?.hint ?? null
)

// The hinted file is admin-managed and served through the authenticated
// API, so we fetch its current name for display and download it as a blob.
interface ResourceMeta { key: string; original_name: string; size: number }

const resourceMeta = ref<ResourceMeta | null>(null)
const resourceState = ref<'idle' | 'loading' | 'ready' | 'missing'>('idle')
const downloading = ref(false)

watch(() => selectedSubHint.value?.resourceKey, async (key) => {
  resourceMeta.value = null
  if (!key) {
    resourceState.value = 'idle'
    return
  }
  resourceState.value = 'loading'
  try {
    const { data } = await resourceFileApi.get(key)
    resourceMeta.value = data
    resourceState.value = 'ready'
  } catch {
    resourceState.value = 'missing'
  }
}, { immediate: true })

async function downloadResource() {
  const key = selectedSubHint.value?.resourceKey
  const name = resourceMeta.value?.original_name
  if (!key || !name || downloading.value) return
  downloading.value = true
  try {
    const { data } = await resourceFileApi.download(key)
    const url = URL.createObjectURL(data)
    const link = document.createElement('a')
    link.href = url
    link.download = name
    link.click()
    URL.revokeObjectURL(url)
  } finally {
    downloading.value = false
  }
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
