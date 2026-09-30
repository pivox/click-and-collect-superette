import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantTeamPage from '@/app/merchant/parametres/equipe/page';
import { MerchantLocaleProvider } from '@/lib/i18n/MerchantLocaleContext';
import {
  getMerchantTeam,
  inviteMerchantTeamAccount,
  resendMerchantTeamInvitation,
  revokeMerchantTeamAccount,
  type MerchantTeam,
  type MerchantTeamAccount,
} from '@/lib/services/merchant-team.service';

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => ({
    merchant: {
      user_id: 'user-primary',
      email: 'primary@example.test',
      name: 'Ali Ben Salah',
      store: { id: 'store-1', name: 'Supérette Ezzahra', active: true },
    },
    refresh: vi.fn(),
  }),
}));

vi.mock('@/lib/services/merchant-team.service', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/services/merchant-team.service')>();
  return {
    ...actual,
    getMerchantTeam: vi.fn(),
    inviteMerchantTeamAccount: vi.fn(),
    resendMerchantTeamInvitation: vi.fn(),
    revokeMerchantTeamAccount: vi.fn(),
  };
});

function account(overrides: Partial<MerchantTeamAccount>): MerchantTeamAccount {
  return {
    accountId: 'user-x',
    firstName: 'Ahmed',
    lastName: 'Ben Ali',
    email: 'ahmed@example.test',
    phone: null,
    status: 'active',
    isPrimary: false,
    invitedAt: null,
    acceptedAt: null,
    revokedAt: null,
    ...overrides,
  };
}

function team(items: MerchantTeamAccount[], activeOrInvitedCount = items.length): MerchantTeam {
  return {
    storeId: 'store-1',
    organizationId: 'org-1',
    limit: 10,
    activeOrInvitedCount,
    items,
  };
}

function renderPage() {
  return render(
    <MerchantLocaleProvider>
      <MerchantTeamPage />
    </MerchantLocaleProvider>,
  );
}

describe('MerchantTeamPage (MERCHANT-TEAM-006)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    vi.mocked(getMerchantTeam).mockResolvedValue(
      team([
        account({
          accountId: 'user-primary',
          firstName: 'Ali',
          lastName: 'Ben Salah',
          email: 'primary@example.test',
          isPrimary: true,
        }),
        account({ accountId: 'user-2', status: 'invited', invitedAt: '2026-09-30T08:00:00+00:00' }),
      ]),
    );
  });

  it('affiche la liste, le quota et le badge principal pour le compte principal', async () => {
    renderPage();

    expect(await screen.findByText('Ali Ben Salah')).toBeInTheDocument();
    expect(screen.getByText('2 / 10 comptes')).toBeInTheDocument();
    expect(screen.getByText('Principal')).toBeInTheDocument();
    expect(screen.getByText('Invitation en attente')).toBeInTheDocument();
    // Invited account gets a resend action; the primary account never gets revoke.
    expect(screen.getByRole('button', { name: 'Renvoyer l’invitation' })).toBeInTheDocument();
    expect(screen.getAllByRole('button', { name: 'Révoquer l’accès' })).toHaveLength(1);
  });

  it('invite une personne avec le bon payload et affiche le succès', async () => {
    vi.mocked(inviteMerchantTeamAccount).mockResolvedValue(
      account({ accountId: 'user-3', email: 'noura@example.test', status: 'invited', invitationStatus: 'sent' }),
    );

    renderPage();
    await screen.findByText('Ali Ben Salah');

    fireEvent.change(screen.getByLabelText('Prénom'), { target: { value: 'Noura' } });
    fireEvent.change(screen.getByLabelText('Nom'), { target: { value: 'Trabelsi' } });
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'Noura@Example.Test' } });
    fireEvent.click(screen.getByRole('button', { name: 'Envoyer l’invitation' }));

    await waitFor(() =>
      expect(inviteMerchantTeamAccount).toHaveBeenCalledWith('store-1', {
        firstName: 'Noura',
        lastName: 'Trabelsi',
        email: 'noura@example.test',
        phone: null,
      }),
    );
    expect(await screen.findByText(/Invitation envoyée/)).toBeInTheDocument();
  });

  it('bloque une invitation incomplète sans appel API', async () => {
    renderPage();
    await screen.findByText('Ali Ben Salah');

    fireEvent.click(screen.getByRole('button', { name: 'Envoyer l’invitation' }));

    expect(await screen.findByText('Prénom, nom et email sont obligatoires.')).toBeInTheDocument();
    expect(inviteMerchantTeamAccount).not.toHaveBeenCalled();
  });

  it('affiche les erreurs métier email utilisé et quota atteint', async () => {
    vi.mocked(inviteMerchantTeamAccount).mockRejectedValueOnce({
      response: { status: 422, data: { detail: 'MERCHANT_ACCOUNT_EMAIL_ALREADY_USED' } },
    });

    renderPage();
    await screen.findByText('Ali Ben Salah');

    fireEvent.change(screen.getByLabelText('Prénom'), { target: { value: 'Noura' } });
    fireEvent.change(screen.getByLabelText('Nom'), { target: { value: 'Trabelsi' } });
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'noura@example.test' } });
    fireEvent.click(screen.getByRole('button', { name: 'Envoyer l’invitation' }));

    expect(
      await screen.findByText('Cet email est déjà utilisé par un autre compte.'),
    ).toBeInTheDocument();

    vi.mocked(inviteMerchantTeamAccount).mockRejectedValueOnce({
      response: { status: 422, data: { detail: 'MERCHANT_ACCOUNT_LIMIT_REACHED' } },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Envoyer l’invitation' }));
    expect(
      await screen.findByText('Limite de comptes atteinte pour votre organisation.'),
    ).toBeInTheDocument();
  });

  it('signale un échec de livraison email et propose le renvoi', async () => {
    vi.mocked(inviteMerchantTeamAccount).mockResolvedValue(
      account({ accountId: 'user-3', status: 'invited', invitationStatus: 'delivery_failed' }),
    );

    renderPage();
    await screen.findByText('Ali Ben Salah');

    fireEvent.change(screen.getByLabelText('Prénom'), { target: { value: 'Noura' } });
    fireEvent.change(screen.getByLabelText('Nom'), { target: { value: 'Trabelsi' } });
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'noura@example.test' } });
    fireEvent.click(screen.getByRole('button', { name: 'Envoyer l’invitation' }));

    expect(await screen.findByText(/l’email n’a pas pu être envoyé/)).toBeInTheDocument();
  });

  it('renvoie une invitation en attente', async () => {
    vi.mocked(resendMerchantTeamInvitation).mockResolvedValue(
      account({ accountId: 'user-2', status: 'invited', invitationStatus: 'sent' }),
    );

    renderPage();
    await screen.findByText('Ali Ben Salah');

    fireEvent.click(screen.getByRole('button', { name: 'Renvoyer l’invitation' }));

    await waitFor(() =>
      expect(resendMerchantTeamInvitation).toHaveBeenCalledWith('store-1', 'user-2'),
    );
    expect(await screen.findByText('Invitation renvoyée.')).toBeInTheDocument();
  });

  it('révoque après une confirmation explicite montrant le compte ciblé', async () => {
    vi.mocked(getMerchantTeam).mockResolvedValue(
      team([
        account({
          accountId: 'user-primary',
          firstName: 'Ali',
          lastName: 'Ben Salah',
          email: 'primary@example.test',
          isPrimary: true,
        }),
        account({ accountId: 'user-x' }),
      ]),
    );
    vi.mocked(revokeMerchantTeamAccount).mockResolvedValue(undefined);

    renderPage();
    await screen.findByText('Ali Ben Salah');

    fireEvent.click(screen.getByRole('button', { name: 'Révoquer l’accès' }));

    const dialog = await screen.findByRole('alertdialog');
    expect(dialog).toHaveTextContent('Révoquer l’accès de Ahmed Ben Ali ?');
    expect(dialog).toHaveTextContent('ahmed@example.test');
    expect(dialog).toHaveTextContent('L’accès sera retiré immédiatement');
    expect(dialog).toHaveTextContent('L’historique de ses actions est conservé.');
    expect(revokeMerchantTeamAccount).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole('button', { name: 'Révoquer définitivement' }));

    await waitFor(() =>
      expect(revokeMerchantTeamAccount).toHaveBeenCalledWith('store-1', 'user-x'),
    );
  });

  it('annule la confirmation de révocation sans appel API', async () => {
    renderPage();
    await screen.findByText('Ali Ben Salah');

    fireEvent.click(screen.getByRole('button', { name: 'Révoquer l’accès' }));
    await screen.findByRole('alertdialog');
    fireEvent.click(screen.getByRole('button', { name: 'Annuler' }));

    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument();
    expect(revokeMerchantTeamAccount).not.toHaveBeenCalled();
  });

  it('affiche la vue membre sans action de gestion pour un compte secondaire', async () => {
    vi.mocked(getMerchantTeam).mockRejectedValue({
      response: { status: 403, data: { detail: 'MERCHANT_TEAM_MANAGEMENT_FORBIDDEN' } },
    });

    renderPage();

    expect(await screen.findByText('Compte membre de l’équipe')).toBeInTheDocument();
    expect(screen.getByText(/Supérette Ezzahra/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Envoyer l’invitation' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Révoquer l’accès' })).not.toBeInTheDocument();
  });

  it('affiche une erreur récupérable avec bouton réessayer', async () => {
    vi.mocked(getMerchantTeam).mockRejectedValueOnce(new Error('network'));

    renderPage();

    expect(await screen.findByRole('alert')).toHaveTextContent(/Impossible de charger l’équipe/);
    fireEvent.click(screen.getByRole('button', { name: 'Réessayer' }));
    expect(await screen.findByText('Ali Ben Salah')).toBeInTheDocument();
  });

  it("s'affiche en arabe avec direction RTL", async () => {
    localStorage.setItem('merchant:lang', 'ar');

    const { container } = renderPage();

    expect(await screen.findByText('الفريق')).toBeInTheDocument();
    expect(container.querySelector('[dir="rtl"]')).not.toBeNull();
    expect(screen.getByRole('button', { name: 'إرسال الدعوة' })).toBeInTheDocument();
  });
});
