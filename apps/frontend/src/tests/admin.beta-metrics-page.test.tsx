import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminBetaMetricsPage from '@/app/admin/beta-metrics/page';
import { getAdminBetaMetrics } from '@/lib/services/admin/beta-metrics.service';
import { listStores } from '@/lib/services/admin/stores.service';
import type { AdminBetaMetrics } from '@/lib/types/admin/beta-metrics.types';
import type { StoreListResponse } from '@/lib/types/admin/stores.types';

vi.mock('@/lib/services/admin/beta-metrics.service', () => ({
  getAdminBetaMetrics: vi.fn(),
}));

vi.mock('@/lib/services/admin/stores.service', () => ({
  listStores: vi.fn(),
}));

const METRICS: AdminBetaMetrics = {
  id: 'admin-beta-metrics',
  date_from: '2026-06-01',
  date_to: '2026-06-30',
  store_id: null,
  submitted: 12,
  accepted: 7,
  partially_accepted: 2,
  rejected: 2,
  cancelled: 1,
  completed: 8,
  acceptance_rate: 0.75,
  cancellation_rate: 0.083,
  active_stores: 4,
  activated_stores: 3,
  store_activation_rate: 0.75,
  stores: [
    {
      store_id: 'store-1',
      store_name: 'Supérette El Amen',
      submitted: 8,
      accepted: 5,
      partially_accepted: 1,
      rejected: 1,
      cancelled: 1,
      completed: 6,
      acceptance_rate: 0.75,
      cancellation_rate: 0.125,
      last_activity_at: '2026-06-04T12:00:00+01:00',
    },
    {
      store_id: 'store-2',
      store_name: 'Supérette Medina',
      submitted: 4,
      accepted: 2,
      partially_accepted: 1,
      rejected: 1,
      cancelled: 0,
      completed: 2,
      acceptance_rate: 0.75,
      cancellation_rate: 0,
      last_activity_at: null,
    },
  ],
};

const EMPTY_METRICS: AdminBetaMetrics = {
  ...METRICS,
  submitted: 0,
  accepted: 0,
  partially_accepted: 0,
  rejected: 0,
  cancelled: 0,
  completed: 0,
  acceptance_rate: 0,
  cancellation_rate: 0,
  active_stores: 0,
  activated_stores: 0,
  store_activation_rate: 0,
  stores: [],
};

const FILTERED_METRICS: AdminBetaMetrics = {
  ...EMPTY_METRICS,
  date_from: '2026-06-01',
  date_to: '2026-06-30',
  store_id: 'store-2',
  submitted: 3,
  accepted: 2,
  completed: 1,
  acceptance_rate: 0.667,
  active_stores: 1,
  activated_stores: 1,
  store_activation_rate: 1,
  stores: [
    {
      store_id: 'store-2',
      store_name: 'Supérette Medina',
      submitted: 3,
      accepted: 2,
      partially_accepted: 0,
      rejected: 0,
      cancelled: 0,
      completed: 1,
      acceptance_rate: 0.667,
      cancellation_rate: 0,
      last_activity_at: '2026-06-05T10:00:00+01:00',
    },
  ],
};

const STORES: StoreListResponse = {
  id: 'admin-stores',
  page: 1,
  limit: 100,
  total: 2,
  items: [
    {
      id: 'store-1',
      name: 'Supérette El Amen',
      slug: 'el-amen',
      city: 'Tunis',
      is_active: true,
      qr_code_token: 'qr-store-1',
      created_at: '2026-06-01T09:00:00+01:00',
      owner: null,
      products_count: 12,
      archived_at: null,
    },
    {
      id: 'store-2',
      name: 'Supérette Medina',
      slug: 'medina',
      city: 'Sfax',
      is_active: true,
      qr_code_token: 'qr-store-2',
      created_at: '2026-06-02T09:00:00+01:00',
      owner: null,
      products_count: 5,
      archived_at: null,
    },
  ],
};

describe('AdminBetaMetricsPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    vi.mocked(getAdminBetaMetrics).mockResolvedValue(METRICS);
    vi.mocked(listStores).mockResolvedValue(STORES);
  });

  it('affiche les KPI bêta et le tableau par supérette', async () => {
    render(<AdminBetaMetricsPage />);

    expect(await screen.findByRole('heading', { name: 'Métriques bêta terrain' })).toBeInTheDocument();
    expect(screen.getByText('Commandes soumises')).toBeInTheDocument();
    expect(screen.getByText('12')).toBeInTheDocument();
    expect(screen.getByText('Acceptées + partielles')).toBeInTheDocument();
    expect(screen.getByText('9')).toBeInTheDocument();
    expect(screen.getByText('Taux d’acceptation')).toBeInTheDocument();
    expect(screen.getAllByText('75 %').length).toBeGreaterThanOrEqual(2);
    expect(screen.getByText('Taux d’annulation')).toBeInTheDocument();
    expect(screen.getByText('8,3 %')).toBeInTheDocument();
    expect(screen.getByText('Activation supérettes')).toBeInTheDocument();
    expect(screen.getByText('3 / 4')).toBeInTheDocument();
    expect(screen.getAllByText('Supérette El Amen').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('Supérette Medina').length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText('Dernière activité')).toBeInTheDocument();
  });

  it('recharge les KPI avec les filtres période et supérette', async () => {
    render(<AdminBetaMetricsPage />);

    await screen.findAllByText('Supérette El Amen');

    fireEvent.change(screen.getByLabelText('Début de période'), {
      target: { value: '2026-06-01' },
    });
    fireEvent.change(screen.getByLabelText('Fin de période'), {
      target: { value: '2026-06-30' },
    });
    fireEvent.change(screen.getByLabelText('Filtrer par supérette'), {
      target: { value: 'store-1' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Appliquer' }));

    await waitFor(() =>
      expect(getAdminBetaMetrics).toHaveBeenLastCalledWith({
        dateFrom: '2026-06-01',
        dateTo: '2026-06-30',
        storeId: 'store-1',
      }),
    );
  });

  it('ignore une réponse métriques obsolète après changement de filtre', async () => {
    const initial = createDeferred<AdminBetaMetrics>();
    const filtered = createDeferred<AdminBetaMetrics>();
    vi.mocked(getAdminBetaMetrics)
      .mockReturnValueOnce(initial.promise)
      .mockReturnValueOnce(filtered.promise);

    render(<AdminBetaMetricsPage />);

    await screen.findByRole('heading', { name: 'Métriques bêta terrain' });

    fireEvent.change(screen.getByLabelText('Début de période'), {
      target: { value: '2026-06-01' },
    });
    fireEvent.change(screen.getByLabelText('Fin de période'), {
      target: { value: '2026-06-30' },
    });
    fireEvent.change(screen.getByLabelText('Filtrer par supérette'), {
      target: { value: 'store-2' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Appliquer' }));

    await waitFor(() => expect(getAdminBetaMetrics).toHaveBeenCalledTimes(2));

    await act(async () => {
      filtered.resolve(FILTERED_METRICS);
      await filtered.promise;
    });

    expect((await screen.findAllByText('3')).length).toBeGreaterThanOrEqual(1);
    expect(screen.queryByText('12')).not.toBeInTheDocument();

    await act(async () => {
      initial.resolve(METRICS);
      await initial.promise;
    });

    expect(screen.getAllByText('3').length).toBeGreaterThanOrEqual(1);
    expect(screen.queryByText('12')).not.toBeInTheDocument();
  });

  it('charge toutes les pages de supérettes actives pour le filtre', async () => {
    vi.mocked(listStores)
      .mockResolvedValueOnce({
        ...STORES,
        page: 1,
        limit: 50,
        total: 51,
        items: STORES.items.slice(0, 1),
      })
      .mockResolvedValueOnce({
        ...STORES,
        page: 2,
        limit: 50,
        total: 51,
        items: [
          {
            id: 'store-51',
            name: 'Supérette Route 51',
            slug: 'route-51',
            city: 'Sousse',
            is_active: true,
            qr_code_token: 'qr-store-51',
            created_at: '2026-06-03T09:00:00+01:00',
            owner: null,
            products_count: 7,
            archived_at: null,
          },
        ],
      });

    render(<AdminBetaMetricsPage />);

    expect(await screen.findByRole('option', { name: 'Supérette Route 51' })).toBeInTheDocument();
    expect(listStores).toHaveBeenNthCalledWith(1, { page: 1, limit: 50, isActive: true });
    expect(listStores).toHaveBeenNthCalledWith(2, { page: 2, limit: 50, isActive: true });
  });

  it('gère une erreur API puis recharge au retry', async () => {
    vi.mocked(getAdminBetaMetrics)
      .mockRejectedValueOnce(new Error('api down'))
      .mockResolvedValueOnce(METRICS);

    render(<AdminBetaMetricsPage />);

    expect(await screen.findByText('Impossible de charger les métriques bêta.')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Réessayer' }));

    await waitFor(() => expect(getAdminBetaMetrics).toHaveBeenCalledTimes(2));
    expect((await screen.findAllByText('Supérette El Amen')).length).toBeGreaterThanOrEqual(1);
  });

  it('affiche un empty state quand aucune supérette n’a d’activité', async () => {
    vi.mocked(getAdminBetaMetrics).mockResolvedValue(EMPTY_METRICS);

    render(<AdminBetaMetricsPage />);

    expect(await screen.findByText('Aucune activité bêta sur cette période.')).toBeInTheDocument();
  });
});

function createDeferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((promiseResolve) => {
    resolve = promiseResolve;
  });

  return { promise, resolve };
}
