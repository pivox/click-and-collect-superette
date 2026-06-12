'use client';
import { useState, useEffect, useCallback, useRef } from 'react';
import { AdminTable, type Column } from '@/components/admin/ui/AdminTable';
import { listPromotions } from '@/lib/services/admin/promotions.service';
import { listStores } from '@/lib/services/admin/stores.service';
import { listMerchants } from '@/lib/services/admin/merchants.service';
import type { AdminPromotionItem, AdminPromotionStatusFilter } from '@/lib/types/admin/promotions.types';
import type { Store } from '@/lib/types/admin/stores.types';
import type { Merchant } from '@/lib/types/admin/merchants.types';

const PAGE_SIZE = 20;

type StatusFilter = '' | AdminPromotionStatusFilter;

function merchantLabel(merchant: Merchant): string {
  return [merchant.first_name, merchant.last_name].filter(Boolean).join(' ') || merchant.email;
}

// promotion_ends_on is a business date (YYYY-MM-DD, end of day in Tunisia):
// going through new Date() would shift it by a day in browsers west of UTC
function formatPromotionEndsOn(isoDate: string): string {
  const [year, month, day] = isoDate.split('-');
  return `${day}/${month}/${year}`;
}

export default function PromotionsPage() {
  const [promotions, setPromotions] = useState<AdminPromotionItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('');
  const [storeFilter, setStoreFilter] = useState('');
  const [merchantFilter, setMerchantFilter] = useState('');
  const [stores, setStores] = useState<Store[]>([]);
  const [merchants, setMerchants] = useState<Merchant[]>([]);
  const requestSeq = useRef(0);

  const load = useCallback(async () => {
    // Ignore stale responses: an in-flight request resolved after a newer one
    // must not overwrite the list with results for old page/filter params
    const seq = ++requestSeq.current;
    setIsLoading(true);
    setError(null);
    try {
      const data = await listPromotions(
        page,
        PAGE_SIZE,
        statusFilter || undefined,
        storeFilter || undefined,
        merchantFilter || undefined,
      );
      if (requestSeq.current !== seq) return;
      setPromotions(data.items);
      setTotal(data.total);
    } catch (err) {
      if (requestSeq.current !== seq) return;
      console.error('[promotions] listPromotions failed', err);
      setError('Impossible de charger les promotions.');
    } finally {
      if (requestSeq.current === seq) {
        setIsLoading(false);
      }
    }
  }, [page, statusFilter, storeFilter, merchantFilter]);

  useEffect(() => { void load(); }, [load]);

  // Page reset happens in the same handler as the filter change: a separate
  // effect would fire a first request with the new filter but the stale page.
  const applyStatusFilter = (value: StatusFilter) => {
    setStatusFilter(value);
    setPage(1);
  };

  const applyStoreFilter = (value: string) => {
    setStoreFilter(value);
    setPage(1);
  };

  const applyMerchantFilter = (value: string) => {
    setMerchantFilter(value);
    setPage(1);
  };

  useEffect(() => {
    // Filter selects: first 50 entries are enough for the MVP volume
    listStores({ page: 1, limit: 50 })
      .then((data) => setStores(data.items))
      .catch((err) => console.error('[promotions] listStores failed', err));
    listMerchants(1, 50)
      .then((data) => setMerchants(data.items))
      .catch((err) => console.error('[promotions] listMerchants failed', err));
  }, []);

  const columns: Column<AdminPromotionItem>[] = [
    {
      key: 'product_name',
      label: 'Produit',
      render: (row) => (
        <div>
          <div className="font-medium">{row.product_name}</div>
          {(!row.is_visible || !row.is_available) && (
            <div className="text-xs text-muted">
              {!row.is_visible ? 'Masqué du catalogue' : 'Indisponible'}
            </div>
          )}
        </div>
      ),
    },
    {
      key: 'store_name',
      label: 'Supérette',
      render: (row) => row.store_name,
    },
    {
      key: 'merchant_email',
      label: 'Marchand',
      render: (row) => row.merchant_email ?? '—',
    },
    {
      key: 'price_tnd',
      label: 'Prix',
      render: (row) => (
        <span className="tabular-nums text-muted line-through">{row.price_tnd} TND</span>
      ),
    },
    {
      key: 'promotion_price_tnd',
      label: 'Prix promo',
      render: (row) => (
        <span className="tabular-nums font-semibold">{row.promotion_price_tnd} TND</span>
      ),
    },
    {
      key: 'promotion_ends_on',
      label: 'Fin de promo',
      render: (row) => formatPromotionEndsOn(row.promotion_ends_on),
    },
    {
      key: 'promotion_active',
      label: 'Statut',
      render: (row) => (
        <span
          className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
            row.promotion_active ? 'bg-green-100 text-green-700' : 'bg-soft text-muted'
          }`}
        >
          {row.promotion_active ? 'Active' : 'Expirée'}
        </span>
      ),
    },
  ];

  return (
    <div>
      <div className="mb-5">
        <h1 className="text-h1 font-black">Promotions</h1>
        <p className="mt-1 text-sm text-muted">
          Suivi en lecture seule des promotions configurées par les marchands.
        </p>
      </div>
      <div className="mb-4 flex flex-wrap items-center gap-3">
        <div className="flex items-center gap-2">
          {([['', 'Toutes'], ['active', 'Actives'], ['expired', 'Expirées']] as [StatusFilter, string][]).map(([val, label]) => (
            <button
              key={val}
              onClick={() => applyStatusFilter(val)}
              className={`rounded-full px-3 py-1 text-xs font-semibold transition-colors ${
                statusFilter === val
                  ? 'bg-primary text-white'
                  : 'bg-soft text-muted hover:bg-line'
              }`}
            >
              {label}
            </button>
          ))}
        </div>
        <div className="flex items-center gap-2">
          <label htmlFor="store-filter" className="text-xs font-semibold text-muted">
            Supérette
          </label>
          <select
            id="store-filter"
            value={storeFilter}
            onChange={(e) => applyStoreFilter(e.target.value)}
            className="max-w-48 rounded-md border border-line px-2 py-1.5 text-sm outline-none focus:border-primary"
          >
            <option value="">Toutes</option>
            {stores.map((store) => (
              <option key={store.id} value={store.id}>{store.name}</option>
            ))}
          </select>
        </div>
        <div className="flex items-center gap-2">
          <label htmlFor="merchant-filter" className="text-xs font-semibold text-muted">
            Marchand
          </label>
          <select
            id="merchant-filter"
            value={merchantFilter}
            onChange={(e) => applyMerchantFilter(e.target.value)}
            className="max-w-48 rounded-md border border-line px-2 py-1.5 text-sm outline-none focus:border-primary"
          >
            <option value="">Tous</option>
            {merchants.map((merchant) => (
              <option key={merchant.id} value={merchant.id}>{merchantLabel(merchant)}</option>
            ))}
          </select>
        </div>
      </div>
      {error && (
        <div role="alert" aria-atomic="true" className="mb-4 flex items-center gap-3 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          <span className="flex-1">{error}</span>
          <button onClick={() => void load()} className="shrink-0 font-semibold underline">
            Réessayer
          </button>
        </div>
      )}
      <AdminTable
        columns={columns}
        data={promotions}
        isLoading={isLoading}
        emptyMessage="Aucune promotion trouvée."
        pagination={{ page, total, limit: PAGE_SIZE, onPageChange: setPage }}
      />
    </div>
  );
}
