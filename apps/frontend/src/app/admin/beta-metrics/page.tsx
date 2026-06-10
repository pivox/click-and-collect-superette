'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { AdminTable, type Column } from '@/components/admin/ui/AdminTable';
import { Button } from '@/components/ui/Button';
import { getAdminBetaMetrics } from '@/lib/services/admin/beta-metrics.service';
import { listStores } from '@/lib/services/admin/stores.service';
import type {
  AdminBetaMetrics,
  AdminBetaMetricsFilters,
  AdminBetaMetricsStore,
} from '@/lib/types/admin/beta-metrics.types';
import type { Store } from '@/lib/types/admin/stores.types';

type StoreRow = AdminBetaMetricsStore & { id: string };

export default function AdminBetaMetricsPage() {
  const [metrics, setMetrics] = useState<AdminBetaMetrics | null>(null);
  const [stores, setStores] = useState<Store[]>([]);
  const [filters, setFilters] = useState<AdminBetaMetricsFilters>({});
  const [draftDateFrom, setDraftDateFrom] = useState('');
  const [draftDateTo, setDraftDateTo] = useState('');
  const [draftStoreId, setDraftStoreId] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const metricsRequestSeq = useRef(0);

  const loadMetrics = useCallback(() => {
    const requestSeq = metricsRequestSeq.current + 1;
    metricsRequestSeq.current = requestSeq;
    setIsLoading(true);
    setError(null);
    void getAdminBetaMetrics(filters)
      .then((data) => {
        if (metricsRequestSeq.current !== requestSeq) return;
        setMetrics(data);
      })
      .catch((err: unknown) => {
        if (metricsRequestSeq.current !== requestSeq) return;
        console.error('[admin-beta-metrics] metrics failed', err);
        setError('Impossible de charger les métriques bêta.');
        setMetrics(null);
      })
      .finally(() => {
        if (metricsRequestSeq.current === requestSeq) {
          setIsLoading(false);
        }
      });
  }, [filters]);

  useEffect(() => {
    loadMetrics();
  }, [loadMetrics]);

  useEffect(() => {
    let isMounted = true;

    void loadActiveStoresForFilter()
      .then((data) => {
        if (isMounted) {
          setStores(data);
        }
      })
      .catch((err: unknown) => {
        console.error('[admin-beta-metrics] stores failed', err);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  const applyFilters = () => {
    setFilters({
      ...(draftDateFrom ? { dateFrom: draftDateFrom } : {}),
      ...(draftDateTo ? { dateTo: draftDateTo } : {}),
      ...(draftStoreId ? { storeId: draftStoreId } : {}),
    });
  };

  const rows: StoreRow[] =
    metrics?.stores.map((store) => ({
      ...store,
      id: store.store_id,
    })) ?? [];

  const columns: Column<StoreRow>[] = [
    {
      key: 'store_name',
      label: 'Supérette',
      render: (row) => (
        <div>
          <div className="font-medium">{row.store_name}</div>
          <div className="text-xs text-muted">{row.store_id}</div>
        </div>
      ),
    },
    {
      key: 'submitted',
      label: 'Soumises',
      render: (row) => row.submitted,
    },
    {
      key: 'accepted',
      label: 'Acceptées',
      render: (row) => `${row.accepted} + ${row.partially_accepted} partielles`,
    },
    {
      key: 'completed',
      label: 'Complétées',
      render: (row) => row.completed,
    },
    {
      key: 'acceptance_rate',
      label: 'Acceptation',
      render: (row) => formatRate(row.acceptance_rate),
    },
    {
      key: 'cancellation_rate',
      label: 'Annulation',
      render: (row) => formatRate(row.cancellation_rate),
    },
    {
      key: 'last_activity_at',
      label: 'Dernière activité',
      render: (row) => formatDateTime(row.last_activity_at),
    },
  ];

  return (
    <div>
      <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h1 className="text-h1 font-black">Métriques bêta terrain</h1>
          <p className="mt-1 text-sm text-muted">
            Pilotage PO des commandes, annulations et activations de supérettes en bêta.
          </p>
        </div>
        <Button variant="ghost" size="md" onClick={loadMetrics}>
          Actualiser
        </Button>
      </div>

      <div className="mb-4 grid gap-3 md:grid-cols-[minmax(0,180px)_minmax(0,180px)_minmax(0,1fr)_auto]">
        <label className="text-sm font-semibold text-ink" htmlFor="beta-date-from">
          Début de période
          <input
            id="beta-date-from"
            type="date"
            value={draftDateFrom}
            onChange={(event) => setDraftDateFrom(event.target.value)}
            className="mt-1 w-full rounded-md border border-line px-3 py-2 text-sm font-normal outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </label>
        <label className="text-sm font-semibold text-ink" htmlFor="beta-date-to">
          Fin de période
          <input
            id="beta-date-to"
            type="date"
            value={draftDateTo}
            onChange={(event) => setDraftDateTo(event.target.value)}
            className="mt-1 w-full rounded-md border border-line px-3 py-2 text-sm font-normal outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </label>
        <label className="text-sm font-semibold text-ink" htmlFor="beta-store-id">
          Filtrer par supérette
          <select
            id="beta-store-id"
            value={draftStoreId}
            onChange={(event) => setDraftStoreId(event.target.value)}
            className="mt-1 w-full rounded-md border border-line px-3 py-2 text-sm font-normal outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          >
            <option value="">Toutes les supérettes actives</option>
            {stores.map((store) => (
              <option key={store.id} value={store.id}>
                {store.name}
              </option>
            ))}
          </select>
        </label>
        <Button
          type="button"
          size="md"
          className="self-end"
          onClick={applyFilters}
        >
          Appliquer
        </Button>
      </div>

      {error && (
        <div role="alert" aria-atomic="true" className="mb-4 flex items-center gap-3 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          <span className="flex-1">{error}</span>
          <button onClick={loadMetrics} className="shrink-0 font-semibold underline">
            Réessayer
          </button>
        </div>
      )}

      {isLoading && !metrics ? (
        <div className="rounded-md border border-line bg-card px-4 py-6 text-sm text-muted">
          Chargement des métriques bêta…
        </div>
      ) : metrics ? (
        <>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Metric label="Commandes soumises" value={metrics.submitted.toString()} />
            <Metric
              label="Acceptées + partielles"
              value={(metrics.accepted + metrics.partially_accepted).toString()}
              detail={`${metrics.accepted} acceptées, ${metrics.partially_accepted} partielles`}
            />
            <Metric label="Refusées" value={metrics.rejected.toString()} />
            <Metric label="Annulées" value={metrics.cancelled.toString()} />
            <Metric label="Complétées" value={metrics.completed.toString()} />
            <Metric label="Taux d’acceptation" value={formatRate(metrics.acceptance_rate)} />
            <Metric label="Taux d’annulation" value={formatRate(metrics.cancellation_rate)} />
            <Metric
              label="Activation supérettes"
              value={`${metrics.activated_stores} / ${metrics.active_stores}`}
              detail={formatRate(metrics.store_activation_rate)}
            />
          </div>

          <div className="mt-6">
            <AdminTable
              columns={columns}
              data={rows}
              isLoading={isLoading}
              emptyMessage="Aucune activité bêta sur cette période."
            />
          </div>
        </>
      ) : null}
    </div>
  );
}

function Metric({
  label,
  value,
  detail,
}: {
  label: string;
  value: string;
  detail?: string;
}) {
  return (
    <section className="rounded-md border border-line bg-card p-4">
      <p className="text-xs font-semibold uppercase text-muted">{label}</p>
      <p className="mt-1 text-2xl font-black text-ink">{value}</p>
      {detail && <p className="mt-1 text-xs text-muted">{detail}</p>}
    </section>
  );
}

function formatRate(value: number): string {
  const percentage = value * 100;
  const formatted = Number.isInteger(percentage)
    ? percentage.toString()
    : percentage.toFixed(1).replace('.', ',');

  return `${formatted} %`;
}

async function loadActiveStoresForFilter(): Promise<Store[]> {
  const limit = 50;
  let page = 1;
  let total = Number.POSITIVE_INFINITY;
  const stores: Store[] = [];

  while (stores.length < total) {
    const data = await listStores({ page, limit, isActive: true });
    stores.push(...data.items);
    total = data.total;
    if (data.items.length === 0) {
      break;
    }
    page += 1;
  }

  return stores;
}

function formatDateTime(value: string | null): string {
  if (!value) {
    return '-';
  }

  return new Date(value).toLocaleString('fr-TN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  });
}
