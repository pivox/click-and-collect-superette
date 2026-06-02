import { render, screen } from '@testing-library/react';
import React from 'react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('next/navigation', () => ({
  usePathname: () => '/',
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
}));
vi.mock('@/components/layout/DesktopNav', () => ({
  DesktopNav: () => <aside data-testid="desktop-nav" />,
}));
vi.mock('@/components/layout/BottomNav', () => ({
  BottomNav: () => <nav data-testid="bottom-nav" />,
}));
vi.mock('@/lib/store/SelectedStoreContext', () => ({
  SelectedStoreProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
  useSelectedStore: () => ({ selectedStore: null, selectStore: vi.fn(), clearStore: vi.fn() }),
}));
vi.mock('@/components/store/StoreContextPill', () => ({
  StoreContextPill: () => <div data-testid="store-context-pill" />,
}));

import ClientLayout from '@/app/(client)/layout';

describe('ClientLayout', () => {
  it('rend la DesktopNav et la BottomNav', () => {
    render(<ClientLayout>page</ClientLayout>);
    expect(screen.getByTestId('desktop-nav')).toBeTruthy();
    expect(screen.getByTestId('bottom-nav')).toBeTruthy();
  });

  it('rend les children une seule fois', () => {
    const { container } = render(<ClientLayout><span data-testid="child">content</span></ClientLayout>);
    expect(container.querySelectorAll('[data-testid="child"]')).toHaveLength(1);
  });

  it('les children sont dans un <main>', () => {
    const { container } = render(<ClientLayout>page</ClientLayout>);
    expect(container.querySelector('main')).toBeTruthy();
    expect(container.querySelector('main')?.textContent).toContain('page');
  });

  it('contraint la colonne de contenu desktop pour éviter les débordements horizontaux', () => {
    const { container } = render(<ClientLayout>page</ClientLayout>);
    const contentColumn = container.querySelector('[data-testid="client-content-column"]');
    const main = container.querySelector('main');
    expect(contentColumn?.className).toContain('min-w-0');
    expect(main?.className).toContain('min-w-0');
  });

  it('rend la StoreContextPill dans le main', () => {
    render(<ClientLayout>page</ClientLayout>);
    expect(screen.getByTestId('store-context-pill')).toBeTruthy();
  });
});
