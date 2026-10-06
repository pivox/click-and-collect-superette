/**
 * Maps a portal identifier (carried as a `portal` query param) to its login
 * route. The password-reset pages are shared by the customer, merchant and admin
 * portals, so this keeps "back to login" links pointing to the right portal.
 * Defaults to the customer login when the portal is unknown or absent.
 */
export function loginHrefForPortal(portal: string | null | undefined): string {
  switch (portal) {
    case 'admin':
      return '/admin/login';
    case 'merchant':
      return '/merchant/login';
    default:
      return '/login';
  }
}

/** Keep a post-login destination inside the matching portal, without auth loops. */
export function safeLoginDestination(portal: 'client' | 'merchant' | 'admin', raw?: string | null): string {
  const home = portal === 'admin' ? '/admin/dashboard' : portal === 'merchant' ? '/merchant' : '/';
  if (!raw || !raw.startsWith('/') || raw.startsWith('//') || /[\\%\u0000-\u0020]/.test(raw)) return home;
  const path = raw.split(/[?#]/, 1)[0];
  if (/(?:^|\/)\.{1,2}(?:\/|$)/.test(path)) return home;
  if (portal === 'client') {
    return /^\/(?:$|stores(?:\/|$)|orders(?:\/|$)|kadhia(?:\/|$)|notifications$|profile$)/.test(path) ? raw : home;
  }
  return path.startsWith(`/${portal}/`) && !/^\/(admin|merchant)\/(login|invitation)(?:\/|$)/.test(path) ? raw : home;
}
