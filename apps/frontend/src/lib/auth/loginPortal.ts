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
