import { fireEvent, render, screen } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AdminShell } from '@/components/admin/AdminShell';
import { AdminTable, type Column } from '@/components/admin/ui/AdminTable';

let pathname = '/admin/dashboard';

const adminContext = {
  user: { email: 'admin@kadhia.tn' } as { email: string } | null,
  logout: vi.fn(),
};

vi.mock('next/navigation', () => ({
  usePathname: () => pathname,
}));

vi.mock('@/lib/auth/AdminAuthContext', () => ({
  useAdminAuth: () => adminContext,
}));

vi.mock('@/components/feedback/FeedbackProvider', () => ({
  FeedbackProvider: ({
    appArea,
    enabled,
    shopId,
    locale,
  }: {
    appArea: string;
    enabled: boolean;
    shopId?: string | null;
    locale?: string;
  }) => (
    <div
      data-testid="admin-feedback-provider"
      data-app-area={appArea}
      data-enabled={String(enabled)}
      data-shop-id={shopId ?? ''}
      data-locale={locale ?? ''}
    />
  ),
}));

interface TestRow {
  id: string;
  name: string;
  email: string;
}

const columns: Column<TestRow>[] = [
  { key: 'name', label: 'Nom' },
  { key: 'email', label: 'Email' },
];

describe('AdminShell', () => {
  beforeEach(() => {
    pathname = '/admin/dashboard';
    adminContext.user = { email: 'admin@kadhia.tn' };
    adminContext.logout.mockClear();
  });

  it('keeps the admin content full-width on mobile and opens drawer navigation', () => {
    const { container } = render(
      React.createElement(AdminShell, null, React.createElement('p', null, 'Contenu admin')),
    );

    expect(container.firstElementChild).toHaveClass(
      'min-h-screen',
      'md:h-screen',
      'md:overflow-hidden',
    );
    expect(screen.getByRole('navigation', { name: 'Navigation admin' })).toHaveClass(
      'hidden',
      'md:flex',
    );
    expect(screen.getByRole('link', { name: 'Santé plateforme' })).toHaveAttribute(
      'href',
      '/admin/ops/messenger',
    );
    expect(screen.getByRole('link', { name: 'Métriques bêta' })).toHaveAttribute(
      'href',
      '/admin/beta-metrics',
    );
    expect(screen.getByRole('link', { name: 'Feedbacks' })).toHaveAttribute(
      'href',
      '/admin/feedbacks',
    );
    expect(screen.getByRole('button', { name: 'Ouvrir la navigation admin' })).toHaveClass(
      'md:hidden',
    );
    expect(screen.getByText('Contenu admin')).toBeInTheDocument();
    expect(screen.queryByRole('dialog', { name: 'Navigation admin' })).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Ouvrir la navigation admin' }));

    expect(screen.getByRole('dialog', { name: 'Navigation admin' })).toBeInTheDocument();
    expect(screen.getAllByRole('link', { name: /Marchands/i })).toHaveLength(2);

    fireEvent.click(screen.getAllByRole('link', { name: /Marchands/i })[1]);

    expect(screen.queryByRole('dialog', { name: 'Navigation admin' })).not.toBeInTheDocument();
  });

  it('enables admin feedback with the default French locale and no supérette', () => {
    render(
      React.createElement(AdminShell, null, React.createElement('p', null, 'Contenu admin')),
    );

    const provider = screen.getByTestId('admin-feedback-provider');
    expect(provider).toHaveAttribute('data-app-area', 'admin');
    expect(provider).toHaveAttribute('data-enabled', 'true');
    expect(provider).toHaveAttribute('data-shop-id', '');
    expect(provider).toHaveAttribute('data-locale', 'fr');
  });

  it('disables admin feedback on the admin login route', () => {
    pathname = '/admin/login';

    render(React.createElement(AdminShell, null, React.createElement('p', null, 'Connexion')));

    const provider = screen.getByTestId('admin-feedback-provider');
    expect(provider).toHaveAttribute('data-app-area', 'admin');
    expect(provider).toHaveAttribute('data-enabled', 'false');
    expect(provider).toHaveAttribute('data-shop-id', '');
    expect(provider).toHaveAttribute('data-locale', 'fr');
  });

  it('disables admin feedback when no admin is authenticated', () => {
    adminContext.user = null;

    render(React.createElement(AdminShell, null, React.createElement('p', null, 'Contenu admin')));

    expect(screen.getByTestId('admin-feedback-provider')).toHaveAttribute('data-enabled', 'false');
  });
});

describe('AdminTable', () => {
  it('keeps columns horizontally scrollable instead of compressing them on mobile', () => {
    const { container } = render(
      <AdminTable
        columns={columns}
        data={[{ id: 'row-1', name: 'Client Demo', email: 'client.demo@example.test' }]}
        pagination={{ page: 1, total: 1, limit: 20, onPageChange: vi.fn() }}
      />,
    );

    const pagination = screen.getByText('1 résultat').parentElement as HTMLElement;

    expect(container.querySelector('.overflow-x-auto')).toBeInTheDocument();
    expect(screen.getByRole('table')).toHaveClass('min-w-[720px]');
    expect(pagination).toHaveClass('flex-col', 'sm:flex-row');
  });
});
