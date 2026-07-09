import { ref } from 'vue'
import { downloadAttachment, attachmentPreviewUrl } from '@/utils/attachments'

export interface PreviewableAttachment {
  id: number
  original_name: string
  mime_type: string
}

// Shared preview behavior for ticket, asset and comment attachments:
// images open in the ImageLightbox, PDFs in a new tab, anything else downloads.
// `getCachedUrl` lets callers reuse blob URLs they already hold (thumbnails) —
// those must not be revoked on close, so `ownedUrl` marks lightbox-only URLs.
export function useAttachmentPreview(getCachedUrl?: (att: PreviewableAttachment) => string | undefined) {
  const lightbox = ref<{ url: string; name: string; ownedUrl: boolean } | null>(null)

  async function openPreview(att: PreviewableAttachment) {
    if (att.mime_type.startsWith('image/')) {
      const cached = getCachedUrl?.(att)
      if (cached) {
        lightbox.value = { url: cached, name: att.original_name, ownedUrl: false }
        return
      }
      try {
        const url = await attachmentPreviewUrl(att.id)
        lightbox.value = { url, name: att.original_name, ownedUrl: true }
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
    if (lightbox.value?.ownedUrl) URL.revokeObjectURL(lightbox.value.url)
    lightbox.value = null
  }

  return { lightbox, openPreview, closeLightbox }
}
