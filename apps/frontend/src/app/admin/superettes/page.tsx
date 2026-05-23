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
  activateStore,
  deactivateStore,
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
  const [pendingToggleId, setPendingToggleId] = useState<string | null>(null);

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

  const handleToggleActive = async (row: Store) => {
    if (pendingToggleId) return;
    setPendingToggleId(row.id);
    const toggledId = row.id;
    const originalIsActive = row.is_active;
    setStores((current) =>
      current.map((s) => (s.id === toggledId ? { ...s, is_active: !s.is_active } : s)),
    );
    try {
      if (row.is_active) {
        await deactivateStore(row.id);
      } else {
        await activateStore(row.id);
      }
      // When a status filter is active, the toggled row may no longer belong — reload.
      if (isActiveFilter !== '') {
        void load();
      }
    } catch {
      setStores((current) =>
        current.map((s) => (s.id === toggledId ? { ...s, is_active: originalIsActive } : s)),
      );
      setError('Impossible de modifier le statut de cette supérette.');
    } finally {
      setPendingToggleId(null);
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
      render: (row) =>
        row.archived_at ? (
          <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500">
            Archivée
          </span>
        ) : (
          <button
            type="button"
            onClick={() => void handleToggleActive(row)}
            disabled={pendingToggleId === row.id}
            aria-label={row.is_active ? 'Désactiver la supérette' : 'Activer la supérette'}
            className={`relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 ${
              pendingToggleId === row.id ? 'cursor-wait opacity-60' : 'cursor-pointer'
            } ${row.is_active ? 'bg-green-500' : 'bg-gray-300'}`}
          >
            <span
              className={`inline-block h-3.5 w-3.5 rounded-full bg-white shadow transition-transform ${
                row.is_active ? 'translate-x-4' : 'translate-x-0.5'
              }`}
            />
          </button>
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
            QR
          </button>
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
