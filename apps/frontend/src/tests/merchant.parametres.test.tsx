import { render, screen } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it } from 'vitest';
import MerchantSettingsPage from '@/app/merchant/parametres/page';
import { MerchantLocaleProvider } from '@/lib/i18n/MerchantLocaleContext';

function renderPage() {
  return render(
    <MerchantLocaleProvider>
      <MerchantSettingsPage />
    </MerchantLocaleProvider>,
  );
}

describe('MerchantSettingsPage (MS-001 / MS-004)', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('renders working shortcuts to existing configuration pages', () => {
    renderPage();

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

  it('links the store profile, account and language shortcuts to their pages', () => {
    renderPage();

    expect(screen.getByRole('link', { name: /Profil de la supérette/i })).toHaveAttribute(
      'href',
      '/merchant/parametres/profil',
    );
    expect(screen.getByRole('link', { name: /Compte/i })).toHaveAttribute(
      'href',
      '/merchant/parametres/compte',
    );
    expect(screen.getByRole('link', { name: /Langue de l’interface/i })).toHaveAttribute(
      'href',
      '/merchant/parametres/langue',
    );
  });

  it('no longer shows a "Bientôt disponible" placeholder (language is now live)', () => {
    renderPage();

    expect(screen.queryByText(/Bientôt disponible/i)).toBeNull();
  });
});
