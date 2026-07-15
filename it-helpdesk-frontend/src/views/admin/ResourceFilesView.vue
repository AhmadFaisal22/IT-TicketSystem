<template>
  <div>
    <div class="bg-white rounded-card shadow-soft border border-gray-100 overflow-hidden">
      <div class="px-6 py-4 border-b">
        <h2 class="font-semibold text-gray-800">{{ t('admin.resources.title') }}</h2>
        <p class="mt-0.5 text-xs text-gray-500">{{ t('admin.resources.subtitle') }}</p>
      </div>

      <div class="divide-y divide-gray-100">
        <div v-for="res in MANAGED_RESOURCES" :key="res.key" class="px-6 py-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-800">
                {{ locale === 'zh' ? res.zh : res.en }}
              </p>
              <p class="text-xs text-gray-500 mt-0.5">
                {{ locale === 'zh' ? res.desc_zh : res.desc_en }}
              </p>

              <div class="mt-2 text-xs">
                <template v-if="fileOf(res.key)">
                  <button type="button" @click="download(res.key)"
                    class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-500 underline font-medium break-all transition">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                    </svg>
                    {{ fileOf(res.key)!.original_name }}
                  </button>
                  <span class="text-gray-400 ml-2">
                    {{ formatSize(fileOf(res.key)!.size) }}
                    · {{ t('admin.resources.updated') }} {{ formatDate(fileOf(res.key)!.updated_at) }}
                    <template v-if="fileOf(res.key)!.uploader">
                      · {{ t('admin.resources.uploadedBy') }} {{ fileOf(res.key)!.uploader.name }}
                    </template>
                  </span>
                </template>
                <span v-else class="text-gray-400 italic">{{ t('admin.resources.notUploaded') }}</span>
              </div>

              <p v-if="errorKey === res.key" class="mt-1.5 text-xs text-red-600">{{ errorMsg }}</p>
              <p v-else-if="successKey === res.key" class="mt-1.5 text-xs text-green-600">
                {{ t('admin.resources.uploaded') }}
              </p>
            </div>

            <button @click="pickFile(res.key)" :disabled="uploadingKey === res.key"
              class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-1.5 rounded-lg text-sm font-medium transition disabled:opacity-50 shrink-0">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 8l5-5 5 5M12 3v12"/>
              </svg>
              {{ uploadingKey === res.key
                ? t('common.loading')
                : (fileOf(res.key) ? t('admin.resources.replace') : t('admin.resources.upload')) }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <input ref="fileInput" type="file" class="hidden"
      accept=".xlsx,.xls,.csv,.doc,.docx,.pdf,.ppt,.pptx,.txt,.zip"
      @change="onFilePicked" />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { resourceFileApi } from '@/api'
import { MANAGED_RESOURCES } from '@/constants/resources'

interface ResourceFileRow {
  key: string
  original_name: string
  size: number
  updated_at: string
  uploader?: { id: number; name: string } | null
}

const { t, locale } = useI18n()

const files = ref<ResourceFileRow[]>([])
const fileInput = ref<HTMLInputElement | null>(null)
const pendingKey = ref('')
const uploadingKey = ref('')
const errorKey = ref('')
const errorMsg = ref('')
const successKey = ref('')

function fileOf(key: string): ResourceFileRow | undefined {
  return files.value.find(f => f.key === key)
}

async function load() {
  const { data } = await resourceFileApi.list()
  files.value = data
}

function pickFile(key: string) {
  pendingKey.value = key
  errorKey.value = ''
  successKey.value = ''
  fileInput.value?.click()
}

async function onFilePicked(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  const key = pendingKey.value
  input.value = ''
  if (!file || !key) return

  uploadingKey.value = key
  try {
    const fd = new FormData()
    fd.append('file', file)
    await resourceFileApi.upload(key, fd)
    await load()
    successKey.value = key
    setTimeout(() => { if (successKey.value === key) successKey.value = '' }, 3000)
  } catch (err: any) {
    errorKey.value = key
    errorMsg.value = err?.response?.status === 422
      ? t('admin.resources.invalidFile')
      : t('admin.resources.uploadFailed')
  } finally {
    uploadingKey.value = ''
  }
}

async function download(key: string) {
  const meta = fileOf(key)
  if (!meta) return
  const { data } = await resourceFileApi.download(key)
  const url = URL.createObjectURL(data)
  const link = document.createElement('a')
  link.href = url
  link.download = meta.original_name
  link.click()
  URL.revokeObjectURL(url)
}

function formatSize(bytes: number): string {
  if (bytes >= 1024 * 1024) return `${(bytes / 1024 / 1024).toFixed(1)} MB`
  return `${Math.max(1, Math.round(bytes / 1024))} KB`
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(locale.value === 'zh' ? 'zh-CN' : 'en-US', {
    year: 'numeric', month: 'short', day: 'numeric',
  })
}

onMounted(load)
</script>
