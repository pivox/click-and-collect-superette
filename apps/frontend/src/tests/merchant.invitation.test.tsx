import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantInvitationPage from '@/app/merchant/invitation/page';
import {
  completeMerchantInvitation,
  verifyMerchantInvitation,
} from '@/lib/services/merchant-invitation.service';

const push = vi.fn();
const searchParams = { value: new URLSearchParams('token=invitation-token') };

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push }),
  useSearchParams: () => searchParams.value,
}));

vi.mock('@/lib/services/merchant-invitation.service', () => ({
  completeMerchantInvitation: vi.fn(),
  verifyMerchantInvitation: vi.fn(),
}));

function apiError(detail: string, status = 400) {
  return { response: { status, data: { detail } } };
}

async function fillAndSubmit(password = 'definitiveSecret456', confirm = password) {
  await act(async () => {
    fireEvent.change(screen.getByLabelText(/^Nouveau mot de passe/i), {
      target: { value: password },
    });
    fireEvent.change(screen.getByLabelText(/Confirmer le nouveau mot de passe/i), {
      target: { value: confirm },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Définir mon mot de passe' }));
  });
}

describe('MerchantInvitationPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    sessionStorage.clear();
    searchParams.value = new URLSearchParams('token=invitation-token');
    vi.mocked(verifyMerchantInvitation).mockResolvedValue({
      status: 'valid',
      expiresAt: '2026-06-26T08:00:00+00:00',
    });
    vi.mocked(completeMerchantInvitation).mockResolvedValue(undefined);
  });

  it('affiche un message de lien manquant sans appeler API', () => {
    searchParams.value = new URLSearchParams();

    render(<MerchantInvitationPage />);

    expect(screen.getByRole('heading', { name: 'Lien invalide' })).toBeInTheDocument();
    expect(screen.getByText(/Ce lien d’invitation est invalide/i)).toBeInTheDocument();
    expect(verifyMerchantInvitation).not.toHaveBeenCalled();
  });

  it('vérifie le token et affiche le formulaire de mot de passe', async () => {
    render(<MerchantInvitationPage />);

    expect(screen.getByText(/Vérification de l’invitation/i)).toBeInTheDocument();

    expect(await screen.findByRole('heading', { name: 'Activer votre espace marchand' }))
      .toBeInTheDocument();
    expect(verifyMerchantInvitation).toHaveBeenCalledWith('invitation-token');
    expect(screen.getByText(/Ce lien expire le/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/^Nouveau mot de passe/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/Confirmer le nouveau mot de passe/i)).toBeInTheDocument();
  });

  it.each([
    ['MERCHANT_INVITATION_TOKEN_EXPIRED', 'Ce lien d’invitation a expiré.'],
    ['MERCHANT_INVITATION_TOKEN_ALREADY_USED', 'Ce lien d’invitation a déjà été utilisé.'],
    ['MERCHANT_INVITATION_TOKEN_REVOKED', 'Ce lien a été remplacé par une invitation plus récente.'],
    ['MERCHANT_INVITATION_TOKEN_INVALID', 'Ce lien d’invitation est invalide.'],
  ])('affiche une erreur contextualisée pour %s', async (detail, message) => {
    vi.mocked(verifyMerchantInvitation).mockRejectedValue(apiError(detail));

    render(<MerchantInvitationPage />);

    expect(await screen.findByRole('alert')).toHaveTextContent(message);
    expect(screen.queryByRole('button', { name: 'Définir mon mot de passe' })).not.toBeInTheDocument();
  });

  it('valide la longueur du mot de passe avant finalisation', async () => {
    render(<MerchantInvitationPage />);

    await screen.findByRole('heading', { name: 'Activer votre espace marchand' });
    await fillAndSubmit('short');

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Le nouveau mot de passe doit contenir au moins 8 caractères.',
    );
    expect(completeMerchantInvitation).not.toHaveBeenCalled();
  });

  it('valide la confirmation avant finalisation', async () => {
    render(<MerchantInvitationPage />);

    await screen.findByRole('heading', { name: 'Activer votre espace marchand' });
    await fillAndSubmit('definitiveSecret456', 'anotherSecret456');

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'La confirmation ne correspond pas au nouveau mot de passe.',
    );
    expect(completeMerchantInvitation).not.toHaveBeenCalled();
  });

  it('finalise l’invitation sans stocker de secret puis redirige vers le login marchand', async () => {
    const setItemSpy = vi.spyOn(Storage.prototype, 'setItem');
    render(<MerchantInvitationPage />);

    await screen.findByRole('heading', { name: 'Activer votre espace marchand' });
    await fillAndSubmit();

    await waitFor(() => {
      expect(completeMerchantInvitation).toHaveBeenCalledWith({
        token: 'invitation-token',
        newPassword: 'definitiveSecret456',
        newPasswordConfirmation: 'definitiveSecret456',
      });
    });
    expect(await screen.findByText('Mot de passe défini.')).toBeInTheDocument();
    expect(push).toHaveBeenCalledWith('/merchant/login');
    expect(setItemSpy).not.toHaveBeenCalledWith(expect.any(String), 'invitation-token');
    expect(setItemSpy).not.toHaveBeenCalledWith(expect.any(String), 'definitiveSecret456');
    expect(localStorage.getItem('invitation-token')).toBeNull();
    expect(sessionStorage.getItem('definitiveSecret456')).toBeNull();
  });

  it('affiche une erreur générique sur échec API inattendu', async () => {
    vi.mocked(completeMerchantInvitation).mockRejectedValue(apiError('SERVER_ERROR', 500));
    render(<MerchantInvitationPage />);

    await screen.findByRole('heading', { name: 'Activer votre espace marchand' });
    await fillAndSubmit();

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Impossible de finaliser l’invitation. Réessayez.',
    );
  });
});
