'use client';
import { useState, useEffect, useCallback } from 'react';
import { AdminTable, type Column } from '@/components/admin/ui/AdminTable';
import { AdminConfirmDialog } from '@/components/admin/ui/AdminConfirmDialog';
import { StoreDrawer } from '@/components/admin/superettes/StoreDrawer';
import { Button } from '@/components/ui/Button';
import { useSort } from '@/lib/hooks/useSort';
import {
  listStores,
  archiveStore,
} from '@/lib/services/admin/stores.service';
import { listMerchants } from '@/lib/services/admin/merchants.service';
import type { Store } from '@/lib/types/admin/stores.types';
import type { Merchant } from '@/lib/types/admin/merchants.types';

const PAGE_SIZE = 20;

export default function SuperettesPage() {
  const [stores, setStores] = useState<Store[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [merchantFilter, setMerchantFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [filterMerchants, setFilterMerchants] = useState<Merchant[]>([]);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editTarget, setEditTarget] = useState<Store | null>(null);
  const [archiveTarget, setArchiveTarget] = useState<Store | null>(null);

  const { sorted, sortKey, sortDir, toggleSort } = useSort(stores);

  useEffect(() => {
    void listMerchants(1, 100)
      .then((data) => setFilterMerchants(data.items))
      .catch(() => {
        setError('Impossible de charger la liste des marchands.');
      });
  }, []);

  const load = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await listStores({
        page,
        limit: PAGE_SIZE,
        merchant: merchantFilter || undefined,
        status: statusFilter || undefined,
      });
      setStores(data.items);
      setTotal(data.total);
    } catch {
      setError('Impossible de charger les supérettes.');
    } finally {
      setIsLoading(false);
    }
  }, [page, merchantFilter, statusFilter]);

  useEffect(() => { void load(); }, [load]);
  useEffect(() => { setPage(1); }, [merchantFilter, statusFilter]);

  const handleArchive = async () => {
    if (!archiveTarget) return;
    try {
      await archiveStore(archiveTarget.id);
      setArchiveTarget(null);
      void load();
    } catch {
      setError("Impossible d'archiver cette supérette.");
      setArchiveTarget(null);
    }
  };

  const columns: Column<Store>[] = [
    {
      key: 'name',
      label: 'Supérette',
      sortable: true,
      render: (row) => (
        <div>
          <div className={`font-medium ${row.archived_at ? 'text-muted' : ''}`}>{row.name}</div>
          {row.description && (
            <div className="max-w-xs truncate text-xs text-muted">{row.description}</div>
          )}
        </div>
      ),
    },
    {
      key: 'merchant_name',
      label: 'Marchand',
      sortable: true,
      render: (row) => <span className="text-sm">{row.merchant_name}</span>,
    },
    {
      key: 'city',
      label: 'Ville',
      sortable: true,
      render: (row) => row.city ?? <span className="text-muted">—</span>,
    },
    {
      key: 'archived_at',
      label: 'Statut',
      sortable: true,
      render: (row) => (
        <span
          className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
            row.archived_at
              ? 'bg-gray-100 text-gray-500'
              : 'bg-green-100 text-green-700'
          }`}
        >
          {row.archived_at ? 'Archivée' : 'Active'}
        </span>
      ),
    },
    {
      key: 'actions',
      label: '',
      render: (row) => (
        <div className="flex justify-end gap-2">
          <button
            onClick={() => { setEditTarget(row); setDrawerOpen(true); }}
            className="text-xs text-muted hover:text-ink"
          >
            ✏ Modifier
          </button>
          {!row.archived_at && (
            <button
              onClick={() => setArchiveTarget(row)}
              className="text-xs text-muted hover:text-danger"
            >
              ⊘ Archiver
            </button>
          )}
        </div>
      ),
    },
  ];

  return (
    <div>
      <div className="mb-5 flex items-center justify-between">
        <h1 className="text-h1 font-black">Supérettes</h1>
        <Button size="md" onClick={() => { setEditTarget(null); setDrawerOpen(true); }}>
          + Nouvelle supérette
        </Button>
      </div>
      <div className="mb-4 flex flex-wrap gap-3">
        <select
          value={merchantFilter}
          onChange={(e) => setMerchantFilter(e.target.value)}
          className="rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
        >
          <option value="">Tous les marchands</option>
          {filterMerchants.map((m) => (
            <option key={m.id} value={m.id}>
              {m.first_name} {m.last_name}
            </option>
          ))}
        </select>
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
        >
          <option value="">Tous les statuts</option>
          <option value="active">Active</option>
          <option value="archived">Archivée</option>
        </select>
      </div>
      {error && (
        <div className="mb-4 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          {error}
        </div>
      )}
      <AdminTable
        columns={columns}
        data={sorted}
        isLoading={isLoading}
        emptyMessage="Aucune supérette trouvée."
        emptyAction={{
          label: '+ Créer la première supérette',
          onClick: () => { setEditTarget(null); setDrawerOpen(true); },
        }}
        pagination={{ page, total, limit: PAGE_SIZE, onPageChange: setPage }}
        sortKey={sortKey as string | null}
        sortDir={sortDir}
        onSort={(key) => toggleSort(key as keyof Store)}
      />
      <StoreDrawer
        open={drawerOpen}
        onClose={() => { setDrawerOpen(false); setEditTarget(null); }}
        store={editTarget}
        onSaved={() => { setDrawerOpen(false); setEditTarget(null); void load(); }}
      />
      <AdminConfirmDialog
        open={!!archiveTarget}
        onClose={() => setArchiveTarget(null)}
        onConfirm={handleArchive}
        title="Archiver la supérette"
        message={`Archiver "${archiveTarget?.name}" ? Les commandes actives seront annulées.`}
        confirmLabel="Archiver"
        variant="warning"
      />
    </div>
  );
}
