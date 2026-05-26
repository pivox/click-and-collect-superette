import { render, screen } from '@testing-library/react';
import React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { DesktopShell } from '@/components/layout/DesktopShell';

vi.mock('next/navigation', () => ({ usePathname: () => '/' }));
vi.mock('next/link', () => ({
  default: ({ href, children }: { href: string; children: React.ReactNode }) => (
    <a href={href}>{children}</a>
  ),
}));

describe('DesktopShell nav hrefs', () => {
  it('links to /(client)/ routes, not /desktop/', () => {
    render(<DesktopShell>content</DesktopShell>);
    const links = screen.getAllByRole('link');
    const hrefs = links.map((l) => l.getAttribute('href'));
    expect(hrefs).toContain('/');
    expect(hrefs).toContain('/stores');
    expect(hrefs).toContain('/kadhia');
    expect(hrefs).toContain('/orders');
    expect(hrefs.some((h) => h?.startsWith('/desktop'))).toBe(false);
  });
});
