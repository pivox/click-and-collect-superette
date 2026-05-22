'use client';
import { useState, useEffect, useCallback } from 'react';
import { AdminTable, type Column } from '@/components/admin/ui/AdminTable';
import { AdminConfirmDialog } from '@/components/admin/ui/AdminConfirmDialog';
import { CategoryDrawer } from '@/components/admin/referentiel/categories/CategoryDrawer';
import { Button } from '@/components/ui/Button';
import { useSort } from '@/lib/hooks/useSort';
import { listCategories, deleteCategory } from '@/lib/services/admin/categories.service';
import type { Category } from '@/lib/types/admin/referentiel.types';

export default function CategoriesPage() {
  const [categories, setCategories] = useState<Category[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState('');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editTarget, setEditTarget] = useState<Category | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Category | null>(null);

  const filtered = categories.filter((c) =>
    search
      ? c.name_fr.toLowerCase().includes(search.toLowerCase()) ||
        (c.name_ar?.toLowerCase().includes(search.toLowerCase()) ?? false)
      : true,
  );
  const { sorted, sortKey, sortDir, toggleSort } = useSort(filtered);

  const load = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await listCategories(page, 20);
      setCategories(data.items);
      setTotal(data.total);
    } catch {
      setError('Impossible de charger les catégories.');
    } finally {
      setIsLoading(false);
    }
  }, [page]);

  useEffect(() => { void load(); }, [load]);

  const handleDelete = async () => {
    if (!deleteTarget) return;
    try {
      await deleteCategory(deleteTarget.id);
      setDeleteTarget(null);
      void load();
    } catch {
      setError('Impossible de supprimer cette catégorie.');
      setDeleteTarget(null);
    }
  };

  const columns: Column<Category>[] = [
    { key: 'name_fr', label: 'Nom FR', sortable: true },
    { key: 'name_ar', label: 'Nom AR' },
    { key: 'slug', label: 'Slug' },
    { key: 'sort_order', label: 'Ordre', sortable: true },
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
        <h1 className="text-h1 font-black">Catégories</h1>
        <Button size="md" onClick={() => { setEditTarget(null); setDrawerOpen(true); }}>
          + Nouvelle catégorie
        </Button>
      </div>
      <div className="mb-4">
        <input
          type="text"
          placeholder="Rechercher une catégorie…"
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
        emptyMessage="Aucune catégorie trouvée."
        emptyAction={{
          label: '+ Créer la première catégorie',
          onClick: () => { setEditTarget(null); setDrawerOpen(true); },
        }}
        pagination={{ page, total, limit: 20, onPageChange: setPage }}
        sortKey={sortKey as string | null}
        sortDir={sortDir}
        onSort={(key) => toggleSort(key as keyof Category)}
      />
      <CategoryDrawer
        open={drawerOpen}
        onClose={() => { setDrawerOpen(false); setEditTarget(null); }}
        category={editTarget}
        onSaved={() => { setDrawerOpen(false); setEditTarget(null); void load(); }}
      />
      <AdminConfirmDialog
        open={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title="Supprimer la catégorie"
        message={`Supprimer "${deleteTarget?.name_fr}" ? Cette action est irréversible.`}
        confirmLabel="Supprimer"
        variant="danger"
      />
    </div>
  );
}
