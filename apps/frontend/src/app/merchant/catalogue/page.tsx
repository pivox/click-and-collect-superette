'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { MerchantCatalogBulkActions } from '@/components/merchant/catalogue/MerchantCatalogBulkActions';
import { MerchantCatalogEditDrawer } from '@/components/merchant/catalogue/MerchantCatalogEditDrawer';
import { MerchantCatalogFilters } from '@/components/merchant/catalogue/MerchantCatalogFilters';
import { MerchantCatalogTable } from '@/components/merchant/catalogue/MerchantCatalogTable';
import { Button } from '@/components/ui/Button';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import {
  bulkUpdateMerchantProductAvailability,
  filterMerchantCatalogProducts,
  listMerchantCatalog,
} from '@/lib/services/merchant-catalog.service';
import type {
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
  const [draftFilters, setDraftFilters] = useState<MerchantCatalogListOptions>(defaultFilters);
  const [appliedFilters, setAppliedFilters] = useState<MerchantCatalogListOptions>(defaultFilters);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editProduct, setEditProduct] = useState<MerchantCatalogProduct | null>(null);
  const [isSelectionMode, setIsSelectionMode] = useState(false);
  const [selectedProductIds, setSelectedProductIds] = useState<string[]>([]);
  const [selectionError, setSelectionError] = useState<string | null>(null);
  const [bulkError, setBulkError] = useState<string | null>(null);
  const [isBulkSubmitting, setIsBulkSubmitting] = useState(false);
  const requestId = useRef(0);

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

  const filteredProducts = useMemo(
    () => filterMerchantCatalogProducts(products, appliedFilters),
    [appliedFilters, products],
  );

  const handleToggleSelectionMode = () => {
    setIsSelectionMode(true);
    setSelectedProductIds([]);
    setSelectionError(null);
    setBulkError(null);
  };

  const handleCancelSelectionMode = () => {
    setIsSelectionMode(false);
    setSelectedProductIds([]);
    setSelectionError(null);
    setBulkError(null);
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
    if (!merchant || selectedProductIds.length === 0) return;

    setIsBulkSubmitting(true);
    setBulkError(null);

    try {
      await bulkUpdateMerchantProductAvailability(merchant.store.id, {
        merchant_product_ids: selectedProductIds,
        is_available: isAvailable,
        merchant_note: isAvailable ? null : 'Rupture temporaire',
      });
      setSelectedProductIds([]);
      setIsSelectionMode(false);
      await loadCatalog();
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
        <Button
          variant="ghost"
          size="md"
          disabled={isLoading}
          onClick={() => void loadCatalog()}
        >
          Réessayer
        </Button>
      </div>

      <MerchantCatalogFilters
        filters={draftFilters}
        onFiltersChange={setDraftFilters}
        onSubmit={() => setAppliedFilters(draftFilters)}
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
            selectedProductIds={selectedProductIds}
            onEditProduct={setEditProduct}
            onToggleProductSelection={handleToggleProductSelection}
          />
        )}
      </section>

      <MerchantCatalogEditDrawer
        product={editProduct}
        onClose={() => setEditProduct(null)}
        onSaved={handleProductSaved}
      />
    </div>
  );
}
