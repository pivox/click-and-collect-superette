'use client';
import { useState, useEffect, useCallback } from 'react';
import { ArrowUpDown, GitMerge, RefreshCw, Sparkles, Check, X } from 'lucide-react';
import { AdminTable, type Column } from '@/components/admin/ui/AdminTable';
import { AdminConfirmDialog } from '@/components/admin/ui/AdminConfirmDialog';
import { BulkActionBar } from '@/components/admin/ui/BulkActionBar';
import { BulkActionDialog, type BulkAction, type BulkResult } from '@/components/admin/ui/BulkActionDialog';
import { ProductReferenceDrawer } from '@/components/admin/referentiel/produits/ProductReferenceDrawer';
import { Button } from '@/components/ui/Button';
import { useSort } from '@/lib/hooks/useSort';
import {
  listProductReferences,
  archiveProductReference,
  approveProductReference,
  rejectProductReference,
  compareProductReferences,
  listProductReferenceDuplicateCandidates,
  mergeProductReference,
  toResponsiveProductImage,
} from '@/lib/services/admin/product-references.service';
import { ProductThumbnail } from '@/components/product/ProductThumbnail';
import { runProductAiEnrichment } from '@/lib/services/admin/product-ai-enrichment.service';
import { listBrands } from '@/lib/services/admin/brands.service';
import { listCategories } from '@/lib/services/admin/categories.service';
import type {
  ProductReference,
  Brand,
  Category,
  ProductReferenceComparison,
  ProductReferenceDuplicateCandidate,
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

const QUALITY_STYLES: Record<string, string> = {
  good: 'bg-green-100 text-green-700',
  medium: 'bg-yellow-100 text-yellow-700',
  low: 'bg-red-100 text-red-700',
};

type QualityFilter = '' | 'low' | 'medium' | 'good';
type QualitySortDirection = '' | 'asc' | 'desc';

function qualityBounds(filter: QualityFilter): {
  qualityScoreMin?: number;
  qualityScoreMax?: number;
} {
  if (filter === 'good') {
    return { qualityScoreMin: 80 };
  }
  if (filter === 'medium') {
    return { qualityScoreMin: 60, qualityScoreMax: 79 };
  }
  if (filter === 'low') {
    return { qualityScoreMax: 59 };
  }

  return {};
}

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
  const [qualityFilter, setQualityFilter] = useState<QualityFilter>('');
  const [qualitySortDir, setQualitySortDir] = useState<QualitySortDirection>('');
  const [filterBrands, setFilterBrands] = useState<Brand[]>([]);
  const [filterCategories, setFilterCategories] = useState<Category[]>([]);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editTarget, setEditTarget] = useState<ProductReference | null>(null);
  const [archiveTarget, setArchiveTarget] = useState<ProductReference | null>(null);
  const [rejectTarget, setRejectTarget] = useState<ProductReference | null>(null);
  const [rejectionReason, setRejectionReason] = useState('');
  const [approvalInProgress, setApprovalInProgress] = useState<string | null>(null);
  const [rejectionInProgress, setRejectionInProgress] = useState<string | null>(null);
  const [aiLimit, setAiLimit] = useState(100);
  const [aiResult, setAiResult] = useState<ProductAiEnrichmentRunResult | null>(null);
  const [aiError, setAiError] = useState<string | null>(null);
  const [isAiRunning, setIsAiRunning] = useState(false);
  const [duplicateCandidates, setDuplicateCandidates] = useState<ProductReferenceDuplicateCandidate[]>([]);
  const [duplicateTotal, setDuplicateTotal] = useState(0);
  const [isDuplicateLoading, setIsDuplicateLoading] = useState(false);
  const [duplicateError, setDuplicateError] = useState<string | null>(null);
  const [comparison, setComparison] = useState<ProductReferenceComparison | null>(null);
  const [isComparisonLoading, setIsComparisonLoading] = useState(false);
  const [mergeError, setMergeError] = useState<string | null>(null);
  const [isMerging, setIsMerging] = useState(false);
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [bulkAction, setBulkAction] = useState<BulkAction | null>(null);
  const [bulkResult, setBulkResult] = useState<BulkResult | null>(null);
  const [isBulkBusy, setIsBulkBusy] = useState(false);

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
      const quality = qualityBounds(qualityFilter);
      const data = await listProductReferences({
        q: debouncedQ || undefined,
        brand: brandFilter || undefined,
        category: categoryFilter || undefined,
        status: statusFilter || undefined,
        ...quality,
        sort: qualitySortDir ? 'quality_score' : undefined,
        direction: qualitySortDir || undefined,
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
  }, [page, debouncedQ, brandFilter, categoryFilter, statusFilter, qualityFilter, qualitySortDir]);

  const loadDuplicateCandidates = useCallback(async () => {
    setIsDuplicateLoading(true);
    setDuplicateError(null);
    try {
      const data = await listProductReferenceDuplicateCandidates({ page: 1, limit: 5 });
      setDuplicateCandidates(data.items);
      setDuplicateTotal(data.total);
    } catch (err) {
      console.error('[produits] listProductReferenceDuplicateCandidates failed', err);
      setDuplicateError('Impossible de charger les doublons candidats.');
    } finally {
      setIsDuplicateLoading(false);
    }
  }, []);

  useEffect(() => { void load(); }, [load]);
  useEffect(() => { void loadDuplicateCandidates(); }, [loadDuplicateCandidates]);
  useEffect(() => { setPage(1); }, [debouncedQ, brandFilter, categoryFilter, statusFilter, qualityFilter, qualitySortDir]);
  useEffect(() => { setSelectedIds(new Set()); }, [page, debouncedQ, brandFilter, categoryFilter, statusFilter, qualityFilter, qualitySortDir]);

  const handleTableSort = (key: string) => {
    if (key === 'quality_score') {
      setQualitySortDir((current) => (current === 'asc' ? 'desc' : 'asc'));
      return;
    }

    setQualitySortDir('');
    toggleSort(key as keyof ProductReference);
  };

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

  const handleApprove = async (id: string) => {
    setApprovalInProgress(id);
    setError(null);
    try {
      await approveProductReference(id);
      void load();
    } catch (err) {
      console.error('[produits] approveProductReference failed', err);
      setError("Impossible d'approuver ce produit.");
    } finally {
      setApprovalInProgress(null);
    }
  };

  const handleRejectSubmit = async () => {
    if (!rejectTarget || !rejectionReason.trim()) return;
    setRejectionInProgress(rejectTarget.id);
    setError(null);
    try {
      await rejectProductReference(rejectTarget.id, rejectionReason.trim());
      setRejectTarget(null);
      setRejectionReason('');
      void load();
    } catch (err) {
      console.error('[produits] rejectProductReference failed', err);
      setError('Impossible de rejeter ce produit.');
    } finally {
      setRejectionInProgress(null);
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

  const handleCompare = async (candidate: ProductReferenceDuplicateCandidate) => {
    setIsComparisonLoading(true);
    setMergeError(null);
    try {
      const data = await compareProductReferences(candidate.left.id, candidate.right.id);
      setComparison(data);
    } catch (err) {
      console.error('[produits] compareProductReferences failed', err);
      setMergeError('Impossible de charger la comparaison.');
    } finally {
      setIsComparisonLoading(false);
    }
  };

  const handleMerge = async (absorbedId: string, keptId: string) => {
    setIsMerging(true);
    setMergeError(null);
    try {
      await mergeProductReference(absorbedId, keptId);
      setComparison(null);
      await Promise.all([load(), loadDuplicateCandidates()]);
    } catch (err) {
      console.error('[produits] mergeProductReference failed', err);
      setMergeError('Impossible de fusionner ces références.');
    } finally {
      setIsMerging(false);
    }
  };

  const handleSelectionChange = (id: string) => {
    if (id === '__all__') {
      const currentIds = (qualitySortDir ? products : sorted).map((p) => p.id);
      setSelectedIds((prev) =>
        prev.size === currentIds.length ? new Set() : new Set(currentIds),
      );
    } else {
      setSelectedIds((prev) => {
        const next = new Set(prev);
        if (next.has(id)) {
          next.delete(id);
        } else {
          next.add(id);
        }
        return next;
      });
    }
  };

  const displayedProducts = qualitySortDir ? products : sorted;
  const selectedProducts = displayedProducts.filter((p) => selectedIds.has(p.id));
  const eligibleApprove = selectedProducts.filter((p) => p.status !== 'approved' && p.status !== 'archived').length;
  const eligibleArchive = selectedProducts.filter((p) => p.status !== 'archived').length;
  const eligibleReject = selectedProducts.filter((p) => p.status !== 'rejected' && p.status !== 'archived').length;

  const handleBulkConfirm = async (reason?: string) => {
    if (!bulkAction) return;
    setIsBulkBusy(true);
    setBulkResult(null);
    const eligibilityFn: Record<BulkAction, (p: ProductReference) => boolean> = {
      approve: (p) => p.status !== 'approved' && p.status !== 'archived',
      archive: (p) => p.status !== 'archived',
      reject: (p) => p.status !== 'rejected' && p.status !== 'archived',
    };
    const eligible = selectedProducts.filter(eligibilityFn[bulkAction]);
    const ignored = selectedProducts.length - eligible.length;
    const results = await Promise.allSettled(
      eligible.map((p) => {
        if (bulkAction === 'approve') return approveProductReference(p.id);
        if (bulkAction === 'archive') return archiveProductReference(p.id);
        return rejectProductReference(p.id, reason!);
      }),
    );
    const succeeded = results.filter((r) => r.status === 'fulfilled').length;
    const failed = results.filter((r) => r.status === 'rejected').length;
    setBulkResult({ succeeded, failed, ignored });
    setIsBulkBusy(false);
    setSelectedIds(new Set());
    void load();
  };

  const columns: Column<ProductReference>[] = [
    {
      key: 'image',
      label: '',
      render: (row) => (
        <ProductThumbnail
          image={toResponsiveProductImage(row.image)}
          nameFr={row.name_fr}
          sizes="40px"
          className="h-10 w-10 rounded-md text-base"
        />
      ),
    },
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
    {
      key: 'quality_score',
      label: 'Qualité',
      sortable: true,
      render: (row) => (
        <span
          className={cn(
            'inline-flex min-w-12 items-center justify-center rounded-full px-2 py-0.5 text-xs font-bold',
            QUALITY_STYLES[row.quality_level] ?? QUALITY_STYLES.low,
          )}
        >
          {row.quality_score}
        </span>
      ),
    },
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
          <div className="flex justify-end gap-1.5">
            {row.status !== 'approved' && (
              <button
                onClick={() => void handleApprove(row.id)}
                disabled={approvalInProgress === row.id}
                className="rounded p-1 text-muted hover:bg-green-50 hover:text-green-700 disabled:opacity-50"
                title="Approuver"
              >
                <Check size={16} />
              </button>
            )}
            {row.status !== 'rejected' && (
              <button
                onClick={() => setRejectTarget(row)}
                disabled={rejectionInProgress === row.id}
                className="rounded p-1 text-muted hover:bg-red-50 hover:text-red-700 disabled:opacity-50"
                title="Rejeter"
              >
                <X size={16} />
              </button>
            )}
            <button
              onClick={() => { setEditTarget(row); setDrawerOpen(true); }}
              className="rounded p-1 text-muted hover:bg-blue-50 hover:text-blue-700"
              title="Éditer"
            >
              ✏
            </button>
            <button
              onClick={() => setArchiveTarget(row)}
              className="rounded p-1 text-muted hover:bg-gray-100 hover:text-gray-700"
              title="Archiver"
            >
              ⊗
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
      <section className="mb-4 rounded-xl border border-line bg-card p-4">
        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h2 className="text-base font-black">Doublons candidats</h2>
            <p className="mt-1 text-sm text-muted">
              Détection manuelle par code-barres puis identité produit normalisée.
            </p>
          </div>
          <Button
            size="md"
            variant="ghost"
            disabled={isDuplicateLoading}
            onClick={() => void loadDuplicateCandidates()}
          >
            <RefreshCw aria-hidden="true" size={16} />
            Actualiser
          </Button>
        </div>
        <div className="mt-3 space-y-2">
          {duplicateCandidates.map((candidate) => (
            <button
              key={`${candidate.left.id}-${candidate.right.id}`}
              type="button"
              onClick={() => void handleCompare(candidate)}
              className="grid w-full gap-2 rounded-md border border-line px-3 py-2 text-left text-sm hover:border-primary hover:bg-soft md:grid-cols-[1fr_auto_1fr_auto]"
            >
              <span>
                <span className="block font-semibold text-ink">{candidate.left.name_fr}</span>
                <span className="text-muted">{candidate.left.brand_name} · {candidate.left_offer_count} offre{candidate.left_offer_count > 1 ? 's' : ''}</span>
              </span>
              <span className={cn('rounded-full px-2 py-0.5 text-xs font-semibold', candidate.reason === 'barcode' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700')}>
                {candidate.reason === 'barcode' ? 'Code-barres' : 'Identité'}
              </span>
              <span>
                <span className="block font-semibold text-ink">{candidate.right.name_fr}</span>
                <span className="text-muted">{candidate.right.brand_name} · {candidate.right_offer_count} offre{candidate.right_offer_count > 1 ? 's' : ''}</span>
              </span>
              <span className="inline-flex items-center gap-1 self-center font-semibold text-primary">
                <GitMerge aria-hidden="true" size={16} />
                Comparer
              </span>
            </button>
          ))}
          {!isDuplicateLoading && duplicateCandidates.length === 0 && (
            <div className="rounded-md bg-soft px-3 py-2 text-sm text-muted">
              Aucun doublon candidat détecté.
            </div>
          )}
          {isDuplicateLoading && (
            <div className="rounded-md bg-soft px-3 py-2 text-sm text-muted">Recherche des doublons…</div>
          )}
          {duplicateTotal > duplicateCandidates.length && (
            <div className="text-xs text-muted">
              {duplicateTotal} candidats détectés au total, les 5 premiers sont affichés.
            </div>
          )}
          {duplicateError && (
            <div className="rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
              {duplicateError}
            </div>
          )}
        </div>
        {(comparison || isComparisonLoading || mergeError) && (
          <div className="mt-4 rounded-md border border-line p-3">
            {isComparisonLoading && <div className="text-sm text-muted">Chargement de la comparaison…</div>}
            {comparison && (
              <div className="grid gap-3 lg:grid-cols-2">
                {[comparison.left, comparison.right].map((item) => {
                  const offerCount = item.id === comparison.left.id ? comparison.left_offer_count : comparison.right_offer_count;
                  const other = item.id === comparison.left.id ? comparison.right : comparison.left;
                  return (
                    <div key={item.id} className="rounded-md bg-soft p-3">
                      <div className="mb-2 flex items-center gap-2">
                        <ProductThumbnail
                          image={toResponsiveProductImage(item.image)}
                          nameFr={item.name_fr}
                          sizes="40px"
                          className="h-10 w-10 rounded-md text-base"
                        />
                        <div>
                          <div className="font-black text-ink">{item.name_fr}</div>
                          <div className="text-xs text-muted">{item.brand_name} · {item.category_name_fr}</div>
                        </div>
                      </div>
                      <dl className="grid grid-cols-2 gap-2 text-xs">
                        <dt className="text-muted">Code-barres</dt>
                        <dd className="font-semibold text-ink">{item.barcode ?? '—'}</dd>
                        <dt className="text-muted">Format</dt>
                        <dd className="font-semibold text-ink">{item.volume ?? '—'} {item.unit}</dd>
                        <dt className="text-muted">Statut</dt>
                        <dd className="font-semibold text-ink">{STATUS_LABELS[item.status] ?? item.status}</dd>
                        <dt className="text-muted">Offres marchand</dt>
                        <dd className="font-semibold text-ink">{offerCount}</dd>
                      </dl>
                      <Button
                        size="md"
                        className="mt-3 w-full"
                        disabled={isMerging || item.status === 'archived' || other.status === 'archived'}
                        onClick={() => void handleMerge(other.id, item.id)}
                      >
                        <GitMerge aria-hidden="true" size={16} />
                        Garder cette référence
                      </Button>
                    </div>
                  );
                })}
              </div>
            )}
            {mergeError && (
              <div className="mt-3 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
                {mergeError}
              </div>
            )}
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
        <button
          onClick={() => setStatusFilter('pending_review')}
          className={cn(
            'rounded-md border px-3 py-2 text-sm font-semibold transition-colors',
            statusFilter === 'pending_review'
              ? 'border-primary bg-primary text-white'
              : 'border-line bg-white text-ink hover:bg-soft',
          )}
        >
          À traiter
        </button>
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
        <select
          value={qualityFilter}
          onChange={(e) => setQualityFilter(e.target.value as QualityFilter)}
          className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary sm:w-auto"
        >
          <option value="">Tous les scores</option>
          <option value="low">À compléter (&lt; 60)</option>
          <option value="medium">Moyen (60-79)</option>
          <option value="good">Bon (80-100)</option>
        </select>
        <Button
          size="md"
          variant="ghost"
          onClick={() => setQualitySortDir((current) => (current === 'asc' ? 'desc' : 'asc'))}
        >
          <ArrowUpDown aria-hidden="true" size={16} />
          Qualité {qualitySortDir === 'asc' ? '↑' : qualitySortDir === 'desc' ? '↓' : ''}
        </Button>
      </div>
      {error && (
        <div className="mb-4 flex items-center gap-3 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          <span className="flex-1">{error}</span>
          <button onClick={() => void load()} className="shrink-0 font-semibold underline">
            Réessayer
          </button>
        </div>
      )}
      {selectedIds.size > 0 && (
        <BulkActionBar
          selectedCount={selectedIds.size}
          onClear={() => setSelectedIds(new Set())}
          onApprove={() => { setBulkAction('approve'); setBulkResult(null); }}
          onArchive={() => { setBulkAction('archive'); setBulkResult(null); }}
          onReject={() => { setBulkAction('reject'); setBulkResult(null); }}
          isBusy={isBulkBusy}
          eligibleApprove={eligibleApprove}
          eligibleArchive={eligibleArchive}
          eligibleReject={eligibleReject}
        />
      )}
      <AdminTable
        columns={columns}
        data={qualitySortDir ? products : sorted}
        isLoading={isLoading}
        emptyMessage="Aucun produit trouvé."
        emptyAction={{
          label: '+ Créer le premier produit',
          onClick: () => { setEditTarget(null); setDrawerOpen(true); },
        }}
        pagination={{ page, total, limit: 20, onPageChange: setPage }}
        sortKey={qualitySortDir ? 'quality_score' : (sortKey as string | null)}
        sortDir={qualitySortDir || sortDir}
        onSort={handleTableSort}
        selectedIds={selectedIds}
        onSelectionChange={handleSelectionChange}
      />
      {drawerOpen && (
        <ProductReferenceDrawer
          open={drawerOpen}
          onClose={() => { setDrawerOpen(false); setEditTarget(null); }}
          product={editTarget}
          onSaved={() => { setDrawerOpen(false); setEditTarget(null); void load(); }}
          onChanged={() => { void load(); }}
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
      {rejectTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
          <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
            <h2 className="mb-2 text-lg font-bold text-ink">Rejeter le produit</h2>
            <p className="mb-4 text-sm text-muted">
              Rejeter &quot;{rejectTarget.name_fr}&quot; ? Indiquez une raison pour l&apos;admin.
            </p>
            <textarea
              autoFocus
              value={rejectionReason}
              onChange={(e) => setRejectionReason(e.target.value)}
              placeholder="Raison du rejet..."
              maxLength={500}
              className="mb-4 w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
              rows={4}
            />
            <div className="flex gap-3">
              <button
                onClick={() => { setRejectTarget(null); setRejectionReason(''); }}
                className="flex-1 rounded-md border border-line px-4 py-2 text-sm font-semibold hover:bg-soft"
              >
                Annuler
              </button>
              <button
                onClick={() => void handleRejectSubmit()}
                disabled={!rejectionReason.trim() || rejectionInProgress !== null}
                className="flex-1 rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
              >
                Rejeter
              </button>
            </div>
          </div>
        </div>
      )}
      <BulkActionDialog
        open={bulkAction !== null}
        action={bulkAction}
        eligibleCount={
          bulkAction === 'approve'
            ? eligibleApprove
            : bulkAction === 'archive'
              ? eligibleArchive
              : eligibleReject
        }
        ignoredCount={
          bulkAction === 'approve'
            ? selectedProducts.length - eligibleApprove
            : bulkAction === 'archive'
              ? selectedProducts.length - eligibleArchive
              : selectedProducts.length - eligibleReject
        }
        onClose={() => { setBulkAction(null); setBulkResult(null); }}
        onConfirm={handleBulkConfirm}
        result={bulkResult}
        isBusy={isBulkBusy}
      />
    </div>
  );
}
