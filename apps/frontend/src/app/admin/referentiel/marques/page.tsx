'use client';
import { useState, useEffect, useCallback } from 'react';
import { AdminTable, type Column } from '@/components/admin/ui/AdminTable';
import { AdminConfirmDialog } from '@/components/admin/ui/AdminConfirmDialog';
import { BrandDrawer } from '@/components/admin/referentiel/marques/BrandDrawer';
import { Button } from '@/components/ui/Button';
import { useSort } from '@/lib/hooks/useSort';
import { listBrands, deleteBrand } from '@/lib/services/admin/brands.service';
import type { Brand } from '@/lib/types/admin/referentiel.types';

export default function MarquesPage() {
  const [brands, setBrands] = useState<Brand[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState('');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editTarget, setEditTarget] = useState<Brand | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Brand | null>(null);

  const filtered = brands.filter((b) =>
    search ? b.canonical_name.toLowerCase().includes(search.toLowerCase()) : true,
  );
  const { sorted, sortKey, sortDir, toggleSort } = useSort(filtered);

  const load = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await listBrands(page, 20);
      setBrands(data.items);
      setTotal(data.total);
    } catch {
      setError('Impossible de charger les marques.');
    } finally {
      setIsLoading(false);
    }
  }, [page]);

  useEffect(() => { void load(); }, [load]);

  const handleDelete = async () => {
    if (!deleteTarget) return;
    try {
      await deleteBrand(deleteTarget.id);
      setDeleteTarget(null);
      void load();
    } catch {
      setError('Impossible de supprimer cette marque.');
      setDeleteTarget(null);
    }
  };

  const columns: Column<Brand>[] = [
    { key: 'canonical_name', label: 'Nom canonique', sortable: true },
    {
      key: 'aliases',
      label: 'Aliases',
      render: (row) => (
        <div className="flex flex-wrap gap-1">
          {row.aliases.slice(0, 3).map((a) => (
            <span key={a} className="rounded bg-soft px-1.5 py-0.5 text-xs">{a}</span>
          ))}
          {row.aliases.length > 3 && (
            <span className="text-xs text-muted">+{row.aliases.length - 3}</span>
          )}
        </div>
      ),
    },
    { key: 'country', label: 'Pays', sortable: true },
    {
      key: 'is_active',
      label: 'Actif',
      sortable: true,
      render: (row) => (
        <span
          className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
            row.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
          }`}
        >
          {row.is_active ? 'Actif' : 'Inactif'}
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
          <button
            onClick={() => setDeleteTarget(row)}
            className="text-xs text-danger hover:brightness-90"
          >
            🗑 Supprimer
          </button>
        </div>
      ),
    },
  ];

  return (
    <div>
      <div className="mb-5 flex items-center justify-between">
        <h1 className="text-h1 font-black">Marques</h1>
        <Button size="md" onClick={() => { setEditTarget(null); setDrawerOpen(true); }}>
          + Nouvelle marque
        </Button>
      </div>
      <div className="mb-4">
        <input
          type="text"
          placeholder="Rechercher une marque…"
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
        emptyMessage="Aucune marque trouvée."
        emptyAction={{
          label: '+ Créer la première marque',
          onClick: () => { setEditTarget(null); setDrawerOpen(true); },
        }}
        pagination={{ page, total, limit: 20, onPageChange: setPage }}
        sortKey={sortKey as string | null}
        sortDir={sortDir}
        onSort={(key) => toggleSort(key as keyof Brand)}
      />
      <BrandDrawer
        open={drawerOpen}
        onClose={() => { setDrawerOpen(false); setEditTarget(null); }}
        brand={editTarget}
        onSaved={() => { setDrawerOpen(false); setEditTarget(null); void load(); }}
      />
      <AdminConfirmDialog
        open={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title="Supprimer la marque"
        message={`Supprimer "${deleteTarget?.canonical_name}" ? Cette action est irréversible.`}
        confirmLabel="Supprimer"
        variant="danger"
      />
    </div>
  );
}
