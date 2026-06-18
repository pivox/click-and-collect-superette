import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantFirstLoginPage from '@/app/merchant/premiere-connexion/page';
import { changeMerchantFirstLoginPassword } from '@/lib/services/merchant-account.service';

const logout = vi.fn();
const refresh = vi.fn().mockResolvedValue(undefined);
const push = vi.fn();

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push }),
}));

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => ({
    logout,
    refresh,
    merchant: {
      user_id: 'user-1',
      email: 'marchand@kadhia.tn',
      name: 'Ali Ben Salah',
      first_name: 'Ali',
      last_name: 'Ben Salah',
      phone: '+21620111222',
      store: { id: 'store-1', name: 'Supérette Ezzahra', active: true },
      onboarding_completed: true,
      password_change_required: true,
    },
  }),
}));

vi.mock('@/lib/services/merchant-account.service', () => ({
  changeMerchantFirstLoginPassword: vi.fn(),
}));

describe('MerchantFirstLoginPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    sessionStorage.clear();
    vi.mocked(changeMerchantFirstLoginPassword).mockResolvedValue(undefined);
  });

  it('affiche les champs attendus et permet la déconnexion', () => {
    render(React.createElement(MerchantFirstLoginPage));

    expect(screen.getByRole('heading', { name: 'Définir votre mot de passe' })).toBeInTheDocument();
    expect(screen.getByText(/mot de passe provisoire doit être remplacé/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/Mot de passe provisoire actuel/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/^Nouveau mot de passe/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/Confirmer le nouveau mot de passe/i)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Se déconnecter' }));
    expect(logout).toHaveBeenCalled();
  });

  it('valide la confirmation avant appel API', async () => {
    render(React.createElement(MerchantFirstLoginPage));

    fireEvent.change(screen.getByLabelText(/Mot de passe provisoire actuel/i), {
      target: { value: 'temporarySecret123' },
    });
    fireEvent.change(screen.getByLabelText(/^Nouveau mot de passe/i), {
      target: { value: 'definitiveSecret456' },
    });
    fireEvent.change(screen.getByLabelText(/Confirmer le nouveau mot de passe/i), {
      target: { value: 'mismatchSecret456' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Définir mon mot de passe' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'La confirmation ne correspond pas au nouveau mot de passe.',
    );
    expect(changeMerchantFirstLoginPassword).not.toHaveBeenCalled();
  });

  it('affiche une erreur API', async () => {
    vi.mocked(changeMerchantFirstLoginPassword).mockRejectedValue({ response: { status: 422 } });
    render(React.createElement(MerchantFirstLoginPage));

    fireEvent.change(screen.getByLabelText(/Mot de passe provisoire actuel/i), {
      target: { value: 'temporarySecret123' },
    });
    fireEvent.change(screen.getByLabelText(/^Nouveau mot de passe/i), {
      target: { value: 'definitiveSecret456' },
    });
    fireEvent.change(screen.getByLabelText(/Confirmer le nouveau mot de passe/i), {
      target: { value: 'definitiveSecret456' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Définir mon mot de passe' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Mot de passe provisoire incorrect ou nouveau mot de passe invalide.',
    );
  });

  it('appelle endpoint, vide les champs, rafraîchit puis redirige dashboard sans stocker les mots de passe', async () => {
    const setItemSpy = vi.spyOn(Storage.prototype, 'setItem');
    render(React.createElement(MerchantFirstLoginPage));

    fireEvent.change(screen.getByLabelText(/Mot de passe provisoire actuel/i), {
      target: { value: 'temporarySecret123' },
    });
    fireEvent.change(screen.getByLabelText(/^Nouveau mot de passe/i), {
      target: { value: 'definitiveSecret456' },
    });
    fireEvent.change(screen.getByLabelText(/Confirmer le nouveau mot de passe/i), {
      target: { value: 'definitiveSecret456' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Définir mon mot de passe' }));

    await waitFor(() => {
      expect(changeMerchantFirstLoginPassword).toHaveBeenCalledWith({
        currentPassword: 'temporarySecret123',
        newPassword: 'definitiveSecret456',
        newPasswordConfirmation: 'definitiveSecret456',
      });
    });
    expect(await screen.findByText('Mot de passe défini.')).toBeInTheDocument();
    expect(screen.getByLabelText(/Mot de passe provisoire actuel/i)).toHaveValue('');
    expect(screen.getByLabelText(/^Nouveau mot de passe/i)).toHaveValue('');
    expect(screen.getByLabelText(/Confirmer le nouveau mot de passe/i)).toHaveValue('');
    expect(refresh).toHaveBeenCalled();
    expect(push).toHaveBeenCalledWith('/merchant');
    expect(setItemSpy).not.toHaveBeenCalledWith(expect.any(String), 'temporarySecret123');
    expect(setItemSpy).not.toHaveBeenCalledWith(expect.any(String), 'definitiveSecret456');
    expect(localStorage.getItem('temporarySecret123')).toBeNull();
    expect(sessionStorage.getItem('definitiveSecret456')).toBeNull();
  });
});
