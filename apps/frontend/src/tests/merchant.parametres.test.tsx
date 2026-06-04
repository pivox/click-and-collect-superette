import { render, screen } from '@testing-library/react';
import React from 'react';
import { describe, expect, it } from 'vitest';
import MerchantSettingsPage from '@/app/merchant/parametres/page';

describe('MerchantSettingsPage (MS-001)', () => {
  it('renders working shortcuts to existing configuration pages', () => {
    render(React.createElement(MerchantSettingsPage));

    expect(screen.getByRole('link', { name: /Horaires & créneaux/i })).toHaveAttribute(
      'href',
      '/merchant/creneaux',
    );
    expect(screen.getByRole('link', { name: /Apparence/i })).toHaveAttribute(
      'href',
      '/merchant/apparence',
    );
    expect(screen.getByRole('link', { name: /QR code magasin/i })).toHaveAttribute(
      'href',
      '/merchant/qr-code',
    );
    expect(screen.getByRole('link', { name: /Notifications/i })).toHaveAttribute(
      'href',
      '/merchant/notifications',
    );
  });

  it('links the store profile and account shortcuts to their pages', () => {
    render(React.createElement(MerchantSettingsPage));

    expect(screen.getByRole('link', { name: /Profil de la supérette/i })).toHaveAttribute(
      'href',
      '/merchant/parametres/profil',
    );
    expect(screen.getByRole('link', { name: /Compte/i })).toHaveAttribute(
      'href',
      '/merchant/parametres/compte',
    );
  });

  it('shows the remaining language section as a "Bientôt disponible" placeholder', () => {
    render(React.createElement(MerchantSettingsPage));

    expect(screen.getAllByText(/Bientôt disponible/i)).toHaveLength(1);
  });
});
