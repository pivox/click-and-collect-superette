import { render } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { BottomNav } from '@/components/layout/BottomNav';
import { DesktopNav } from '@/components/layout/DesktopNav';
import { ClientLocaleProvider } from '@/lib/i18n/ClientLocaleContext';

let unreadCount = 0;

vi.mock('next/navigation', () => ({
  usePathname: () => '/stores',
  useRouter: () => ({ push: vi.fn() }),
}));

vi.mock('@/lib/auth/ClientAuthContext', () => ({
  useClientAuth: () => ({ user: null, logout: vi.fn() }),
}));

vi.mock('@/lib/store/SelectedStoreContext', () => ({
  useSelectedStore: () => ({
    selectedStore: { id: 'store-1', name: 'Supérette Test', logoLetter: 'S' },
  }),
}));

vi.mock('@/lib/hooks/useHydrated', () => ({
  useHydrated: () => true,
}));

vi.mock('@/lib/notifications/ClientNotificationsContext', () => ({
  useClientNotifications: () => ({ unreadCount }),
}));

describe('DesktopNav theme', () => {
  beforeEach(() => {
    unreadCount = 0;
  });

  it('teinte légèrement le menu gauche avec le thème actif', () => {
    const { container } = render(
      <ClientLocaleProvider>
        <DesktopNav />
      </ClientLocaleProvider>,
    );
    const sidebar = container.querySelector('aside');

    expect(sidebar?.className).toContain('client-theme-sidebar');
  });

  it('keeps notification live regions mounted when unread count is zero', () => {
    const { container } = render(
      <ClientLocaleProvider>
        <DesktopNav />
        <BottomNav />
      </ClientLocaleProvider>,
    );

    const badges = Array.from(container.querySelectorAll('[aria-live="polite"][aria-atomic="true"]'));

    expect(badges).toHaveLength(2);
    expect(badges.every((badge) => !badge.hasAttribute('aria-hidden'))).toBe(true);
    expect(badges.every((badge) => badge.textContent === '')).toBe(true);
    expect(badges.every((badge) => !badge.className.includes('hidden'))).toBe(true);
  });
});
