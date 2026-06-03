import { render } from '@testing-library/react';
import React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { DesktopNav } from '@/components/layout/DesktopNav';

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
  useClientNotifications: () => ({ unreadCount: 0 }),
}));

describe('DesktopNav theme', () => {
  it('teinte légèrement le menu gauche avec le thème actif', () => {
    const { container } = render(<DesktopNav />);
    const sidebar = container.querySelector('aside');

    expect(sidebar?.className).toContain('client-theme-sidebar');
  });
});
