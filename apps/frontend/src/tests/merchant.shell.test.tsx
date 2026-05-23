import { render, screen } from '@testing-library/react';
import React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { MerchantShell } from '@/components/merchant/MerchantShell';

vi.mock('next/navigation', () => ({
  usePathname: () => '/merchant',
}));

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => ({
    merchant: {
      email: 'marchand@kadhia.tn',
      store: { id: 'store-1', name: 'Supérette Ezzahra', active: true },
    },
    logout: vi.fn(),
  }),
}));

describe('MerchantShell', () => {
  it('renders active merchant navigation and disabled future sections', () => {
    render(
      React.createElement(
        MerchantShell,
        null,
        React.createElement('p', null, 'Contenu marchand'),
      ),
    );

    expect(screen.getAllByText('Supérette Ezzahra')).toHaveLength(2);
    expect(screen.getByText('marchand@kadhia.tn')).toBeInTheDocument();
    expect(screen.getAllByRole('link', { name: /Dashboard/i })[0]).toHaveAttribute(
      'href',
      '/merchant',
    );
    expect(screen.getAllByRole('link', { name: /Commandes/i })[0]).toHaveAttribute(
      'href',
      '/merchant/commandes',
    );
    expect(screen.getByRole('button', { name: /Créneaux/i })).toBeDisabled();
    expect(screen.getByRole('button', { name: /Catalogue/i })).toBeDisabled();
    expect(screen.getByRole('button', { name: /Paramètres/i })).toBeDisabled();
    expect(screen.getByText('Contenu marchand')).toBeInTheDocument();
  });
});
