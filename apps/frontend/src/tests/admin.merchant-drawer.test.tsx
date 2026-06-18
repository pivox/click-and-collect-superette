import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { MerchantDrawer } from '@/components/admin/marchands/MerchantDrawer';
import {
  createMerchant,
  resetMerchantTemporaryPassword,
  updateMerchant,
} from '@/lib/services/admin/merchants.service';
import type { Merchant } from '@/lib/types/admin/merchants.types';

vi.mock('@/components/admin/marchands/MerchantCrmSection', () => ({
  MerchantCrmSection: () => <div data-testid="merchant-crm-section" />,
}));

vi.mock('@/lib/services/admin/merchants.service', () => ({
  createMerchant: vi.fn(),
  updateMerchant: vi.fn(),
  resetMerchantTemporaryPassword: vi.fn(),
}));

const MERCHANT: Merchant = {
  id: 'merchant-1',
  email: 'ali@example.test',
  first_name: 'Ali',
  last_name: 'Ben Salah',
  phone: '+21600000001',
  is_active: true,
  subscription_lifecycle: null,
  created_at: '2026-06-01T10:00:00+01:00',
  stores_count: 1,
};

function renderDrawer(
  merchant: Merchant | null = MERCHANT,
  open = true,
  onClose = vi.fn(),
) {
  const onSaved = vi.fn();
  render(
    <MerchantDrawer
      open={open}
      onClose={onClose}
      merchant={merchant}
      onSaved={onSaved}
    />,
  );

  return { onClose, onSaved };
}

describe('MerchantDrawer', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.stubGlobal('confirm', vi.fn(() => true));
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: { writeText: vi.fn().mockResolvedValue(undefined) },
    });
  });

  it('affiche le bouton reset pour un marchand existant', () => {
    renderDrawer();

    expect(screen.getByRole('button', { name: 'Réinitialiser le mot de passe' })).toBeInTheDocument();
  });

  it('n’affiche pas le reset en création', () => {
    renderDrawer(null);

    expect(screen.queryByRole('button', { name: 'Réinitialiser le mot de passe' })).not.toBeInTheDocument();
  });

  it('demande confirmation avant le reset', async () => {
    vi.mocked(resetMerchantTemporaryPassword).mockResolvedValue({
      merchant_id: MERCHANT.id,
      temporary_password: 'TempPass-1234567890',
    });
    vi.stubGlobal('confirm', vi.fn(() => false));
    renderDrawer();

    fireEvent.click(screen.getByRole('button', { name: 'Réinitialiser le mot de passe' }));

    expect(window.confirm).toHaveBeenCalled();
    expect(resetMerchantTemporaryPassword).not.toHaveBeenCalled();
  });

  it('appelle resetMerchantTemporaryPassword et affiche le mot de passe temporaire une seule fois', async () => {
    vi.mocked(resetMerchantTemporaryPassword).mockResolvedValue({
      merchant_id: MERCHANT.id,
      temporary_password: 'TempPass-1234567890',
    });
    renderDrawer();

    fireEvent.click(screen.getByRole('button', { name: 'Réinitialiser le mot de passe' }));

    await waitFor(() => {
      expect(resetMerchantTemporaryPassword).toHaveBeenCalledWith(MERCHANT.id);
    });
    expect(await screen.findByText('TempPass-1234567890')).toBeInTheDocument();
    expect(screen.getByText('Ce mot de passe ne sera plus affiché après fermeture.')).toBeInTheDocument();
  });

  it('copie le mot de passe temporaire', async () => {
    vi.mocked(resetMerchantTemporaryPassword).mockResolvedValue({
      merchant_id: MERCHANT.id,
      temporary_password: 'TempPass-1234567890',
    });
    renderDrawer();

    fireEvent.click(screen.getByRole('button', { name: 'Réinitialiser le mot de passe' }));
    await screen.findByText('TempPass-1234567890');
    fireEvent.click(screen.getByRole('button', { name: 'Copier' }));

    await waitFor(() => {
      expect(navigator.clipboard.writeText).toHaveBeenCalledWith('TempPass-1234567890');
    });
  });

  it('supprime le mot de passe temporaire à la fermeture du drawer', async () => {
    vi.mocked(resetMerchantTemporaryPassword).mockResolvedValue({
      merchant_id: MERCHANT.id,
      temporary_password: 'TempPass-1234567890',
    });
    const onClose = vi.fn();
    const { rerender } = render(
      <MerchantDrawer open onClose={onClose} merchant={MERCHANT} onSaved={vi.fn()} />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Réinitialiser le mot de passe' }));
    expect(await screen.findByText('TempPass-1234567890')).toBeInTheDocument();

    rerender(<MerchantDrawer open={false} onClose={onClose} merchant={MERCHANT} onSaved={vi.fn()} />);
    rerender(<MerchantDrawer open onClose={onClose} merchant={MERCHANT} onSaved={vi.fn()} />);

    expect(screen.queryByText('TempPass-1234567890')).not.toBeInTheDocument();
  });

  it('supprime le mot de passe temporaire quand le marchand change', async () => {
    vi.mocked(resetMerchantTemporaryPassword).mockResolvedValue({
      merchant_id: MERCHANT.id,
      temporary_password: 'TempPass-1234567890',
    });
    const otherMerchant = { ...MERCHANT, id: 'merchant-2', email: 'noura@example.test' };
    const { rerender } = render(
      <MerchantDrawer open onClose={vi.fn()} merchant={MERCHANT} onSaved={vi.fn()} />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Réinitialiser le mot de passe' }));
    expect(await screen.findByText('TempPass-1234567890')).toBeInTheDocument();

    rerender(<MerchantDrawer open onClose={vi.fn()} merchant={otherMerchant} onSaved={vi.fn()} />);

    expect(screen.queryByText('TempPass-1234567890')).not.toBeInTheDocument();
  });

  it('affiche une erreur proprement quand le reset échoue', async () => {
    vi.mocked(resetMerchantTemporaryPassword).mockRejectedValue(new Error('boom'));
    renderDrawer();

    fireEvent.click(screen.getByRole('button', { name: 'Réinitialiser le mot de passe' }));

    expect(await screen.findByText('Impossible de réinitialiser le mot de passe.')).toBeInTheDocument();
    expect(screen.queryByText('TempPass-1234567890')).not.toBeInTheDocument();
  });

  it('supprime le mot de passe temporaire après sauvegarde réussie', async () => {
    vi.mocked(resetMerchantTemporaryPassword).mockResolvedValue({
      merchant_id: MERCHANT.id,
      temporary_password: 'TempPass-1234567890',
    });
    vi.mocked(updateMerchant).mockResolvedValue(MERCHANT);
    vi.mocked(createMerchant).mockResolvedValue(MERCHANT);
    const onSaved = vi.fn();
    render(
      <MerchantDrawer open onClose={vi.fn()} merchant={MERCHANT} onSaved={onSaved} />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Réinitialiser le mot de passe' }));
    expect(await screen.findByText('TempPass-1234567890')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() => {
      expect(onSaved).toHaveBeenCalled();
    });
    expect(screen.queryByText('TempPass-1234567890')).not.toBeInTheDocument();
  });
});
