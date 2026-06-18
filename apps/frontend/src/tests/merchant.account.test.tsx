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
    first_name: 'Ali',
    last_name: 'Ben Salah',
    phone: '+21620111222',
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
    localStorage.clear();
    sessionStorage.clear();
    vi.mocked(updateMerchantAccount).mockResolvedValue({
      userId: 'user-1',
      email: 'marchand@kadhia.tn',
      name: 'Marchand Test',
      firstName: 'Ali',
      lastName: 'Ben Salah',
      phone: '+21620111222',
    });
    vi.mocked(changeMerchantPassword).mockResolvedValue(undefined);
  });

  it('affiche la page compte marchand sans champ sensible', () => {
    render(React.createElement(MerchantAccountPage));

    expect(screen.getByRole('heading', { name: /Mon compte marchand/i })).toBeInTheDocument();
    expect(screen.queryByText('ROLE_MERCHANT')).not.toBeInTheDocument();
    expect(screen.queryByText(/passwordHash|resetToken|invitationToken|temporaryPassword|token|statut/i))
      .not.toBeInTheDocument();
  });

  it('préremplit le formulaire profil depuis le contexte marchand', () => {
    render(React.createElement(MerchantAccountPage));

    expect(screen.getByLabelText(/Prénom/i)).toHaveValue('Ali');
    expect(screen.getByLabelText(/^Nom \*/i)).toHaveValue('Ben Salah');
    expect(screen.getByLabelText(/Téléphone/i)).toHaveValue('+21620111222');
    expect(screen.getByLabelText(/Email/i)).toHaveValue('marchand@kadhia.tn');
    expect(screen.getByLabelText(/Email/i)).toHaveProperty('readOnly', true);
  });

  it('sauvegarde uniquement les champs profil autorisés et rafraîchit le contexte', async () => {
    render(React.createElement(MerchantAccountPage));

    fireEvent.change(screen.getByLabelText(/Prénom/i), { target: { value: 'Noura' } });
    fireEvent.change(screen.getByLabelText(/^Nom \*/i), { target: { value: 'Kacem' } });
    fireEvent.change(screen.getByLabelText(/Téléphone/i), { target: { value: '+21622999888' } });
    fireEvent.click(screen.getByRole('button', { name: /^Enregistrer/i }));

    await waitFor(() => {
      expect(updateMerchantAccount).toHaveBeenCalledWith({
        firstName: 'Noura',
        lastName: 'Kacem',
        phone: '+21622999888',
      });
    });
    expect(refresh).toHaveBeenCalled();
    expect(await screen.findByText(/Informations enregistrées/i)).toBeInTheDocument();
  });

  it('affiche une erreur API profil et empêche le double submit', async () => {
    let rejectRequest!: (error: unknown) => void;
    vi.mocked(updateMerchantAccount).mockImplementation(
      () => new Promise((_, reject) => {
        rejectRequest = reject;
      }),
    );

    render(React.createElement(MerchantAccountPage));

    fireEvent.click(screen.getByRole('button', { name: /^Enregistrer/i }));
    fireEvent.click(screen.getByRole('button', { name: /Enregistrement/i }));

    expect(updateMerchantAccount).toHaveBeenCalledTimes(1);
    expect(screen.getByRole('button', { name: /Enregistrement/i })).toBeDisabled();

    rejectRequest({ response: { status: 422 } });

    expect(await screen.findByRole('alert')).toHaveTextContent(
      "Impossible d'enregistrer. Vérifiez les valeurs saisies.",
    );
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

  it('affiche une erreur quand le mot de passe actuel est refusé', async () => {
    vi.mocked(changeMerchantPassword).mockRejectedValue({ response: { status: 422 } });
    render(React.createElement(MerchantAccountPage));

    fireEvent.change(screen.getByLabelText(/Mot de passe actuel/i), { target: { value: 'secret123' } });
    fireEvent.change(screen.getByLabelText(/^Nouveau mot de passe/i), { target: { value: 'brandNewSecret' } });
    fireEvent.change(screen.getByLabelText(/Confirmer le nouveau mot de passe/i), {
      target: { value: 'brandNewSecret' },
    });
    fireEvent.click(screen.getByRole('button', { name: /Changer le mot de passe/i }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Mot de passe actuel incorrect.');
  });

  it('change le mot de passe, vide les champs et ne stocke pas les mots de passe', async () => {
    const setItemSpy = vi.spyOn(Storage.prototype, 'setItem');

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
    expect(screen.getByLabelText(/Mot de passe actuel/i)).toHaveValue('');
    expect(screen.getByLabelText(/^Nouveau mot de passe/i)).toHaveValue('');
    expect(screen.getByLabelText(/Confirmer le nouveau mot de passe/i)).toHaveValue('');
    expect(setItemSpy).not.toHaveBeenCalledWith(expect.any(String), 'secret123');
    expect(setItemSpy).not.toHaveBeenCalledWith(expect.any(String), 'brandNewSecret');
    expect(localStorage.getItem('secret123')).toBeNull();
    expect(sessionStorage.getItem('brandNewSecret')).toBeNull();
  });
});
