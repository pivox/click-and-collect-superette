'use client';
import { useState, useEffect, useCallback } from 'react';
import { AdminTable, type Column } from '@/components/admin/ui/AdminTable';
import { AdminConfirmDialog } from '@/components/admin/ui/AdminConfirmDialog';
import { MerchantDrawer } from '@/components/admin/marchands/MerchantDrawer';
import { Button } from '@/components/ui/Button';
import { useSort } from '@/lib/hooks/useSort';
import {
  listMerchants,
  suspendMerchant,
  activateMerchant,
} from '@/lib/services/admin/merchants.service';
import type { Merchant } from '@/lib/types/admin/merchants.types';

const PAGE_SIZE = 20;

export default function MarchandsPage() {
  const [merchants, setMerchants] = useState<Merchant[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editTarget, setEditTarget] = useState<Merchant | null>(null);
  const [suspendTarget, setSuspendTarget] = useState<Merchant | null>(null);
  const [suspendReason, setSuspendReason] = useState('');
  const [activateTarget, setActivateTarget] = useState<Merchant | null>(null);

  const { sorted, sortKey, sortDir, toggleSort } = useSort(merchants);

  useEffect(() => {
    const t = setTimeout(() => setDebouncedSearch(search), 400);
    return () => clearTimeout(t);
  }, [search]);

  const load = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await listMerchants(page, PAGE_SIZE, debouncedSearch || undefined);
      setMerchants(data.items);
      setTotal(data.total);
    } catch {
      setError('Impossible de charger les marchands.');
    } finally {
      setIsLoading(false);
    }
  }, [page, debouncedSearch]);

  useEffect(() => { void load(); }, [load]);
  useEffect(() => { setPage(1); }, [debouncedSearch]);

  const handleSuspend = async () => {
    if (!suspendTarget) return;
    try {
      await suspendMerchant(suspendTarget.id, suspendReason.trim());
      setSuspendTarget(null);
      setSuspendReason('');
      void load();
    } catch {
      setError('Impossible de suspendre ce marchand.');
      setSuspendTarget(null);
      setSuspendReason('');
    }
  };

  const handleActivate = async () => {
    if (!activateTarget) return;
    try {
      await activateMerchant(activateTarget.id);
      setActivateTarget(null);
      void load();
    } catch {
      setError('Impossible de réactiver ce marchand.');
      setActivateTarget(null);
    }
  };

  const columns: Column<Merchant>[] = [
    {
      key: 'last_name',
      label: 'Marchand',
      sortable: true,
      render: (row) => (
        <div>
          <div className="font-medium">
            {row.first_name} {row.last_name}
          </div>
          <div className="text-xs text-muted">{row.email}</div>
        </div>
      ),
    },
    {
      key: 'shop_count',
      label: 'Supérettes',
      sortable: true,
      render: (row) => <span className="tabular-nums">{row.shop_count}</span>,
    },
    {
      key: 'is_suspended',
      label: 'Statut',
      sortable: true,
      render: (row) => (
        <span
          className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
            row.is_suspended
              ? 'bg-status-cancel-bg text-status-cancel'
              : 'bg-green-100 text-green-700'
          }`}
        >
          {row.is_suspended ? 'Suspendu' : 'Actif'}
        </span>
      ),
    },
    {
      key: 'created_at',
      label: 'Créé le',
      sortable: true,
      render: (row) => new Date(row.created_at).toLocaleDateString('fr-TN'),
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
          {row.is_suspended ? (
            <button
              onClick={() => setActivateTarget(row)}
              className="text-xs text-green-600 hover:brightness-90"
            >
              ✓ Réactiver
            </button>
          ) : (
            <button
              onClick={() => { setSuspendTarget(row); setSuspendReason(''); }}
              className="text-xs text-danger hover:brightness-90"
            >
              ⊘ Suspendre
            </button>
          )}
        </div>
      ),
    },
  ];

  return (
    <div>
      <div className="mb-5 flex items-center justify-between">
        <h1 className="text-h1 font-black">Marchands</h1>
        <Button size="md" onClick={() => { setEditTarget(null); setDrawerOpen(true); }}>
          + Nouveau marchand
        </Button>
      </div>
      <div className="mb-4">
        <input
          type="text"
          placeholder="Rechercher par nom ou email…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-full max-w-sm rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        />
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
        emptyMessage="Aucun marchand trouvé."
        emptyAction={{
          label: '+ Créer le premier marchand',
          onClick: () => { setEditTarget(null); setDrawerOpen(true); },
        }}
        pagination={{ page, total, limit: PAGE_SIZE, onPageChange: setPage }}
        sortKey={sortKey as string | null}
        sortDir={sortDir}
        onSort={(key) => toggleSort(key as keyof Merchant)}
      />
      <MerchantDrawer
        open={drawerOpen}
        onClose={() => { setDrawerOpen(false); setEditTarget(null); }}
        merchant={editTarget}
        onSaved={() => { setDrawerOpen(false); setEditTarget(null); void load(); }}
      />
      <AdminConfirmDialog
        open={!!suspendTarget}
        onClose={() => { setSuspendTarget(null); setSuspendReason(''); }}
        onConfirm={handleSuspend}
        title="Suspendre le marchand"
        message={`Suspendre le compte de ${suspendTarget?.first_name} ${suspendTarget?.last_name} ?`}
        confirmLabel="Suspendre"
        variant="danger"
        extraField={{
          label: 'Raison',
          value: suspendReason,
          onChange: setSuspendReason,
          required: true,
        }}
      />
      <AdminConfirmDialog
        open={!!activateTarget}
        onClose={() => setActivateTarget(null)}
        onConfirm={handleActivate}
        title="Réactiver le marchand"
        message={`Réactiver le compte de ${activateTarget?.first_name} ${activateTarget?.last_name} ?`}
        confirmLabel="Réactiver"
        variant="warning"
      />
    </div>
  );
}
