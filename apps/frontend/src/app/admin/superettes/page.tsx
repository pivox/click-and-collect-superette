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
import type { Store } from '@/lib/types/admin/stores.types';

const PAGE_SIZE = 20;

export default function SuperettesPage() {
  const [stores, setStores] = useState<Store[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [isActiveFilter, setIsActiveFilter] = useState<'' | 'true' | 'false'>('');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editTarget, setEditTarget] = useState<Store | null>(null);
  const [archiveTarget, setArchiveTarget] = useState<Store | null>(null);

  const { sorted, sortKey, sortDir, toggleSort } = useSort(stores);

  const load = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await listStores({
        page,
        limit: PAGE_SIZE,
        isActive: isActiveFilter === '' ? undefined : isActiveFilter === 'true',
      });
      setStores(data.items);
      setTotal(data.total);
    } catch {
      setError('Impossible de charger les supérettes.');
    } finally {
      setIsLoading(false);
    }
  }, [page, isActiveFilter]);

  useEffect(() => { void load(); }, [load]);
  useEffect(() => { setPage(1); }, [isActiveFilter]);

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
          {row.slug && (
            <div className="max-w-xs truncate text-xs text-muted">{row.slug}</div>
          )}
        </div>
      ),
    },
    {
      key: 'owner' as keyof Store,
      label: 'Marchand',
      sortable: false,
      render: (row) => (
        <span className="text-sm">{row.owner?.email ?? <span className="text-muted">—</span>}</span>
      ),
    },
    {
      key: 'city',
      label: 'Ville',
      sortable: true,
      render: (row) => row.city ?? <span className="text-muted">—</span>,
    },
    {
      key: 'is_active',
      label: 'Statut',
      sortable: true,
      render: (row) => (
        <span
          className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
            row.archived_at
              ? 'bg-gray-100 text-gray-500'
              : row.is_active
                ? 'bg-green-100 text-green-700'
                : 'bg-status-cancel-bg text-status-cancel'
          }`}
        >
          {row.archived_at ? 'Archivée' : row.is_active ? 'Active' : 'Inactive'}
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
      <div className="mb-4">
        <select
          value={isActiveFilter}
          onChange={(e) => setIsActiveFilter(e.target.value as '' | 'true' | 'false')}
          className="rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
        >
          <option value="">Tous les statuts</option>
          <option value="true">Active</option>
          <option value="false">Inactive</option>
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
