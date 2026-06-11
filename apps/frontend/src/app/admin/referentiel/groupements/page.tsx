'use client';

import { useCallback, useEffect, useState } from 'react';
import { Archive, Eye, Pencil, Plus, Send, Trash2 } from 'lucide-react';
import { AdminDrawer } from '@/components/admin/ui/AdminDrawer';
import { AdminTable, type Column } from '@/components/admin/ui/AdminTable';
import { Button } from '@/components/ui/Button';
import {
  addProductGroupItem,
  archiveProductGroup,
  createProductGroup,
  deleteProductGroupItem,
  getProductGroup,
  listProductGroups,
  publishProductGroup,
  updateProductGroup,
} from '@/lib/services/admin/product-groups.service';
import { listProductReferences } from '@/lib/services/admin/product-references.service';
import type {
  ProductGroup,
  ProductGroupItem,
  ProductReference,
} from '@/lib/types/admin/referentiel.types';
import { cn } from '@/lib/cn';

const PAGE_SIZE = 20;

const STATUS_LABELS: Record<string, string> = {
  draft: 'Brouillon',
  published: 'Publié',
  archived: 'Archivé',
};

const STATUS_STYLES: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-700',
  published: 'bg-green-100 text-green-700',
  archived: 'bg-gray-50 text-gray-400',
};

const IMPORTANCE_LABELS: Record<string, string> = {
  required: 'Obligatoire',
  recommended: 'Recommandé',
  optional: 'Optionnel',
};

function isDuplicateError(error: unknown): boolean {
  const detail = (error as { response?: { data?: { detail?: string } } })?.response?.data?.detail;
  return typeof detail === 'string' && detail.includes('ADMIN_PRODUCT_GROUP_ITEM_DUPLICATE');
}

export default function GroupementsPage() {
  const [groups, setGroups] = useState<ProductGroup[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editTarget, setEditTarget] = useState<ProductGroup | null>(null);
  const [detailGroup, setDetailGroup] = useState<ProductGroup | null>(null);
  const [detailError, setDetailError] = useState<string | null>(null);
  const [productQuery, setProductQuery] = useState('');
  const [productResults, setProductResults] = useState<ProductReference[]>([]);
  const [itemError, setItemError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await listProductGroups({
        q: search || undefined,
        status: statusFilter || undefined,
        marketCountry: 'TN',
        page,
        limit: PAGE_SIZE,
      });
      setGroups(data.items);
      setTotal(data.total);
    } catch (err) {
      console.error('[groupements] listProductGroups failed', err);
      setError('Impossible de charger les groupements.');
    } finally {
      setIsLoading(false);
    }
  }, [page, search, statusFilter]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    setPage(1);
  }, [search, statusFilter]);

  useEffect(() => {
    if (!detailGroup || !productQuery.trim()) {
      setProductResults([]);
      return;
    }

    let cancelled = false;
    void listProductReferences({ q: productQuery.trim(), limit: 10 })
      .then((data) => {
        if (!cancelled) {
          setProductResults(data.items);
        }
      })
      .catch((err) => {
        console.error('[groupements] listProductReferences failed', err);
        if (!cancelled) {
          setItemError('Impossible de rechercher les produits.');
        }
      });

    return () => {
      cancelled = true;
    };
  }, [detailGroup, productQuery]);

  const openCreateDrawer = () => {
    setEditTarget(null);
    setDrawerOpen(true);
  };

  const openEditDrawer = (group: ProductGroup) => {
    setEditTarget(group);
    setDrawerOpen(true);
  };

  const openDetail = async (group: ProductGroup) => {
    setDetailError(null);
    setItemError(null);
    setProductQuery('');
    setProductResults([]);
    try {
      const detail = await getProductGroup(group.id);
      setDetailGroup(detail);
    } catch (err) {
      console.error('[groupements] getProductGroup failed', err);
      setDetailError('Impossible de charger le détail du groupement.');
    }
  };

  const refreshDetail = async (groupId: string) => {
    const detail = await getProductGroup(groupId);
    setDetailGroup(detail);
  };

  const handlePublish = async (group: ProductGroup) => {
    setError(null);
    try {
      await publishProductGroup(group.id);
      await load();
      if (detailGroup?.id === group.id) {
        await refreshDetail(group.id);
      }
    } catch (err) {
      console.error('[groupements] publishProductGroup failed', err);
      setError('Impossible de publier ce groupement.');
    }
  };

  const handleArchive = async (group: ProductGroup) => {
    setError(null);
    try {
      await archiveProductGroup(group.id);
      await load();
      if (detailGroup?.id === group.id) {
        await refreshDetail(group.id);
      }
    } catch (err) {
      console.error('[groupements] archiveProductGroup failed', err);
      setError("Impossible d'archiver ce groupement.");
    }
  };

  const handleAddProduct = async (product: ProductReference) => {
    if (!detailGroup) return;
    setItemError(null);
    try {
      const item = await addProductGroupItem(detailGroup.id, {
        productReferenceId: product.id,
        importance: 'recommended',
      });
      setDetailGroup({
        ...detailGroup,
        items_count: detailGroup.items_count + 1,
        items: [...(detailGroup.items ?? []), item].sort((a, b) => a.sort_order - b.sort_order),
      });
    } catch (err) {
      console.error('[groupements] addProductGroupItem failed', err);
      setItemError(isDuplicateError(err)
        ? 'Ce produit est déjà dans le groupement.'
        : "Impossible d'ajouter ce produit.");
    }
  };

  const handleDeleteItem = async (item: ProductGroupItem) => {
    if (!detailGroup) return;
    setItemError(null);
    try {
      await deleteProductGroupItem(detailGroup.id, item.id);
      setDetailGroup({
        ...detailGroup,
        items_count: Math.max(0, detailGroup.items_count - 1),
        items: (detailGroup.items ?? []).filter((current) => current.id !== item.id),
      });
    } catch (err) {
      console.error('[groupements] deleteProductGroupItem failed', err);
      setItemError('Impossible de retirer ce produit.');
    }
  };

  const columns: Column<ProductGroup>[] = [
    {
      key: 'name_fr',
      label: 'Groupement',
      render: (row) => (
        <div>
          <p className="font-semibold text-ink">{row.name_fr}</p>
          <p className="text-xs text-muted">{row.slug}</p>
        </div>
      ),
    },
    {
      key: 'status',
      label: 'Statut',
      render: (row) => (
        <span className={cn('rounded-full px-2 py-0.5 text-xs font-semibold', STATUS_STYLES[row.status] ?? STATUS_STYLES.draft)}>
          {STATUS_LABELS[row.status] ?? row.status}
        </span>
      ),
    },
    { key: 'market_country', label: 'Pays' },
    { key: 'items_count', label: 'Produits' },
    { key: 'sort_order', label: 'Ordre' },
    {
      key: 'actions',
      label: '',
      render: (row) => (
        <div className="flex justify-end gap-1">
          <button
            type="button"
            aria-label={`Voir le détail ${row.name_fr}`}
            onClick={() => void openDetail(row)}
            className="rounded p-1.5 text-muted hover:bg-primary-50 hover:text-primary"
          >
            <Eye size={16} aria-hidden="true" />
          </button>
          <button
            type="button"
            aria-label={`Modifier ${row.name_fr}`}
            onClick={() => openEditDrawer(row)}
            className="rounded p-1.5 text-muted hover:bg-primary-50 hover:text-primary"
          >
            <Pencil size={16} aria-hidden="true" />
          </button>
          <button
            type="button"
            aria-label={`Publier ${row.name_fr}`}
            onClick={() => void handlePublish(row)}
            className="rounded p-1.5 text-muted hover:bg-green-50 hover:text-green-700"
          >
            <Send size={16} aria-hidden="true" />
          </button>
          <button
            type="button"
            aria-label={`Archiver ${row.name_fr}`}
            onClick={() => void handleArchive(row)}
            className="rounded p-1.5 text-muted hover:bg-gray-100 hover:text-gray-700"
          >
            <Archive size={16} aria-hidden="true" />
          </button>
        </div>
      ),
    },
  ];

  return (
    <div>
      <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-h1 font-black">Groupements</h1>
        <Button size="md" className="w-full sm:w-auto" onClick={openCreateDrawer}>
          <Plus size={16} aria-hidden="true" />
          Nouveau groupement
        </Button>
      </div>

      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <input
          type="search"
          placeholder="Rechercher un groupement…"
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          className="w-full max-w-sm rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        />
        <select
          aria-label="Filtrer par statut"
          value={statusFilter}
          onChange={(event) => setStatusFilter(event.target.value)}
          className="w-full max-w-xs rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 sm:w-48"
        >
          <option value="">Tous les statuts</option>
          <option value="draft">Brouillon</option>
          <option value="published">Publié</option>
          <option value="archived">Archivé</option>
        </select>
      </div>

      {error && (
        <div role="alert" className="mb-4 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          {error}
        </div>
      )}

      <AdminTable
        columns={columns}
        data={groups}
        isLoading={isLoading}
        emptyMessage="Aucun groupement trouvé."
        emptyAction={{ label: 'Créer le premier groupement', onClick: openCreateDrawer }}
        pagination={{ page, total, limit: PAGE_SIZE, onPageChange: setPage }}
      />

      {detailError && (
        <div role="alert" className="mt-4 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          {detailError}
        </div>
      )}

      {detailGroup && (
        <section
          role="region"
          aria-label={`Détail ${detailGroup.name_fr}`}
          className="mt-6 border-t border-line pt-5"
        >
          <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <h2 className="text-xl font-black text-ink">{detailGroup.name_fr}</h2>
              <p className="text-sm text-muted">
                {detailGroup.items_count} produit{detailGroup.items_count !== 1 ? 's' : ''} référentiel
              </p>
            </div>
            <button
              type="button"
              onClick={() => setDetailGroup(null)}
              className="text-sm font-semibold text-muted hover:text-ink"
            >
              Fermer
            </button>
          </div>

          <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(280px,360px)]">
            <div className="overflow-hidden rounded-lg border border-line">
              <table className="w-full min-w-[560px] text-sm">
                <thead className="bg-soft text-xs uppercase text-muted">
                  <tr>
                    <th className="px-4 py-3 text-left">Produit</th>
                    <th className="px-4 py-3 text-left">Importance</th>
                    <th className="px-4 py-3 text-left">Ordre</th>
                    <th className="px-4 py-3 text-right"></th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-line">
                  {(detailGroup.items ?? []).length === 0 ? (
                    <tr>
                      <td className="px-4 py-8 text-center text-muted" colSpan={4}>
                        Aucun produit dans ce groupement.
                      </td>
                    </tr>
                  ) : (
                    (detailGroup.items ?? []).map((item) => (
                      <tr key={item.id}>
                        <td className="px-4 py-3">
                          <p className="font-semibold text-ink">{item.product_reference.name_fr}</p>
                          <p className="text-xs text-muted">
                            {item.product_reference.brand_name} · {item.product_reference.category_name_fr}
                          </p>
                        </td>
                        <td className="px-4 py-3">{IMPORTANCE_LABELS[item.importance] ?? item.importance}</td>
                        <td className="px-4 py-3">{item.sort_order}</td>
                        <td className="px-4 py-3 text-right">
                          <button
                            type="button"
                            aria-label={`Retirer ${item.product_reference.name_fr}`}
                            onClick={() => void handleDeleteItem(item)}
                            className="rounded p-1.5 text-muted hover:bg-red-50 hover:text-red-700"
                          >
                            <Trash2 size={16} aria-hidden="true" />
                          </button>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>

            <div>
              <label htmlFor="product-reference-search" className="mb-1 block text-sm font-semibold">
                Ajouter un produit
              </label>
              <input
                id="product-reference-search"
                type="search"
                placeholder="Rechercher un produit référentiel…"
                value={productQuery}
                onChange={(event) => setProductQuery(event.target.value)}
                className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
              />
              {itemError && (
                <div role="alert" className="mt-3 rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
                  {itemError}
                </div>
              )}
              <div className="mt-3 divide-y divide-line rounded-lg border border-line">
                {productResults.length === 0 ? (
                  <p className="px-3 py-4 text-sm text-muted">Aucun résultat.</p>
                ) : (
                  productResults.map((product) => (
                    <div key={product.id} className="flex items-center justify-between gap-3 px-3 py-2">
                      <div className="min-w-0">
                        <p className="truncate text-sm font-semibold text-ink">{product.name_fr}</p>
                        <p className="truncate text-xs text-muted">{product.brand_name}</p>
                      </div>
                      <button
                        type="button"
                        aria-label={`Ajouter ${product.name_fr}`}
                        onClick={() => void handleAddProduct(product)}
                        className="shrink-0 rounded p-1.5 text-primary hover:bg-primary-50"
                      >
                        <Plus size={16} aria-hidden="true" />
                      </button>
                    </div>
                  ))
                )}
              </div>
            </div>
          </div>
        </section>
      )}

      <ProductGroupDrawer
        open={drawerOpen}
        group={editTarget}
        onClose={() => {
          setDrawerOpen(false);
          setEditTarget(null);
        }}
        onSaved={() => {
          setDrawerOpen(false);
          setEditTarget(null);
          void load();
        }}
      />
    </div>
  );
}

interface ProductGroupDrawerProps {
  open: boolean;
  group: ProductGroup | null;
  onClose: () => void;
  onSaved: () => void;
}

function ProductGroupDrawer({ open, group, onClose, onSaved }: ProductGroupDrawerProps) {
  const [nameFr, setNameFr] = useState('');
  const [nameAr, setNameAr] = useState('');
  const [slug, setSlug] = useState('');
  const [descriptionFr, setDescriptionFr] = useState('');
  const [visibility, setVisibility] = useState<'merchant' | 'admin_only'>('merchant');
  const [icon, setIcon] = useState('');
  const [sortOrder, setSortOrder] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (group) {
      setNameFr(group.name_fr);
      setNameAr(group.name_ar ?? '');
      setSlug(group.slug);
      setDescriptionFr(group.description_fr ?? '');
      setVisibility(group.visibility === 'admin_only' ? 'admin_only' : 'merchant');
      setIcon(group.icon ?? '');
      setSortOrder(group.sort_order);
    } else {
      setNameFr('');
      setNameAr('');
      setSlug('');
      setDescriptionFr('');
      setVisibility('merchant');
      setIcon('');
      setSortOrder(0);
    }
    setError(null);
  }, [group, open]);

  const handleSubmit = async () => {
    if (!nameFr.trim()) {
      setError('Le nom FR est obligatoire.');
      return;
    }

    setIsSubmitting(true);
    setError(null);
    try {
      if (group) {
        await updateProductGroup(group.id, {
          nameFr: nameFr.trim(),
          nameAr: nameAr.trim() || null,
          slug: slug.trim(),
          descriptionFr: descriptionFr.trim() || null,
          visibility,
          icon: icon.trim() || null,
          sortOrder,
        });
      } else {
        await createProductGroup({
          nameFr: nameFr.trim(),
          nameAr: nameAr.trim() || undefined,
          slug: slug.trim() || undefined,
          descriptionFr: descriptionFr.trim() || undefined,
          visibility,
          icon: icon.trim() || undefined,
          sortOrder,
        });
      }
      onSaved();
    } catch (err) {
      console.error('[groupements] save group failed', err);
      setError('Une erreur est survenue. Réessayez.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <AdminDrawer
      open={open}
      onClose={onClose}
      title={group ? 'Modifier le groupement' : 'Nouveau groupement'}
      onSubmit={handleSubmit}
      isSubmitting={isSubmitting}
      size="lg"
    >
      <div className="space-y-4">
        {error && (
          <div role="alert" className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
            {error}
          </div>
        )}
        <div>
          <label htmlFor="product-group-name-fr" className="mb-1 block text-sm font-semibold">Nom FR</label>
          <input
            id="product-group-name-fr"
            type="text"
            value={nameFr}
            onChange={(event) => setNameFr(event.target.value)}
            maxLength={160}
            className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>
        <div>
          <label htmlFor="product-group-name-ar" className="mb-1 block text-sm font-semibold">Nom AR</label>
          <input
            id="product-group-name-ar"
            type="text"
            value={nameAr}
            onChange={(event) => setNameAr(event.target.value)}
            maxLength={160}
            dir="rtl"
            className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>
        <div>
          <label htmlFor="product-group-slug" className="mb-1 block text-sm font-semibold">Slug</label>
          <input
            id="product-group-slug"
            type="text"
            value={slug}
            onChange={(event) => setSlug(event.target.value)}
            maxLength={180}
            placeholder="auto-généré si vide"
            className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>
        <div>
          <label htmlFor="product-group-description-fr" className="mb-1 block text-sm font-semibold">Description FR</label>
          <textarea
            id="product-group-description-fr"
            value={descriptionFr}
            onChange={(event) => setDescriptionFr(event.target.value)}
            rows={3}
            className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>
        <div className="grid gap-4 sm:grid-cols-3">
          <div>
            <label htmlFor="product-group-visibility" className="mb-1 block text-sm font-semibold">Visibilité</label>
            <select
              id="product-group-visibility"
              value={visibility}
              onChange={(event) => setVisibility(event.target.value as 'merchant' | 'admin_only')}
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            >
              <option value="merchant">Marchand</option>
              <option value="admin_only">Admin seul</option>
            </select>
          </div>
          <div>
            <label htmlFor="product-group-icon" className="mb-1 block text-sm font-semibold">Icône</label>
            <input
              id="product-group-icon"
              type="text"
              value={icon}
              onChange={(event) => setIcon(event.target.value)}
              maxLength={80}
              placeholder="shopping-basket"
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>
          <div>
            <label htmlFor="product-group-sort-order" className="mb-1 block text-sm font-semibold">Ordre</label>
            <input
              id="product-group-sort-order"
              type="number"
              value={sortOrder}
              onChange={(event) => setSortOrder(Number(event.target.value))}
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>
        </div>
      </div>
    </AdminDrawer>
  );
}
