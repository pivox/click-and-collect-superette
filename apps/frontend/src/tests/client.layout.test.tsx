import { render } from '@testing-library/react';
import React from 'react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('next/navigation', () => ({
  usePathname: () => '/',
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
}));
vi.mock('@/components/layout/MobileShell', () => ({
  MobileShell: ({ children }: { children: React.ReactNode }) => (
    <div data-testid="mobile-shell">{children}</div>
  ),
}));
vi.mock('@/components/layout/DesktopShell', () => ({
  DesktopShell: ({ children }: { children: React.ReactNode }) => (
    <div data-testid="desktop-shell">{children}</div>
  ),
}));
vi.mock('@/components/layout/BottomNav', () => ({
  BottomNav: () => <nav data-testid="bottom-nav" />,
}));

import ClientLayout from '@/app/(client)/layout';

describe('ClientLayout double shell', () => {
  it('rend les deux shells dans le DOM', () => {
    const { container } = render(<ClientLayout>page</ClientLayout>);
    expect(container.querySelector('[data-testid="mobile-shell"]')).toBeTruthy();
    expect(container.querySelector('[data-testid="desktop-shell"]')).toBeTruthy();
  });

  it('le wrapper mobile a la classe md:hidden', () => {
    const { container } = render(<ClientLayout>page</ClientLayout>);
    const mobileWrapper = container.querySelector('[data-testid="mobile-shell"]')?.parentElement;
    expect(mobileWrapper?.className).toContain('md:hidden');
  });

  it('le wrapper desktop a la classe hidden md:block', () => {
    const { container } = render(<ClientLayout>page</ClientLayout>);
    const desktopWrapper = container.querySelector('[data-testid="desktop-shell"]')?.parentElement;
    expect(desktopWrapper?.className).toContain('hidden');
    expect(desktopWrapper?.className).toContain('md:block');
  });
});
