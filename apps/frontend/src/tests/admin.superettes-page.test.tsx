import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SuperettesPage from '@/app/admin/superettes/page';
import {
  deactivateStore,
  getStoreActivationChecklist,
  listStores,
} from '@/lib/services/admin/stores.service';

vi.mock('@/components/admin/superettes/StoreDrawer', () => ({
  StoreDrawer: () => null,
}));

vi.mock('@/lib/services/admin/stores.service', () => ({
  listStores: vi.fn(),
  archiveStore: vi.fn(),
  activateStore: vi.fn(),
  deactivateStore: vi.fn(),
  getStoreActivationChecklist: vi.fn(),
}));

const STORE = {
  id: '11111111-1111-4111-8111-111111111111',
  name: 'Supérette Active',
  slug: 'superette-active',
  city: 'Tunis',
  is_active: true,
  qr_code_token: 'qr-active',
  created_at: '2026-06-01T10:00:00+01:00',
  owner: { id: 'merchant-1', email: 'merchant@example.test' },
  products_count: 6,
  archived_at: null,
};

const READY_CHECKLIST = {
  store_id: STORE.id,
  store_name: STORE.name,
  ready: true,
  minimum_catalog_products: 5,
  required_completed_count: 8,
  required_total_count: 8,
  steps: [
    {
      key: 'merchant_active',
      label: 'Marchand actif',
      completed: true,
      required: true,
    },
    {
      key: 'catalog_minimum',
      label: 'Catalogue minimum',
      completed: true,
      required: true,
      current_value: 6,
      target_value: 5,
    },
    {
      key: 'store_picture',
      label: 'Photo vitrine',
      completed: true,
      required: false,
    },
  ],
};

const INCOMPLETE_CHECKLIST = {
  ...READY_CHECKLIST,
  ready: false,
  required_completed_count: 7,
  steps: [
    {
      key: 'opening_hours',
      label: 'Horaires renseignés',
      completed: false,
      required: true,
    },
    {
      key: 'catalog_minimum',
      label: 'Catalogue minimum',
      completed: false,
      required: true,
      current_value: 3,
      target_value: 5,
    },
  ],
};

describe('SuperettesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(listStores).mockResolvedValue({
      id: 'admin-stores',
      items: [{ ...STORE, activation_checklist: READY_CHECKLIST }],
      page: 1,
      limit: 20,
      total: 1,
    });
    vi.mocked(deactivateStore).mockResolvedValue(undefined);
    vi.mocked(getStoreActivationChecklist).mockResolvedValue(INCOMPLETE_CHECKLIST);
  });

  it('rafraîchit le badge activation après désactivation optimiste', async () => {
    render(<SuperettesPage />);

    expect(await screen.findByText('Prête')).toBeInTheDocument();
    expect(getStoreActivationChecklist).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole('button', { name: 'Désactiver la supérette' }));

    await waitFor(() => {
      expect(deactivateStore).toHaveBeenCalledWith(STORE.id);
      expect(getStoreActivationChecklist).toHaveBeenCalledTimes(1);
    });
    expect(await screen.findByText('Incomplète 7/8')).toBeInTheDocument();
  });

  it('ouvre le détail checklist prête depuis la liste sans appel API supplémentaire', async () => {
    render(<SuperettesPage />);

    expect(await screen.findByText('Prête')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Voir la checklist activation' }));

    const dialog = await screen.findByRole('dialog', { name: "Checklist d'activation" });
    expect(dialog).toBeInTheDocument();
    expect(within(dialog).getByText('Supérette Active')).toBeInTheDocument();
    expect(within(dialog).getByText('Statut global : Prête')).toBeInTheDocument();
    expect(within(dialog).getByText('8/8 critères obligatoires')).toBeInTheDocument();
    expect(within(dialog).getByText("Cette checklist aide à décider l'activation bêta ; elle ne bloque pas automatiquement l'activation.")).toBeInTheDocument();
    expect(within(dialog).getByText('Marchand actif')).toBeInTheDocument();
    expect(within(dialog).getByText('Catalogue minimum')).toBeInTheDocument();
    expect(within(dialog).getByText('Photo vitrine')).toBeInTheDocument();
    expect(within(dialog).getAllByText('Obligatoire')).toHaveLength(2);
    expect(within(dialog).getByText('Optionnel')).toBeInTheDocument();
    expect(within(dialog).getByText('6 / 5')).toBeInTheDocument();
    expect(getStoreActivationChecklist).not.toHaveBeenCalled();
  });

  it('charge le détail checklist incomplet si la liste ne le fournit pas', async () => {
    vi.mocked(listStores).mockResolvedValue({
      id: 'admin-stores',
      items: [{ ...STORE }],
      page: 1,
      limit: 20,
      total: 1,
    });

    render(<SuperettesPage />);

    expect(await screen.findByText('Activation indisponible')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Voir la checklist activation' }));

    const dialog = await screen.findByRole('dialog', { name: "Checklist d'activation" });
    expect(within(dialog).getByText('Statut global : Incomplète')).toBeInTheDocument();
    expect(within(dialog).getByText('7/8 critères obligatoires')).toBeInTheDocument();
    expect(within(dialog).getByText('Horaires renseignés')).toBeInTheDocument();
    expect(within(dialog).getAllByText('Manquant')).toHaveLength(2);
    expect(within(dialog).getByText('3 / 5')).toBeInTheDocument();
    expect(getStoreActivationChecklist).toHaveBeenCalledWith(STORE.id);
  });

  it("affiche l'erreur du détail checklist quand l'API échoue", async () => {
    vi.mocked(listStores).mockResolvedValue({
      id: 'admin-stores',
      items: [{ ...STORE }],
      page: 1,
      limit: 20,
      total: 1,
    });
    vi.mocked(getStoreActivationChecklist).mockRejectedValue(new Error('network'));

    render(<SuperettesPage />);

    expect(await screen.findByText('Activation indisponible')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Voir la checklist activation' }));

    expect(await screen.findByText("Impossible de charger la checklist d'activation.")).toBeInTheDocument();
    expect(screen.queryByText('Statut global : Incomplète')).not.toBeInTheDocument();
  });
});
