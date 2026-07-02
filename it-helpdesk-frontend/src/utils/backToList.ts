import type { Router } from 'vue-router'

// Navigate from a detail page back to its list page. When the previous
// history entry IS that list page, use history back so the list's filter
// query params (and scroll) survive; on deep links (notifications, pasted
// URLs) there is no such entry, so fall back to a plain navigation.
export function backToList(router: Router, listPath: string) {
  const prev = window.history.state?.back
  if (typeof prev === 'string' && (prev === listPath || prev.startsWith(`${listPath}?`))) {
    router.back()
  } else {
    router.push(listPath)
  }
}
