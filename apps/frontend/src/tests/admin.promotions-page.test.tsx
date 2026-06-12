import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PromotionsPage from '@/app/admin/promotions/page';
import { listPromotions } from '@/lib/services/admin/promotions.service';
import { listStores } from '@/lib/services/admin/stores.service';
import { listMerchants } from '@/lib/services/admin/merchants.service';
import type { AdminPromotionItem } from '@/lib/types/admin/promotions.types';
import type { Store } from '@/lib/types/admin/stores.types';
import type { Merchant } from '@/lib/types/admin/merchants.types';

vi.mock('@/lib/services/admin/promotions.service', () => ({
  listPromotions: vi.fn(),
}));

vi.mock('@/lib/services/admin/stores.service', () => ({
  listStores: vi.fn(),
}));

vi.mock('@/lib/services/admin/merchants.service', () => ({
  listMerchants: vi.fn(),
}));

const ACTIVE_PROMOTION: AdminPromotionItem = {
  id: 'promo-1',
  product_name: 'Lait Vitalait 1L',
  store_id: 'store-1',
  store_name: 'Supérette El Manar',
  merchant_id: 'merchant-1',
  merchant_email: 'ali@example.test',
  price_tnd: '3.000',
  promotion_price_tnd: '2.500',
  promotion_ends_on: '2026-06-30',
  promotion_active: true,
  is_available: true,
  is_visible: true,
};

const EXPIRED_PROMOTION: AdminPromotionItem = {
  id: 'promo-2',
  product_name: 'Eau Safia 1.5L',
  store_id: 'store-2',
  store_name: 'Supérette El Amal',
  merchant_id: 'merchant-2',
  merchant_email: 'noura@example.test',
  price_tnd: '1.200',
  promotion_price_tnd: '0.900',
  promotion_ends_on: '2026-06-01',
  promotion_active: false,
  is_available: true,
  is_visible: true,
};

const STORE: Store = {
  id: 'store-1',
  name: 'Supérette El Manar',
  slug: 'superette-el-manar',
  city: 'Tunis',
  is_active: true,
  qr_code_token: 'token-1',
  created_at: '2026-06-01T10:00:00+01:00',
  owner: { id: 'merchant-1', email: 'ali@example.test' },
  products_count: 10,
  archived_at: null,
};

const MERCHANT: Merchant = {
  id: 'merchant-1',
  email: 'ali@example.test',
  first_name: 'Ali',
  last_name: 'Ben Salah',
  phone: null,
  is_active: true,
  subscription_lifecycle: null,
  created_at: '2026-06-01T10:00:00+01:00',
  stores_count: 1,
};

describe('PromotionsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(listPromotions).mockResolvedValue({
      items: [ACTIVE_PROMOTION, EXPIRED_PROMOTION],
      page: 1,
      limit: 20,
      total: 2,
    });
    vi.mocked(listStores).mockResolvedValue({
      id: 'admin-stores',
      items: [STORE],
      page: 1,
      limit: 50,
      total: 1,
    });
    vi.mocked(listMerchants).mockResolvedValue({
      id: 'admin-merchants',
      items: [MERCHANT],
      page: 1,
      limit: 50,
      total: 1,
    });
  });

  it('affiche la liste des promotions avec prix et badges de statut', async () => {
    render(<PromotionsPage />);

    expect(await screen.findByText('Lait Vitalait 1L')).toBeInTheDocument();
    expect(screen.getByText('Eau Safia 1.5L')).toBeInTheDocument();
    expect(screen.getAllByText('Supérette El Manar').length).toBeGreaterThan(0);
    expect(screen.getByText('ali@example.test')).toBeInTheDocument();
    expect(screen.getByText('3.000 TND')).toBeInTheDocument();
    expect(screen.getByText('2.500 TND')).toBeInTheDocument();
    expect(screen.getByText('Active')).toBeInTheDocument();
    expect(screen.getByText('Expirée')).toBeInTheDocument();
    expect(listPromotions).toHaveBeenCalledWith(1, 20, undefined, undefined, undefined);
  });

  it('filtre par statut de promotion', async () => {
    render(<PromotionsPage />);
    expect(await screen.findByText('Lait Vitalait 1L')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Actives' }));

    await waitFor(() => {
      expect(listPromotions).toHaveBeenLastCalledWith(1, 20, 'active', undefined, undefined);
    });

    fireEvent.click(screen.getByRole('button', { name: 'Expirées' }));

    await waitFor(() => {
      expect(listPromotions).toHaveBeenLastCalledWith(1, 20, 'expired', undefined, undefined);
    });
  });

  it('filtre par supérette et par marchand', async () => {
    render(<PromotionsPage />);
    expect(await screen.findByText('Lait Vitalait 1L')).toBeInTheDocument();

    await waitFor(() => {
      expect(listStores).toHaveBeenCalledWith({ page: 1, limit: 50 });
      expect(listMerchants).toHaveBeenCalledWith(1, 50);
    });

    fireEvent.change(screen.getByLabelText('Supérette'), { target: { value: 'store-1' } });

    await waitFor(() => {
      expect(listPromotions).toHaveBeenLastCalledWith(1, 20, undefined, 'store-1', undefined);
    });

    fireEvent.change(screen.getByLabelText('Marchand'), { target: { value: 'merchant-1' } });

    await waitFor(() => {
      expect(listPromotions).toHaveBeenLastCalledWith(1, 20, undefined, 'store-1', 'merchant-1');
    });
  });

  it('affiche un état vide quand aucune promotion', async () => {
    vi.mocked(listPromotions).mockResolvedValue({ items: [], page: 1, limit: 20, total: 0 });

    render(<PromotionsPage />);

    expect(await screen.findByText('Aucune promotion trouvée.')).toBeInTheDocument();
  });

  it('affiche une erreur avec action de relance quand le chargement échoue', async () => {
    vi.mocked(listPromotions).mockRejectedValueOnce(new Error('network'));

    render(<PromotionsPage />);

    expect(await screen.findByText('Impossible de charger les promotions.')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Réessayer' }));

    expect(await screen.findByText('Lait Vitalait 1L')).toBeInTheDocument();
  });
});
