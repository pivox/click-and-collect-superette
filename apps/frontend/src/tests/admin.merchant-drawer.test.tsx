import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { MerchantDrawer } from '@/components/admin/marchands/MerchantDrawer';
import {
  createMerchantOnboarding,
  createMerchant,
  resendMerchantInvitation,
  resetMerchantTemporaryPassword,
  updateMerchant,
} from '@/lib/services/admin/merchants.service';
import type { Merchant } from '@/lib/types/admin/merchants.types';
import { listProductGroups } from '@/lib/services/admin/product-groups.service';
import type { ProductGroup } from '@/lib/types/admin/referentiel.types';

vi.mock('@/components/admin/marchands/MerchantCrmSection', () => ({
  MerchantCrmSection: () => <div data-testid="merchant-crm-section" />,
}));

vi.mock('@/lib/services/admin/merchants.service', () => ({
  createMerchantOnboarding: vi.fn(),
  createMerchant: vi.fn(),
  resendMerchantInvitation: vi.fn(),
  updateMerchant: vi.fn(),
  resetMerchantTemporaryPassword: vi.fn(),
}));

vi.mock('@/lib/services/admin/product-groups.service', () => ({
  listProductGroups: vi.fn(),
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

const GROUPS: ProductGroup[] = [
  {
    id: 'group-1',
    name_fr: 'Premières nécessités',
    name_ar: null,
    slug: 'premieres-necessites',
    description_fr: null,
    description_ar: null,
    market_country: 'TN',
    status: 'published',
    visibility: 'merchant',
    icon: null,
    sort_order: 1,
    items_count: 12,
    created_at: '2026-06-01T10:00:00+01:00',
    updated_at: '2026-06-01T10:00:00+01:00',
  },
  {
    id: 'group-2',
    name_fr: 'Petit déjeuner',
    name_ar: null,
    slug: 'petit-dejeuner',
    description_fr: null,
    description_ar: null,
    market_country: 'TN',
    status: 'published',
    visibility: 'merchant',
    icon: null,
    sort_order: 2,
    items_count: 8,
    created_at: '2026-06-01T10:00:00+01:00',
    updated_at: '2026-06-01T10:00:00+01:00',
  },
];

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

function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((innerResolve) => {
    resolve = innerResolve;
  });

  return { promise, resolve };
}

describe('MerchantDrawer', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.stubGlobal('confirm', vi.fn(() => true));
    vi.mocked(listProductGroups).mockResolvedValue({
      id: 'admin-product-groups',
      items: GROUPS,
      page: 1,
      limit: 50,
      total: 2,
    });
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: { writeText: vi.fn().mockResolvedValue(undefined) },
    });
  });

  it('affiche les sections onboarding en création et charge les groupements publiés', async () => {
    renderDrawer(null);

    expect(screen.getByText('Informations marchand')).toBeInTheDocument();
    expect(screen.getByText('Informations supérette')).toBeInTheDocument();
    expect(screen.getByText('Première connexion')).toBeInTheDocument();
    expect(screen.getByText('Préchargement catalogue')).toBeInTheDocument();

    await waitFor(() => {
      expect(listProductGroups).toHaveBeenCalledWith({ status: 'published', limit: 50, page: 1 });
    });
    expect(await screen.findByLabelText('Premières nécessités')).toBeInTheDocument();
    expect(screen.getByLabelText('Petit déjeuner')).toBeInTheDocument();
  });

  it('charge les groupements marchand publiés au-delà de la première page admin', async () => {
    const adminOnlyGroups = Array.from({ length: 50 }, (_, index): ProductGroup => ({
      ...GROUPS[0],
      id: `admin-only-${index}`,
      name_fr: `Admin only ${index}`,
      visibility: 'admin_only',
    }));
    vi.mocked(listProductGroups)
      .mockResolvedValueOnce({
        id: 'admin-product-groups',
        items: adminOnlyGroups,
        page: 1,
        limit: 50,
        total: 51,
      })
      .mockResolvedValueOnce({
        id: 'admin-product-groups',
        items: [{ ...GROUPS[1], id: 'group-page-2', name_fr: 'Page suivante marchand' }],
        page: 2,
        limit: 50,
        total: 51,
      });

    renderDrawer(null);

    expect(await screen.findByLabelText('Page suivante marchand')).toBeInTheDocument();
    expect(screen.queryByLabelText('Admin only 0')).not.toBeInTheDocument();
    expect(listProductGroups).toHaveBeenNthCalledWith(1, { status: 'published', limit: 50, page: 1 });
    expect(listProductGroups).toHaveBeenNthCalledWith(2, { status: 'published', limit: 50, page: 2 });
  });

  it('soumet l’onboarding marchand avec supérette, groupements et affiche le résumé one-shot', async () => {
    vi.mocked(createMerchantOnboarding).mockResolvedValue({
      id: 'merchant-1',
      merchant: MERCHANT,
      shop: {
        id: 'shop-1',
        name: 'Supérette El Hana',
        slug: 'superette-el-hana',
        address: '12 rue de Tunis',
        city: 'Ariana',
        phone: '+21671111222',
        is_active: true,
        qr_code_token: 'qr-token',
        created_at: '2026-06-01T10:00:00+01:00',
        owner: {
          id: MERCHANT.id,
          email: MERCHANT.email,
        },
        products_count: 10,
        archived_at: null,
      },
      first_login: {
        mode: 'temporary_password',
        temporary_password: 'TempPass-1234567890',
      },
      catalog_preload: {
        added_count: 10,
        already_existing_count: 2,
        ignored_count: 1,
        errors: [],
      },
    });
    renderDrawer(null);

    fireEvent.change(screen.getByLabelText('Prénom *'), { target: { value: 'Ali' } });
    fireEvent.change(screen.getByLabelText('Nom *'), { target: { value: 'Ben Salah' } });
    fireEvent.change(screen.getByLabelText('Email *'), { target: { value: 'ali@example.test' } });
    fireEvent.change(screen.getByLabelText('Nom supérette *'), { target: { value: 'Supérette El Hana' } });
    fireEvent.change(screen.getByLabelText('Adresse supérette'), { target: { value: '12 rue de Tunis' } });
    fireEvent.change(screen.getByLabelText('Ville'), { target: { value: 'Ariana' } });
    fireEvent.click(await screen.findByLabelText('Premières nécessités'));
    fireEvent.click(screen.getByLabelText('Petit déjeuner'));

    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() => {
      expect(createMerchantOnboarding).toHaveBeenCalledWith({
        merchant: {
          email: 'ali@example.test',
          first_name: 'Ali',
          last_name: 'Ben Salah',
          phone: undefined,
        },
        shop: {
          name: 'Supérette El Hana',
          address: '12 rue de Tunis',
          city: 'Ariana',
          phone: undefined,
        },
        first_login_mode: 'temporary_password',
        product_group_ids: ['group-1', 'group-2'],
      });
    });
    expect(await screen.findByText('TempPass-1234567890')).toBeInTheDocument();
    expect(screen.getByText('Ce mot de passe ne sera plus affiché après fermeture.')).toBeInTheDocument();
    expect(screen.getByText('10 ajoutés')).toBeInTheDocument();
    expect(screen.getByText('2 déjà présents')).toBeInTheDocument();
    expect(screen.getByText('1 ignoré')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Copier' }));
    await waitFor(() => {
      expect(navigator.clipboard.writeText).toHaveBeenCalledWith('TempPass-1234567890');
    });
  });

  it('soumet l’onboarding marchand en mode invitation email sans afficher de token', async () => {
    vi.mocked(createMerchantOnboarding).mockResolvedValue({
      id: 'merchant-1',
      merchant: MERCHANT,
      shop: {
        id: 'shop-1',
        name: 'Supérette Invitation',
        slug: 'superette-invitation',
        address: null,
        city: 'Tunis',
        phone: null,
        is_active: true,
        qr_code_token: 'qr-token',
        created_at: '2026-06-01T10:00:00+01:00',
        owner: {
          id: MERCHANT.id,
          email: MERCHANT.email,
        },
        products_count: 0,
        archived_at: null,
      },
      first_login: {
        mode: 'email_invitation',
        temporary_password: null,
        invitation_status: 'sent',
        expires_at: '2026-06-08T10:00:00+01:00',
      },
      catalog_preload: {
        added_count: 0,
        already_existing_count: 0,
        ignored_count: 0,
        errors: [],
      },
    });
    renderDrawer(null);

    fireEvent.change(screen.getByLabelText('Prénom *'), { target: { value: 'Rania' } });
    fireEvent.change(screen.getByLabelText('Nom *'), { target: { value: 'Mansour' } });
    fireEvent.change(screen.getByLabelText('Email *'), { target: { value: 'rania@example.test' } });
    fireEvent.change(screen.getByLabelText('Nom supérette *'), { target: { value: 'Supérette Invitation' } });
    fireEvent.click(screen.getByLabelText('Invitation email'));

    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() => {
      expect(createMerchantOnboarding).toHaveBeenCalledWith(expect.objectContaining({
        first_login_mode: 'email_invitation',
      }));
    });
    expect(await screen.findByText('Invitation email envoyée')).toBeInTheDocument();
    expect(screen.queryByText('Mot de passe temporaire')).not.toBeInTheDocument();
    expect(screen.queryByText(/token/i)).not.toBeInTheDocument();
  });

  it('affiche un échec explicite quand l’invitation email onboarding n’est pas envoyée', async () => {
    vi.mocked(createMerchantOnboarding).mockResolvedValue({
      id: 'merchant-1',
      merchant: MERCHANT,
      shop: {
        id: 'shop-1',
        name: 'Supérette Invitation',
        slug: 'superette-invitation',
        address: null,
        city: null,
        phone: null,
        is_active: true,
        qr_code_token: 'qr-token',
        created_at: '2026-06-01T10:00:00+01:00',
        owner: {
          id: MERCHANT.id,
          email: MERCHANT.email,
        },
        products_count: 0,
        archived_at: null,
      },
      first_login: {
        mode: 'email_invitation',
        temporary_password: null,
        invitation_status: 'delivery_failed',
        expires_at: '2026-06-08T10:00:00+01:00',
      },
      catalog_preload: {
        added_count: 0,
        already_existing_count: 0,
        ignored_count: 0,
        errors: [],
      },
    });
    vi.mocked(resendMerchantInvitation).mockResolvedValue({
      merchant_id: MERCHANT.id,
      status: 'sent',
      expires_at: '2026-06-08T10:05:00+01:00',
    });
    renderDrawer(null);

    fireEvent.change(screen.getByLabelText('Prénom *'), { target: { value: 'Maha' } });
    fireEvent.change(screen.getByLabelText('Nom *'), { target: { value: 'Bouzid' } });
    fireEvent.change(screen.getByLabelText('Email *'), { target: { value: 'maha@example.test' } });
    fireEvent.change(screen.getByLabelText('Nom supérette *'), { target: { value: 'Supérette Invitation' } });
    fireEvent.click(screen.getByLabelText('Invitation email'));

    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    expect(await screen.findByText('Invitation email non envoyée')).toBeInTheDocument();
    expect(screen.queryByText('Invitation email envoyée')).not.toBeInTheDocument();
    expect(screen.queryByText('Mot de passe temporaire')).not.toBeInTheDocument();
    expect(screen.queryByText(/token/i)).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Renvoyer l’invitation' }));

    await waitFor(() => {
      expect(resendMerchantInvitation).toHaveBeenCalledWith(MERCHANT.id);
    });
    expect(await screen.findByText('Invitation email envoyée')).toBeInTheDocument();
    expect(screen.queryByText('Invitation email non envoyée')).not.toBeInTheDocument();
  });

  it('efface le mot de passe temporaire onboarding à la fermeture', async () => {
    vi.mocked(createMerchantOnboarding).mockResolvedValue({
      id: 'merchant-1',
      merchant: MERCHANT,
      shop: {
        id: 'shop-1',
        name: 'Supérette El Hana',
        slug: 'superette-el-hana',
        address: null,
        city: null,
        phone: null,
        is_active: true,
        qr_code_token: 'qr-token',
        created_at: '2026-06-01T10:00:00+01:00',
        owner: {
          id: MERCHANT.id,
          email: MERCHANT.email,
        },
        products_count: 0,
        archived_at: null,
      },
      first_login: {
        mode: 'temporary_password',
        temporary_password: 'TempPass-1234567890',
      },
      catalog_preload: {
        added_count: 0,
        already_existing_count: 0,
        ignored_count: 0,
        errors: [],
      },
    });
    const onClose = vi.fn();
    const { rerender } = render(
      <MerchantDrawer open onClose={onClose} merchant={null} onSaved={vi.fn()} />,
    );
    await screen.findByLabelText('Premières nécessités');

    fireEvent.change(screen.getByLabelText('Prénom *'), { target: { value: 'Ali' } });
    fireEvent.change(screen.getByLabelText('Nom *'), { target: { value: 'Ben Salah' } });
    fireEvent.change(screen.getByLabelText('Email *'), { target: { value: 'ali@example.test' } });
    fireEvent.change(screen.getByLabelText('Nom supérette *'), { target: { value: 'Supérette El Hana' } });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    expect(await screen.findByText('TempPass-1234567890')).toBeInTheDocument();

    rerender(<MerchantDrawer open={false} onClose={onClose} merchant={null} onSaved={vi.fn()} />);
    rerender(<MerchantDrawer open onClose={onClose} merchant={null} onSaved={vi.fn()} />);
    await screen.findByLabelText('Premières nécessités');

    expect(screen.queryByText('TempPass-1234567890')).not.toBeInTheDocument();
  });

  it('affiche le bouton reset pour un marchand existant', () => {
    renderDrawer();

    expect(screen.getByRole('button', { name: 'Réinitialiser le mot de passe' })).toBeInTheDocument();
  });

  it('n’affiche pas le reset en création', async () => {
    renderDrawer(null);
    await screen.findByLabelText('Premières nécessités');

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

  it('ignore une réponse de reset obsolète après changement de marchand', async () => {
    const reset = deferred<{ merchant_id: string; temporary_password: string }>();
    vi.mocked(resetMerchantTemporaryPassword).mockReturnValue(reset.promise);
    const otherMerchant = { ...MERCHANT, id: 'merchant-2', email: 'noura@example.test' };
    const { rerender } = render(
      <MerchantDrawer open onClose={vi.fn()} merchant={MERCHANT} onSaved={vi.fn()} />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Réinitialiser le mot de passe' }));
    rerender(<MerchantDrawer open onClose={vi.fn()} merchant={otherMerchant} onSaved={vi.fn()} />);

    await act(async () => {
      reset.resolve({
        merchant_id: MERCHANT.id,
        temporary_password: 'TempPass-merchant-a',
      });
    });

    expect(screen.getByText('noura@example.test')).toBeInTheDocument();
    expect(screen.queryByText('TempPass-merchant-a')).not.toBeInTheDocument();
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

  it('conserve le mot de passe temporaire quand la sauvegarde échoue', async () => {
    vi.mocked(resetMerchantTemporaryPassword).mockResolvedValue({
      merchant_id: MERCHANT.id,
      temporary_password: 'TempPass-1234567890',
    });
    vi.mocked(updateMerchant).mockRejectedValue(new Error('boom'));
    render(
      <MerchantDrawer open onClose={vi.fn()} merchant={MERCHANT} onSaved={vi.fn()} />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Réinitialiser le mot de passe' }));
    expect(await screen.findByText('TempPass-1234567890')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    expect(await screen.findByText('Une erreur est survenue. Réessayez.')).toBeInTheDocument();
    expect(screen.getByText('TempPass-1234567890')).toBeInTheDocument();
  });
});
