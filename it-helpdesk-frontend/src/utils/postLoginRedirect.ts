// Remembers where the user was headed when the auth guard bounced them to login,
// so we can return them there after they sign in — including across the OAuth
// round-trip (which is why this lives in localStorage, not a route query param).

const KEY = 'redirectAfterLogin'

// Only same-origin absolute paths are stored/returned, so a crafted
// `redirectAfterLogin` can never bounce the user to an external site.
function isSafe(path: string | null): path is string {
  return !!path && path.startsWith('/') && !path.startsWith('//')
}

export function saveRedirect(path: string): void {
  if (isSafe(path) && path !== '/') {
    localStorage.setItem(KEY, path)
  }
}

export function consumeRedirect(): string | null {
  const path = localStorage.getItem(KEY)
  localStorage.removeItem(KEY)
  return isSafe(path) ? path : null
}
