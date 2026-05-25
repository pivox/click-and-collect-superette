'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { MerchantCatalogFilters } from '@/components/merchant/catalogue/MerchantCatalogFilters';
import { MerchantCatalogTable } from '@/components/merchant/catalogue/MerchantCatalogTable';
import { Button } from '@/components/ui/Button';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import {
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

  const loadCatalog = useCallback(async () => {
    if (!merchant) return;
    setIsLoading(true);
    setError(null);
    try {
      setProducts(await listMerchantCatalog(merchant.store.id));
    } catch {
      setProducts([]);
      setError('Impossible de charger le catalogue.');
    } finally {
      setIsLoading(false);
    }
  }, [merchant]);

  useEffect(() => {
    void loadCatalog();
  }, [loadCatalog]);

  const filteredProducts = useMemo(
    () => filterMerchantCatalogProducts(products, appliedFilters),
    [appliedFilters, products],
  );

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
        <Button variant="ghost" size="md" onClick={() => void loadCatalog()}>
          Réessayer
        </Button>
      </div>

      <MerchantCatalogFilters
        filters={draftFilters}
        onFiltersChange={setDraftFilters}
        onSubmit={() => setAppliedFilters(draftFilters)}
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
          <MerchantCatalogTable products={filteredProducts} />
        )}
      </section>
    </div>
  );
}
