import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { StoreDrawer } from '@/components/admin/superettes/StoreDrawer';
import { createStore } from '@/lib/services/admin/stores.service';
import { listMerchants } from '@/lib/services/admin/merchants.service';

vi.mock('@/lib/services/admin/stores.service', () => ({
  createStore: vi.fn(),
  updateStore: vi.fn(),
  getStoreQrCode: vi.fn(),
  regenerateStoreQrCode: vi.fn(),
}));

vi.mock('@/lib/services/admin/merchants.service', () => ({
  listMerchants: vi.fn(),
}));

const CREATED_STORE = {
  id: 'store-uuid-1',
  name: 'Supérette Sans Marchand',
  slug: 'superette-sans-marchand',
  city: 'Tunis',
  is_active: true,
  qr_code_token: 'qr-token',
  created_at: '2026-06-01T10:00:00+01:00',
  owner: null,
  products_count: 0,
  archived_at: null,
};

describe('StoreDrawer', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(listMerchants).mockResolvedValue({
      id: 'admin-merchants',
      items: [],
      page: 1,
      limit: 100,
      total: 0,
    });
    vi.mocked(createStore).mockResolvedValue(CREATED_STORE);
  });

  it('permet de créer une supérette sans marchand propriétaire', async () => {
    const onSaved = vi.fn();

    render(
      <StoreDrawer
        open
        onClose={vi.fn()}
        store={null}
        onSaved={onSaved}
      />,
    );

    fireEvent.change(screen.getByText('Nom *').parentElement!.querySelector('input')!, {
      target: { value: 'Supérette Sans Marchand' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() => {
      expect(createStore).toHaveBeenCalledWith({
        name: 'Supérette Sans Marchand',
        address: undefined,
        city: undefined,
        phone: undefined,
      });
    });
    expect(screen.queryByText('Le marchand est obligatoire.')).not.toBeInTheDocument();
    expect(onSaved).toHaveBeenCalled();
  });
});
