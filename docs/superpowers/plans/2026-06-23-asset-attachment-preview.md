# Asset Attachment Preview Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** On the asset detail page, clicking an attachment previews it (images in an in-page lightbox, PDFs in a new tab) instead of forcing a download, while keeping a per-row download button.

**Architecture:** A new presentational `ImageLightbox.vue` component (following the codebase's `<Teleport>` + `fixed inset-0 z-50` + `bg-black/50` modal pattern) renders an image overlay. `AssetDetailView.vue` gains an `openPreview(att)` handler that branches on `mime_type`: images set lightbox state; PDFs open a new tab (synchronous `window.open` to dodge popup blocking, matching the existing `printLabel` precedent); anything else falls back to download. The existing `attachmentPreviewUrl`/`downloadAttachment` utils are reused unchanged.

**Tech Stack:** Vue 3 `<script setup>` + TypeScript, Tailwind CSS, vue-i18n, `@heroicons/vue/24/outline`.

## Global Constraints

- Scope is frontend only: `it-helpdesk-frontend/`. No backend changes.
- Only `AssetDetailView.vue` changes behavior. Ticket detail and all other views stay as-is.
- No frontend test runner exists (scripts: `dev`, `build`, `preview`). Do **not** add a test framework. Verify each task with a typecheck (`npx vue-tsc --noEmit`) and verify the final result manually via the `run-it-helpdesk` skill.
- Commit messages must **not** include a `Co-Authored-By` trailer (user preference).
- Attachment objects are `AssetAttachment` from `@/stores/assets`: `{ id: number; original_name: string; mime_type: string; size: number; url: string }`.
- Supported attachment types are images (`image/*`) and `application/pdf` only.
- Follow existing modal markup: `<Teleport to="body">`, `fixed inset-0 z-50 flex items-center justify-center p-4`, `bg-black/50` backdrop whose click closes.

---

### Task 1: Add i18n keys

**Files:**
- Modify: `it-helpdesk-frontend/src/locales/en.ts:270-273` (the `asset.actions` block)
- Modify: `it-helpdesk-frontend/src/locales/zh.ts:272-275` (the `asset.actions` block)

**Interfaces:**
- Consumes: nothing.
- Produces: i18n keys `asset.actions.download`, `asset.actions.preview`, `asset.actions.closePreview` (used by Tasks 2 and 3).

- [ ] **Step 1: Add the English keys**

In `it-helpdesk-frontend/src/locales/en.ts`, change the `asset.actions` block (lines 270-273) from:

```ts
    actions: {
      create: 'Add Asset', edit: 'Edit Asset', assign: 'Assign',
      returnToStock: 'Return to Stock', changeStatus: 'Change Status', uploadFile: 'Upload File',
    },
```

to:

```ts
    actions: {
      create: 'Add Asset', edit: 'Edit Asset', assign: 'Assign',
      returnToStock: 'Return to Stock', changeStatus: 'Change Status', uploadFile: 'Upload File',
      download: 'Download', preview: 'Preview', closePreview: 'Close',
    },
```

- [ ] **Step 2: Add the Chinese keys**

In `it-helpdesk-frontend/src/locales/zh.ts`, change the `asset.actions` block (lines 272-275) from:

```ts
    actions: {
      create: '添加资产', edit: '编辑资产', assign: '分配',
      returnToStock: '回收入库', changeStatus: '改状态', uploadFile: '上传文件',
    },
```

to:

```ts
    actions: {
      create: '添加资产', edit: '编辑资产', assign: '分配',
      returnToStock: '回收入库', changeStatus: '改状态', uploadFile: '上传文件',
      download: '下载', preview: '预览', closePreview: '关闭',
    },
```

- [ ] **Step 3: Typecheck**

Run: `cd it-helpdesk-frontend && npx vue-tsc --noEmit`
Expected: no errors (exit 0).

- [ ] **Step 4: Commit**

```bash
git add it-helpdesk-frontend/src/locales/en.ts it-helpdesk-frontend/src/locales/zh.ts
git commit -m "i18n: add asset attachment preview/download labels"
```

---

### Task 2: Create the ImageLightbox component

**Files:**
- Create: `it-helpdesk-frontend/src/components/ui/ImageLightbox.vue`

**Interfaces:**
- Consumes: i18n keys `asset.actions.download`, `asset.actions.closePreview` (Task 1).
- Produces: component `ImageLightbox` with props `{ src: string; name: string }` and emit `close` (used by Task 3). Handles its own download from the `src` blob URL; does **not** fetch or revoke blob URLs (the parent owns that).

- [ ] **Step 1: Write the component**

Create `it-helpdesk-frontend/src/components/ui/ImageLightbox.vue`:

```vue
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
```

- [ ] **Step 2: Typecheck**

Run: `cd it-helpdesk-frontend && npx vue-tsc --noEmit`
Expected: no errors (exit 0).

- [ ] **Step 3: Commit**

```bash
git add it-helpdesk-frontend/src/components/ui/ImageLightbox.vue
git commit -m "feat(ui): add ImageLightbox component for image preview"
```

---

### Task 3: Wire preview into AssetDetailView

**Files:**
- Modify: `it-helpdesk-frontend/src/views/assets/AssetDetailView.vue` (attachments `<ul>` ~lines 50-57; imports ~lines 194-198; add lightbox state + handlers in `<script setup>`; render `<ImageLightbox>` in template)

**Interfaces:**
- Consumes: `ImageLightbox` (Task 2); i18n keys `asset.actions.preview`, `asset.actions.download` (Task 1); `attachmentPreviewUrl`, `downloadAttachment` from `@/utils/attachments`; `AssetAttachment` type from `@/stores/assets`.
- Produces: end-user behavior (final task).

- [ ] **Step 1: Update imports**

In `it-helpdesk-frontend/src/views/assets/AssetDetailView.vue`:

Change line 194 from:
```ts
import { CheckIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
```
to:
```ts
import { CheckIcon, MagnifyingGlassIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline'
```

Change line 195 from:
```ts
import { useAssetStore, ASSET_STATUSES } from '@/stores/assets'
```
to:
```ts
import { useAssetStore, ASSET_STATUSES, type AssetAttachment } from '@/stores/assets'
```

Change line 198 from:
```ts
import { downloadAttachment } from '@/utils/attachments'
```
to:
```ts
import { downloadAttachment, attachmentPreviewUrl } from '@/utils/attachments'
import ImageLightbox from '@/components/ui/ImageLightbox.vue'
```

- [ ] **Step 2: Add lightbox state and handlers**

In the same file, add this alongside the other `<script setup>` functions (e.g. directly after the existing `removeAttachment` function, around line 380):

```ts
const lightbox = ref<{ url: string; name: string } | null>(null)

async function openPreview(att: AssetAttachment) {
  if (att.mime_type.startsWith('image/')) {
    try {
      const url = await attachmentPreviewUrl(att.id)
      lightbox.value = { url, name: att.original_name }
    } catch {
      downloadAttachment(att)
    }
    return
  }
  if (att.mime_type === 'application/pdf') {
    // Open the tab synchronously (before await) so the browser keeps the
    // user-gesture context and does not block it as a popup.
    const w = window.open('', '_blank')
    try {
      const url = await attachmentPreviewUrl(att.id)
      if (w) {
        w.location.href = url
        // Give the new tab time to load the blob before reclaiming it.
        setTimeout(() => URL.revokeObjectURL(url), 60_000)
      } else {
        downloadAttachment(att)
      }
    } catch {
      w?.close()
      downloadAttachment(att)
    }
    return
  }
  downloadAttachment(att)
}

function closeLightbox() {
  if (lightbox.value) URL.revokeObjectURL(lightbox.value.url)
  lightbox.value = null
}
```

Note: `ref` is already imported at line 190.

- [ ] **Step 3: Update the attachments list markup**

Replace the attachments `<ul>` (lines 50-57):

```vue
        <ul class="space-y-2">
          <li v-for="att in asset.attachments" :key="att.id" class="flex items-center justify-between text-sm">
            <button type="button" @click="downloadAttachment(att)"
              class="text-blue-600 hover:underline truncate text-left cursor-pointer">{{ att.original_name }}</button>
            <button @click="removeAttachment(att.id)" class="text-gray-400 hover:text-red-600">✕</button>
          </li>
          <li v-if="!asset.attachments?.length" class="text-sm text-gray-400">—</li>
        </ul>
```

with:

```vue
        <ul class="space-y-2">
          <li v-for="att in asset.attachments" :key="att.id" class="flex items-center justify-between gap-2 text-sm">
            <button type="button" @click="openPreview(att)" :title="t('asset.actions.preview')"
              class="text-blue-600 hover:underline truncate text-left cursor-pointer">{{ att.original_name }}</button>
            <div class="flex shrink-0 items-center gap-2">
              <button type="button" @click="downloadAttachment(att)" :aria-label="t('asset.actions.download')"
                class="text-gray-400 hover:text-red-600"><ArrowDownTrayIcon class="h-4 w-4" /></button>
              <button @click="removeAttachment(att.id)" class="text-gray-400 hover:text-red-600">✕</button>
            </div>
          </li>
          <li v-if="!asset.attachments?.length" class="text-sm text-gray-400">—</li>
        </ul>
```

- [ ] **Step 4: Render the lightbox**

Add the lightbox just before the final closing `</div>` of the template's root element (it self-teleports to `<body>`, so exact placement only needs to be inside the template):

```vue
    <ImageLightbox v-if="lightbox" :src="lightbox.url" :name="lightbox.name" @close="closeLightbox" />
```

- [ ] **Step 5: Typecheck**

Run: `cd it-helpdesk-frontend && npx vue-tsc --noEmit`
Expected: no errors (exit 0).

- [ ] **Step 6: Commit**

```bash
git add it-helpdesk-frontend/src/views/assets/AssetDetailView.vue
git commit -m "feat(assets): preview attachments (image lightbox, PDF new tab)"
```

---

### Task 4: Manual verification

**Files:** none (verification only).

**Interfaces:**
- Consumes: the full feature (Tasks 1-3).
- Produces: confirmation the feature works end-to-end.

- [ ] **Step 1: Launch the app**

Use the `run-it-helpdesk` skill to start the backend + frontend and open an asset that has both an image and a PDF attachment (upload them via "+ Upload File" if needed).

- [ ] **Step 2: Verify image preview**

Click an image attachment's name. Expect: lightbox opens showing the image. Verify the in-lightbox Download button saves the file, and that `✕`, clicking the dark backdrop, and pressing `Escape` each close it. Screenshot the open lightbox.

- [ ] **Step 3: Verify PDF preview**

Click a PDF attachment's name. Expect: it opens in a new browser tab and renders. (If the browser blocks the popup, it should fall back to downloading.)

- [ ] **Step 4: Verify per-row download**

Click the ↓ icon on both an image row and a PDF row. Expect: each downloads the file directly without opening a preview.

- [ ] **Step 5: Production build sanity check**

Run: `cd it-helpdesk-frontend && npm run build`
Expected: build succeeds (exit 0).

---

## Self-Review notes

- **Spec coverage:** image lightbox (Task 2/3), PDF new tab with sync-open popup guard (Task 3 Step 2), per-row download + lightbox download (Task 2/3), error fallback to download (Task 3 Step 2), blob lifecycle revoke on close + 60s for PDF (Task 3 Step 2), i18n keys (Task 1), manual verification (Task 4). All spec sections mapped.
- **No test runner:** TDD-with-unit-tests is intentionally replaced by typecheck + manual verification per Global Constraints (no vitest in the project; adding one is out of scope / YAGNI).
- **Type consistency:** `openPreview(att: AssetAttachment)`, `lightbox: { url, name }`, `closeLightbox`, `ImageLightbox` props `{ src, name }` + emit `close` are used identically across Tasks 2 and 3.
