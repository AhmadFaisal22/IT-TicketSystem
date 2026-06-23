# Asset Attachment Preview — Design

**Date:** 2026-06-23
**Status:** Approved (design), pending implementation plan
**Scope:** Frontend only — `it-helpdesk-frontend/src/views/assets/AssetDetailView.vue`

## Problem

On the asset detail page, the Attachments panel renders each attachment as a
plain filename link. Clicking it always **downloads** the file. Attachments are
only ever images (jpg/jpeg/png/gif/webp) or PDF, and users want to *view* them
without first downloading to disk.

## Goal

Make clicking an attachment **preview** it, while keeping download available.

- **Image** → open an in-page lightbox modal showing the full image.
- **PDF** → open in a new browser tab (native browser rendering).
- **Download** → kept as a per-row icon button (all types) plus a button inside
  the image lightbox.

Out of scope: ticket detail page and all other views remain unchanged.

## Background — existing plumbing

Attachments live on a private disk and are served through an authenticated API
route, so a plain `<a href>`/`<img src>` cannot reach them. `src/utils/attachments.ts`
already provides:

- `downloadAttachment(att)` — fetches the blob and triggers a save.
- `attachmentPreviewUrl(id)` — fetches the blob and returns a
  `URL.createObjectURL(...)` object URL.

Both go through `attachmentApi.download(id)`. The asset attachment objects expose
`{ id, original_name, mime_type }` (mime_type is used to branch image vs PDF).

The codebase already has an established modal pattern (e.g.
`views/admin/DepartmentsView.vue`): `<Teleport to="body">` + a
`fixed inset-0 z-50` flex container + a `bg-black/50` backdrop whose click closes
the modal. The new lightbox follows this pattern.

## UI

```
Attachments                                + Upload File
  📄 photo.jpg                       [↓]  [✕]
  📄 manual.pdf                      [↓]  [✕]
```

- File name button: `@click="openPreview(att)"` (was `downloadAttachment`).
- New download button: a small ↓ icon, styled to match the existing `✕` remove
  button (`text-gray-400 hover:text-red-600`), placed between the name and `✕`.
- `✕` remove button: unchanged.

Image lightbox (centered, sized within the viewport):

```
┌──────────────── overlay (bg-black/50) ──────────────┐
│                                              [ ✕ ]   │
│        ┌──────────────────────────────────┐         │
│        │            <img>                  │         │
│        └──────────────────────────────────┘         │
│              [ ↓ Download ]   photo.jpg              │
└─────────────────────────────────────────────────────┘
```

## Components

### New: `src/components/ui/ImageLightbox.vue`

A small, reusable, presentational component.

- **Props:** `src: string` (blob object URL), `name: string`.
- **Emits:** `close`.
- **Markup:** `<Teleport to="body">` → `fixed inset-0 z-50 flex items-center
  justify-center p-4` → `bg-black/50` backdrop (click → `close`) → centered card
  with the `<img :src :alt="name" class="max-h-[85vh] max-w-full object-contain">`,
  a top-right `✕` (→ `close`), and a bottom row with a download button and the
  file name.
- **Download button:** downloads directly from the blob URL it already holds —
  `const a = document.createElement('a'); a.href = src; a.download = name; a.click()`.
  No re-fetch, no emit (the component already has everything it needs).
- **Keyboard:** `Escape` → `close` (listener added on mount, removed on unmount).
- It does **not** fetch or revoke blob URLs — the parent owns that lifecycle.

### Modified: `AssetDetailView.vue`

State:

- `lightbox = ref<{ url: string; name: string } | null>(null)`

Handlers:

- `openPreview(att)`:
  - If `att.mime_type.startsWith('image/')`:
    - `const url = await attachmentPreviewUrl(att.id)`
    - `lightbox.value = { url, name: att.original_name }`
  - Else if `att.mime_type === 'application/pdf'`:
    - Open new tab (see lifecycle/popup notes below).
  - Else: fall back to `downloadAttachment(att)`.
  - On any thrown error: fall back to `downloadAttachment(att)`.
- `closeLightbox()`: `URL.revokeObjectURL(lightbox.value.url)`, then
  `lightbox.value = null`.
- Per-row download button calls existing `downloadAttachment(att)`.

Template: render `<ImageLightbox v-if="lightbox" :src="lightbox.url"
:name="lightbox.name" @close="closeLightbox" />`. The lightbox handles its own
download from the blob URL it was given.

## Key technical decisions

1. **PDF new-tab popup blocking.** Calling `window.open` *after* an `await`
   loses the user-gesture context and gets blocked. Open the tab **synchronously**
   first, then point it at the blob once ready:

   ```ts
   const w = window.open('', '_blank')          // sync, inside the click handler
   try {
     const url = await attachmentPreviewUrl(att.id)
     if (w) { w.location.href = url; setTimeout(() => URL.revokeObjectURL(url), 60_000) }
     else { downloadAttachment(att) }           // popup blocked → fall back
   } catch {
     w?.close(); downloadAttachment(att)
   }
   ```

2. **Blob URL lifecycle.**
   - Image: revoke on lightbox close.
   - PDF: revoke ~60s after handing the URL to the new tab (long enough for the
     tab to load it into memory; avoids the leak of never revoking).

3. **Error handling.** Any preview failure (network / 403 / blocked popup) falls
   back to `downloadAttachment`, so a click never silently does nothing.

4. **i18n.** Add keys under `asset.actions` in both `src/locales/en.ts` and
   `src/locales/zh.ts`:
   - `download` — "Download" / "下载" (button `title` / `aria-label`)
   - `preview` — "Preview" / "预览" (file name button `title`)
   - `closePreview` — "Close" / "关闭" (lightbox `✕` `aria-label`)

## Testing

Backend is untouched — no backend tests needed.

Manual verification via the `run-it-helpdesk` skill (launch app, screenshot):

1. Image attachment → click name → lightbox opens with the image; download
   button works; `✕`, backdrop click, and `Escape` each close it.
2. PDF attachment → click name → opens in a new tab and renders.
3. Per-row ↓ button downloads for both types.
4. Error path: simulate a failing preview (e.g. revoked auth) → falls back to a
   download attempt rather than doing nothing.

If a frontend unit-test runner (vitest) is configured, add a component test for
`ImageLightbox` (renders `src`/`name`, emits `close` on `✕`/backdrop/Escape,
emits `download`). Otherwise rely on manual verification.

## Files touched

- `it-helpdesk-frontend/src/components/ui/ImageLightbox.vue` (new)
- `it-helpdesk-frontend/src/views/assets/AssetDetailView.vue` (modified)
- `it-helpdesk-frontend/src/locales/en.ts` (modified)
- `it-helpdesk-frontend/src/locales/zh.ts` (modified)
