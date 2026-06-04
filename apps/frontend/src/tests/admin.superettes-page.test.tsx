import { fireEvent, render, screen, waitFor } from '@testing-library/react';
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
  steps: [],
};

const INCOMPLETE_CHECKLIST = {
  ...READY_CHECKLIST,
  ready: false,
  required_completed_count: 7,
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
});
