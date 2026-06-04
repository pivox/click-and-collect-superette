import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantAccountPage from '@/app/merchant/parametres/compte/page';
import {
  changeMerchantPassword,
  updateMerchantAccount,
} from '@/lib/services/merchant-account.service';

const refresh = vi.fn().mockResolvedValue(undefined);

const merchantContext = {
  merchant: {
    user_id: 'user-1',
    email: 'marchand@kadhia.tn',
    name: 'Marchand Test',
    roles: ['ROLE_MERCHANT'],
    store: { id: 'store-1', name: 'Supérette Ezzahra', active: true },
  },
  refresh,
};

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => merchantContext,
}));

vi.mock('@/lib/services/merchant-account.service', () => ({
  updateMerchantAccount: vi.fn(),
  changeMerchantPassword: vi.fn(),
}));

describe('MerchantAccountPage (MS-003)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(updateMerchantAccount).mockResolvedValue({
      userId: 'user-1',
      email: 'marchand@kadhia.tn',
      name: 'Marchand Test',
      roles: ['ROLE_MERCHANT'],
    });
    vi.mocked(changeMerchantPassword).mockResolvedValue(undefined);
  });

  it('prefills account info from the merchant context', () => {
    render(React.createElement(MerchantAccountPage));

    expect(screen.getByLabelText(/Nom affiché/i)).toHaveValue('Marchand Test');
    expect(screen.getByLabelText(/Email/i)).toHaveValue('marchand@kadhia.tn');
  });

  it('saves account info and refreshes the merchant context', async () => {
    render(React.createElement(MerchantAccountPage));

    fireEvent.change(screen.getByLabelText(/Nom affiché/i), { target: { value: 'Nouveau Nom' } });
    fireEvent.click(screen.getByRole('button', { name: /^Enregistrer/i }));

    await waitFor(() => {
      expect(updateMerchantAccount).toHaveBeenCalledWith({
        name: 'Nouveau Nom',
        email: 'marchand@kadhia.tn',
      });
    });
    expect(refresh).toHaveBeenCalled();
    expect(await screen.findByText(/Informations enregistrées/i)).toBeInTheDocument();
  });

  it('rejects a password change when confirmation does not match', async () => {
    render(React.createElement(MerchantAccountPage));

    fireEvent.change(screen.getByLabelText(/Mot de passe actuel/i), { target: { value: 'secret123' } });
    fireEvent.change(screen.getByLabelText(/^Nouveau mot de passe/i), { target: { value: 'brandNewSecret' } });
    fireEvent.change(screen.getByLabelText(/Confirmer le nouveau mot de passe/i), {
      target: { value: 'mismatch' },
    });
    fireEvent.click(screen.getByRole('button', { name: /Changer le mot de passe/i }));

    await waitFor(() => {
      expect(screen.getByText(/La confirmation ne correspond pas/i)).toBeInTheDocument();
    });
    expect(changeMerchantPassword).not.toHaveBeenCalled();
  });

  it('changes the password when inputs are valid', async () => {
    render(React.createElement(MerchantAccountPage));

    fireEvent.change(screen.getByLabelText(/Mot de passe actuel/i), { target: { value: 'secret123' } });
    fireEvent.change(screen.getByLabelText(/^Nouveau mot de passe/i), { target: { value: 'brandNewSecret' } });
    fireEvent.change(screen.getByLabelText(/Confirmer le nouveau mot de passe/i), {
      target: { value: 'brandNewSecret' },
    });
    fireEvent.click(screen.getByRole('button', { name: /Changer le mot de passe/i }));

    await waitFor(() => {
      expect(changeMerchantPassword).toHaveBeenCalledWith({
        currentPassword: 'secret123',
        newPassword: 'brandNewSecret',
      });
    });
    expect(await screen.findByText(/Mot de passe mis à jour/i)).toBeInTheDocument();
  });
});
