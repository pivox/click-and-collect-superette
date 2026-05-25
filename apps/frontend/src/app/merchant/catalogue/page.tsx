'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { MerchantCatalogBulkActions } from '@/components/merchant/catalogue/MerchantCatalogBulkActions';
import { MerchantCatalogEditDrawer } from '@/components/merchant/catalogue/MerchantCatalogEditDrawer';
import { MerchantCatalogFilters } from '@/components/merchant/catalogue/MerchantCatalogFilters';
import { MerchantCatalogTable } from '@/components/merchant/catalogue/MerchantCatalogTable';
import { MerchantLocalProductDrawer } from '@/components/merchant/catalogue/MerchantLocalProductDrawer';
import { ProductReferenceSearchDrawer } from '@/components/merchant/catalogue/ProductReferenceSearchDrawer';
import { Button } from '@/components/ui/Button';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import {
  bulkUpdateMerchantProductAvailability,
  createMerchantCategory,
  filterMerchantCatalogProducts,
  listMerchantCategories,
  listMerchantCatalog,
} from '@/lib/services/merchant-catalog.service';
import type {
  MerchantCategory,
  MerchantCatalogListOptions,
  MerchantCatalogProduct,
} from '@/lib/types/merchant-catalog.types';

const defaultFilters: MerchantCatalogListOptions = {
  q: '',
  availability: 'all',
  visibility: 'all',
};

export default function MerchantCatalogPage() {
  const { merchant } = useMerchantAuth();
  const [products, setProducts] = useState<MerchantCatalogProduct[]>([]);
  const [categories, setCategories] = useState<MerchantCategory[]>([]);
  const [categoryError, setCategoryError] = useState<string | null>(null);
  const [draftFilters, setDraftFilters] = useState<MerchantCatalogListOptions>(defaultFilters);
  const [appliedFilters, setAppliedFilters] = useState<MerchantCatalogListOptions>(defaultFilters);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editProduct, setEditProduct] = useState<MerchantCatalogProduct | null>(null);
  const [isAddProductDrawerOpen, setIsAddProductDrawerOpen] = useState(false);
  const [isLocalProductDrawerOpen, setIsLocalProductDrawerOpen] = useState(false);
  const [isSelectionMode, setIsSelectionMode] = useState(false);
  const [selectedProductIds, setSelectedProductIds] = useState<string[]>([]);
  const [selectionError, setSelectionError] = useState<string | null>(null);
  const [bulkError, setBulkError] = useState<string | null>(null);
  const [bulkSuccessMessage, setBulkSuccessMessage] = useState<string | null>(null);
  const [isBulkSubmitting, setIsBulkSubmitting] = useState(false);
  const requestId = useRef(0);
  const categoryRequestId = useRef(0);

  const loadCatalog = useCallback(async () => {
    if (!merchant) return;
    const nextRequestId = requestId.current + 1;
    requestId.current = nextRequestId;
    setIsLoading(true);
    setError(null);
    try {
      const nextProducts = await listMerchantCatalog(merchant.store.id);
      if (requestId.current === nextRequestId) {
        setProducts(nextProducts);
        setSelectedProductIds([]);
      }
    } catch {
      if (requestId.current === nextRequestId) {
        setProducts([]);
        setError('Impossible de charger le catalogue.');
      }
    } finally {
      if (requestId.current === nextRequestId) {
        setIsLoading(false);
      }
    }
  }, [merchant]);

  useEffect(() => {
    void loadCatalog();
  }, [loadCatalog]);

  const loadCategories = useCallback(async () => {
    if (!merchant) return;
    const nextRequestId = categoryRequestId.current + 1;
    categoryRequestId.current = nextRequestId;
    setCategoryError(null);

    try {
      const nextCategories = await listMerchantCategories(merchant.store.id);
      if (categoryRequestId.current === nextRequestId) {
        setCategories(nextCategories);
      }
    } catch {
      if (categoryRequestId.current === nextRequestId) {
        setCategories([]);
        setCategoryError('Impossible de charger les catégories marchand.');
      }
    }
  }, [merchant]);

  useEffect(() => {
    void loadCategories();
  }, [loadCategories]);

  const filteredProducts = useMemo(
    () => filterMerchantCatalogProducts(products, appliedFilters),
    [appliedFilters, products],
  );

  const handleToggleSelectionMode = () => {
    setIsSelectionMode(true);
    setSelectedProductIds([]);
    setSelectionError(null);
    setBulkError(null);
    setBulkSuccessMessage(null);
  };

  const handleCancelSelectionMode = () => {
    setIsSelectionMode(false);
    setSelectedProductIds([]);
    setSelectionError(null);
    setBulkError(null);
    setBulkSuccessMessage(null);
  };

  const handleToggleProductSelection = (productId: string) => {
    setSelectedProductIds((currentIds) => {
      if (currentIds.includes(productId)) {
        setSelectionError(null);
        return currentIds.filter((currentId) => currentId !== productId);
      }

      if (currentIds.length >= 50) {
        setSelectionError('La sélection est limitée à 50 produits.');
        return currentIds;
      }

      setSelectionError(null);
      return [...currentIds, productId];
    });
  };

  const handleBulkAvailability = async (isAvailable: boolean) => {
    const visibleProductIds = new Set(filteredProducts.map((product) => product.id));
    const visibleSelectedProductIds = selectedProductIds.filter((productId) =>
      visibleProductIds.has(productId),
    );

    if (!merchant || visibleSelectedProductIds.length === 0) return;

    setIsBulkSubmitting(true);
    setBulkError(null);
    setBulkSuccessMessage(null);

    try {
      const result = await bulkUpdateMerchantProductAvailability(merchant.store.id, {
        merchant_product_ids: visibleSelectedProductIds,
        is_available: isAvailable,
        merchant_note: isAvailable ? null : 'Rupture temporaire',
      });
      setSelectedProductIds([]);
      setIsSelectionMode(false);
      await loadCatalog();
      setBulkSuccessMessage(
        `${result.updated_count} produit${result.updated_count > 1 ? 's' : ''} mis à jour.`,
      );
    } catch {
      setBulkError('Impossible de mettre à jour les produits sélectionnés.');
    } finally {
      setIsBulkSubmitting(false);
    }
  };

  const handleProductSaved = () => {
    setEditProduct(null);
    void loadCatalog();
  };

  const handleProductAdded = () => {
    setIsAddProductDrawerOpen(false);
    void loadCatalog();
  };

  const handleLocalProductCreated = () => {
    setIsLocalProductDrawerOpen(false);
    void loadCatalog();
  };

  const handleCreateCategory = async (nameFr: string) => {
    if (!merchant) {
      throw new Error('Missing merchant context');
    }

    const createdCategory = await createMerchantCategory(merchant.store.id, {
      name_fr: nameFr,
      name_ar: null,
      active: true,
    });

    setCategories((currentCategories) => [...currentCategories, createdCategory]);
    setCategoryError(null);

    return createdCategory;
  };

  const handleApplyFilters = () => {
    setAppliedFilters(draftFilters);
    setSelectedProductIds([]);
    setSelectionError(null);
    setBulkError(null);
    setBulkSuccessMessage(null);
  };

  return (
    <div>
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 className="text-h1 font-black">Catalogue</h1>
          <p className="mt-1 text-sm text-muted">
            Pilote les produits visibles dans la supérette, leurs prix et leur disponibilité pour
            les clients qui préparent leur Kadhia avant le retrait.
          </p>
        </div>
        <div className="flex flex-wrap gap-3">
          <Button
            size="md"
            disabled={isLoading}
            onClick={() => setIsAddProductDrawerOpen(true)}
          >
            Depuis référentiel
          </Button>
          <Button
            variant="ghost"
            size="md"
            disabled={isLoading}
            onClick={() => setIsLocalProductDrawerOpen(true)}
          >
            Produit local
          </Button>
          <Button
            variant="ghost"
            size="md"
            disabled={isLoading}
            onClick={() => void loadCatalog()}
          >
            Réessayer
          </Button>
        </div>
      </div>

      <MerchantCatalogFilters
        filters={draftFilters}
        onFiltersChange={setDraftFilters}
        onSubmit={handleApplyFilters}
      />

      <MerchantCatalogBulkActions
        isSelectionMode={isSelectionMode}
        selectedCount={selectedProductIds.length}
        selectionError={selectionError}
        bulkError={bulkError}
        isSubmitting={isBulkSubmitting}
        onEnterSelectionMode={handleToggleSelectionMode}
        onCancelSelectionMode={handleCancelSelectionMode}
        onMarkAvailable={() => void handleBulkAvailability(true)}
        onMarkUnavailable={() => void handleBulkAvailability(false)}
      />

      {error && (
        <div className="mt-4 rounded-md bg-status-cancel-bg px-4 py-3 text-sm text-status-cancel">
          {error}
        </div>
      )}

      {categoryError && (
        <div className="mt-4 rounded-md bg-soft px-4 py-3 text-sm text-muted">
          {categoryError}
        </div>
      )}

      {bulkSuccessMessage && (
        <div
          role="status"
          className="mt-4 rounded-md bg-status-ready-bg px-4 py-3 text-sm text-status-ready"
        >
          {bulkSuccessMessage}
        </div>
      )}

      <section className="mt-5 rounded-md bg-card shadow-card">
        {isLoading ? (
          <p className="p-5 text-sm text-muted">Chargement du catalogue…</p>
        ) : (
          <MerchantCatalogTable
            products={filteredProducts}
            emptyMessage={
              products.length === 0
                ? 'Aucun produit dans ce catalogue.'
                : 'Aucun produit ne correspond aux filtres.'
            }
            isSelectionMode={isSelectionMode}
            isSelectionDisabled={isBulkSubmitting}
            selectedProductIds={selectedProductIds}
            onEditProduct={setEditProduct}
            onToggleProductSelection={handleToggleProductSelection}
          />
        )}
      </section>

      <MerchantCatalogEditDrawer
        product={editProduct}
        categories={categories}
        categoryMessage={categoryError}
        onCreateCategory={merchant ? handleCreateCategory : undefined}
        onClose={() => setEditProduct(null)}
        onSaved={handleProductSaved}
      />

      <ProductReferenceSearchDrawer
        isOpen={isAddProductDrawerOpen}
        storeId={merchant?.store.id ?? null}
        categories={categories}
        categoryMessage={categoryError}
        onCreateCategory={merchant ? handleCreateCategory : undefined}
        onClose={() => setIsAddProductDrawerOpen(false)}
        onAdded={handleProductAdded}
      />

      <MerchantLocalProductDrawer
        isOpen={isLocalProductDrawerOpen}
        storeId={merchant?.store.id ?? null}
        categories={categories}
        categoryMessage={categoryError}
        onCreateCategory={merchant ? handleCreateCategory : undefined}
        onClose={() => setIsLocalProductDrawerOpen(false)}
        onCreated={handleLocalProductCreated}
      />
    </div>
  );
}
