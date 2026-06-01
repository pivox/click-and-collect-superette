'use client';
import { useState, useEffect, useCallback } from 'react';
import { Sparkles } from 'lucide-react';
import { AdminTable, type Column } from '@/components/admin/ui/AdminTable';
import { AdminConfirmDialog } from '@/components/admin/ui/AdminConfirmDialog';
import { ProductReferenceDrawer } from '@/components/admin/referentiel/produits/ProductReferenceDrawer';
import { Button } from '@/components/ui/Button';
import { useSort } from '@/lib/hooks/useSort';
import {
  listProductReferences,
  archiveProductReference,
} from '@/lib/services/admin/product-references.service';
import { runProductAiEnrichment } from '@/lib/services/admin/product-ai-enrichment.service';
import { listBrands } from '@/lib/services/admin/brands.service';
import { listCategories } from '@/lib/services/admin/categories.service';
import type {
  ProductReference,
  Brand,
  Category,
  ProductAiEnrichmentRunResult,
} from '@/lib/types/admin/referentiel.types';
import { cn } from '@/lib/cn';

const STATUS_LABELS: Record<string, string> = {
  approved: 'Approuvé',
  pending_review: 'À valider',
  draft: 'Brouillon',
  rejected: 'Rejeté',
  archived: 'Archivé',
};

const STATUS_STYLES: Record<string, string> = {
  approved: 'bg-green-100 text-green-700',
  pending_review: 'bg-yellow-100 text-yellow-700',
  draft: 'bg-gray-100 text-gray-600',
  rejected: 'bg-red-100 text-red-700',
  archived: 'bg-gray-50 text-gray-400',
};

export default function ProduitsPage() {
  const [products, setProducts] = useState<ProductReference[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [q, setQ] = useState('');
  const [debouncedQ, setDebouncedQ] = useState('');
  const [brandFilter, setBrandFilter] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [filterBrands, setFilterBrands] = useState<Brand[]>([]);
  const [filterCategories, setFilterCategories] = useState<Category[]>([]);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editTarget, setEditTarget] = useState<ProductReference | null>(null);
  const [archiveTarget, setArchiveTarget] = useState<ProductReference | null>(null);
  const [aiLimit, setAiLimit] = useState(100);
  const [aiResult, setAiResult] = useState<ProductAiEnrichmentRunResult | null>(null);
  const [aiError, setAiError] = useState<string | null>(null);
  const [isAiRunning, setIsAiRunning] = useState(false);

  const { sorted, sortKey, sortDir, toggleSort } = useSort(products);

  useEffect(() => {
    void Promise.all([listBrands(1, 50), listCategories(1, 50)])
      .then(([b, c]) => {
        setFilterBrands(b.items);
        setFilterCategories(c.items);
      })
      .catch(() => {
        setError('Impossible de charger les filtres marques/catégories.');
      });
  }, []);

  useEffect(() => {
    const t = setTimeout(() => setDebouncedQ(q), 400);
    return () => clearTimeout(t);
  }, [q]);

  const load = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await listProductReferences({
        q: debouncedQ || undefined,
        brand: brandFilter || undefined,
        category: categoryFilter || undefined,
        status: statusFilter || undefined,
        page,
        limit: 20,
      });
      setProducts(data.items);
      setTotal(data.total);
    } catch (err) {
      console.error('[produits] listProductReferences failed', err);
      setError('Impossible de charger les produits.');
    } finally {
      setIsLoading(false);
    }
  }, [page, debouncedQ, brandFilter, categoryFilter, statusFilter]);

  useEffect(() => { void load(); }, [load]);
  useEffect(() => { setPage(1); }, [debouncedQ, brandFilter, categoryFilter, statusFilter]);

  const handleArchive = async () => {
    if (!archiveTarget) return;
    try {
      await archiveProductReference(archiveTarget.id);
      setArchiveTarget(null);
      void load();
    } catch (err) {
      console.error('[produits] archiveProductReference failed', err);
      setError("Impossible d'archiver ce produit.");
      setArchiveTarget(null);
    }
  };

  const handleAiRun = async () => {
    const limit = Math.min(500, Math.max(1, Number.isFinite(aiLimit) ? aiLimit : 100));
    setAiLimit(limit);
    setIsAiRunning(true);
    setAiError(null);
    setAiResult(null);
    try {
      const result = await runProductAiEnrichment({ limit });
      setAiResult(result);
      void load();
    } catch (err) {
      console.error('[produits] runProductAiEnrichment failed', err);
      setAiError('Impossible de lancer la recherche IA.');
    } finally {
      setIsAiRunning(false);
    }
  };

  const columns: Column<ProductReference>[] = [
    {
      key: 'name_fr',
      label: 'Produit',
      sortable: true,
      render: (row) => (
        <div>
          <div className={cn('font-medium', row.status === 'archived' && 'text-muted')}>
            {row.name_fr}
          </div>
          {row.variant_fr && <div className="text-xs text-muted">{row.variant_fr}</div>}
        </div>
      ),
    },
    { key: 'brand_name', label: 'Marque', sortable: true },
    { key: 'category_name_fr', label: 'Catégorie', sortable: true },
    { key: 'unit', label: 'Unité' },
    {
      key: 'status',
      label: 'Statut',
      sortable: true,
      render: (row) => (
        <span
          className={cn(
            'rounded-full px-2 py-0.5 text-xs font-semibold',
            STATUS_STYLES[row.status] ?? 'bg-gray-100 text-gray-600',
          )}
        >
          {STATUS_LABELS[row.status] ?? row.status}
        </span>
      ),
    },
    {
      key: 'actions',
      label: '',
      render: (row) =>
        row.status !== 'archived' ? (
          <div className="flex justify-end gap-2">
            <button
              onClick={() => { setEditTarget(row); setDrawerOpen(true); }}
              className="text-xs text-muted hover:text-ink"
            >
              ✏
            </button>
            <button
              onClick={() => setArchiveTarget(row)}
              className="text-xs text-muted hover:text-danger"
            >
              ⊘
            </button>
          </div>
        ) : null,
    },
  ];

  return (
    <div>
      <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-h1 font-black">Produits référentiel</h1>
        <Button
          size="md"
          className="w-full sm:w-auto"
          onClick={() => { setEditTarget(null); setDrawerOpen(true); }}
        >
          + Nouveau produit
        </Button>
      </div>
      <section className="mb-4 rounded-xl border border-line bg-card p-4">
        <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h2 className="text-base font-black">Recherche IA</h2>
            <p className="mt-1 text-sm text-muted">
              Enrichissement des produits incomplets du référentiel.
            </p>
          </div>
          <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
            <label className="text-sm font-semibold text-ink">
              Nombre de produits à rechercher par IA
              <input
                type="number"
                min={1}
                max={500}
                value={aiLimit}
                onChange={(e) => setAiLimit(Number(e.target.value))}
                className="mt-1 w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 sm:w-40"
              />
            </label>
            <Button
              size="md"
              className="w-full sm:w-auto"
              disabled={isAiRunning}
              onClick={() => void handleAiRun()}
            >
              <Sparkles aria-hidden="true" size={16} />
              {isAiRunning ? 'Recherche en cours…' : 'Lancer la recherche IA'}
            </Button>
          </div>
        </div>
        {aiResult && (
          <div className="mt-3 grid gap-2 text-sm sm:grid-cols-3 lg:grid-cols-6">
            <span className="rounded-md bg-soft px-3 py-2 font-semibold">{aiResult.jobs_created} jobs créés</span>
            <span className="rounded-md bg-soft px-3 py-2 font-semibold">{aiResult.jobs_submitted} jobs soumis</span>
            <span className="rounded-md bg-soft px-3 py-2 font-semibold">{aiResult.jobs_applied_total} appliqués</span>
            <span className="rounded-md bg-soft px-3 py-2 font-semibold">{aiResult.jobs_failed_total} échoués</span>
            <span className="rounded-md bg-soft px-3 py-2 font-semibold">
              {aiResult.active_batches_checked} batch{aiResult.active_batches_checked > 1 ? 's' : ''} vérifié{aiResult.active_batches_checked > 1 ? 's' : ''}
            </span>
            <span className={cn('rounded-md px-3 py-2 font-semibold', aiResult.openai_skipped ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700')}>
              {aiResult.openai_skipped ? 'OpenAI non configuré' : 'OpenAI soumis'}
            </span>
          </div>
        )}
        {aiError && (
          <div className="mt-3 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
            {aiError}
          </div>
        )}
      </section>
      <div className="mb-4 flex flex-wrap gap-3">
        <input
          type="text"
          placeholder="Rechercher (nom, code-barres)…"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          className="w-full max-w-xs rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        />
        <select
          value={brandFilter}
          onChange={(e) => setBrandFilter(e.target.value)}
          className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary sm:w-auto"
        >
          <option value="">Toutes les marques</option>
          {filterBrands.map((b) => <option key={b.id} value={b.id}>{b.canonical_name}</option>)}
        </select>
        <select
          value={categoryFilter}
          onChange={(e) => setCategoryFilter(e.target.value)}
          className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary sm:w-auto"
        >
          <option value="">Toutes les catégories</option>
          {filterCategories.map((c) => <option key={c.id} value={c.id}>{c.name_fr}</option>)}
        </select>
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary sm:w-auto"
        >
          <option value="">Tous les statuts</option>
          <option value="draft">Brouillon</option>
          <option value="pending_review">À valider</option>
          <option value="approved">Approuvé</option>
          <option value="rejected">Rejeté</option>
          <option value="archived">Archivé</option>
        </select>
      </div>
      {error && (
        <div className="mb-4 flex items-center gap-3 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          <span className="flex-1">{error}</span>
          <button onClick={() => void load()} className="shrink-0 font-semibold underline">
            Réessayer
          </button>
        </div>
      )}
      <AdminTable
        columns={columns}
        data={sorted}
        isLoading={isLoading}
        emptyMessage="Aucun produit trouvé."
        emptyAction={{
          label: '+ Créer le premier produit',
          onClick: () => { setEditTarget(null); setDrawerOpen(true); },
        }}
        pagination={{ page, total, limit: 20, onPageChange: setPage }}
        sortKey={sortKey as string | null}
        sortDir={sortDir}
        onSort={(key) => toggleSort(key as keyof ProductReference)}
      />
      {drawerOpen && (
        <ProductReferenceDrawer
          open={drawerOpen}
          onClose={() => { setDrawerOpen(false); setEditTarget(null); }}
          product={editTarget}
          onSaved={() => { setDrawerOpen(false); setEditTarget(null); void load(); }}
        />
      )}
      <AdminConfirmDialog
        open={!!archiveTarget}
        onClose={() => setArchiveTarget(null)}
        onConfirm={handleArchive}
        title="Archiver le produit"
        message={`Archiver "${archiveTarget?.name_fr}" ? Le produit ne sera plus modifiable.`}
        confirmLabel="Archiver"
        variant="warning"
      />
    </div>
  );
}
