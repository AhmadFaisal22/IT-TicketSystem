import { attachmentApi } from '@/api'

// Attachments live on a private disk and are served through an authenticated
// API route, so plain <a href>/<img src> cannot reach them — fetch as a blob.

export async function downloadAttachment(att: { id: number; original_name: string }): Promise<void> {
  const { data } = await attachmentApi.download(att.id)
  const url = URL.createObjectURL(data)
  const link = document.createElement('a')
  link.href = url
  link.download = att.original_name
  link.click()
  URL.revokeObjectURL(url)
}

export async function attachmentPreviewUrl(id: number): Promise<string> {
  const { data } = await attachmentApi.download(id)
  return URL.createObjectURL(data)
}
