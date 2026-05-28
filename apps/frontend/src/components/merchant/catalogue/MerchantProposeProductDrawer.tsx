'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/Button';
import {
  createProductProposal,
  fetchGlobalBrands,
  fetchGlobalCategories,
} from '@/lib/services/merchant-catalog.service';
import type {
  GlobalBrand,
  GlobalCategory,
  MerchantCatalogProduct,
} from '@/lib/types/merchant-catalog.types';

interface MerchantProposeProductDrawerProps {
  product: MerchantCatalogProduct | null;
  storeId: string | null;
  onClose: () => void;
}

export function MerchantProposeProductDrawer({
  onClose,
  product,
  storeId,
}: MerchantProposeProductDrawerProps) {
  const [categories, setCategories] = useState<GlobalCategory[]>([]);
  const [brands, setBrands] = useState<GlobalBrand[]>([]);
  const [categoryId, setCategoryId] = useState('');
  const [categoryNameProposed, setCategoryNameProposed] = useState('');
  const [nameFr, setNameFr] = useState('');
  const [nameAr, setNameAr] = useState('');
  const [brandName, setBrandName] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [fieldWithError, setFieldWithError] = useState<'name' | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isLoadingRefs, setIsLoadingRefs] = useState(false);
  const [success, setSuccess] = useState(false);
  const dialogRef = useRef<HTMLDivElement>(null);
  const previousFocusRef = useRef<HTMLElement | null>(null);
  const sessionRef = useRef(0);
  const isOpenRef = useRef(false);

  const isOpen = product !== null && storeId !== null;

  const isCurrentSession = useCallback((sessionId: number) => {
    return isOpenRef.current && sessionRef.current === sessionId;
  }, []);

  const handleClose = useCallback(() => {
    if (isSubmitting) return;

    isOpenRef.current = false;
    sessionRef.current += 1;
    onClose();
  }, [isSubmitting, onClose]);

  useEffect(() => {
    isOpenRef.current = isOpen;
    sessionRef.current += 1;

    if (!isOpen || !product) return;

    setNameFr(product.name_fr);
    setNameAr(product.name_ar ?? '');
    setBrandName(product.brand ?? '');
    setCategoryId('');
    setCategoryNameProposed('');
    setError(null);
    setFieldWithError(null);
    setIsSubmitting(false);
    setSuccess(false);

    const sessionId = sessionRef.current;
    setIsLoadingRefs(true);

    void Promise.all([fetchGlobalCategories(), fetchGlobalBrands()])
      .then(([cats, brnds]) => {
        if (!isCurrentSession(sessionId)) return;

        setCategories(cats);
        setBrands(brnds);
      })
      .catch(() => {
        if (!isCurrentSession(sessionId)) return;

        setError('Impossible de charger les catégories ou les marques.');
      })
      .finally(() => {
        if (!isCurrentSession(sessionId)) return;

        setIsLoadingRefs(false);
      });
  }, [isOpen, product, isCurrentSession]);

  useEffect(() => {
    if (!isOpen) return;

    previousFocusRef.current =
      document.activeElement instanceof HTMLElement ? document.activeElement : null;

    return () => {
      const previousFocus = previousFocusRef.current;
      if (previousFocus && document.contains(previousFocus)) {
        previousFocus.focus();
      }
      previousFocusRef.current = null;
    };
  }, [isOpen]);

  useEffect(() => {
    if (!isOpen) return;

    const handler = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        handleClose();
        return;
      }

      if (event.key !== 'Tab') return;

      const dialog = dialogRef.current;
      if (!dialog) return;

      const focusableElements = Array.from(
        dialog.querySelectorAll<HTMLElement>(
          'a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])',
        ),
      ).filter((element) => !element.hasAttribute('hidden'));

      if (focusableElements.length === 0) return;

      const firstElement = focusableElements[0];
      const lastElement = focusableElements[focusableElements.length - 1];
      const activeElement =
        document.activeElement instanceof HTMLElement ? document.activeElement : null;

      if (!activeElement || !dialog.contains(activeElement)) {
        event.preventDefault();
        firstElement.focus();
        return;
      }

      if (event.shiftKey && activeElement === firstElement) {
        event.preventDefault();
        lastElement.focus();
        return;
      }

      if (!event.shiftKey && activeElement === lastElement) {
        event.preventDefault();
        firstElement.focus();
      }
    };

    document.addEventListener('keydown', handler);

    return () => document.removeEventListener('keydown', handler);
  }, [handleClose, isOpen]);

  if (!isOpen) return null;

  const clearError = () => {
    if (error) {
      setError(null);
      setFieldWithError(null);
    }
  };

  const handleSubmit = async () => {
    const normalizedNameFr = nameFr.trim();
    if (!normalizedNameFr) {
      setFieldWithError('name');
      setError('Le nom en français est obligatoire.');
      return;
    }

    const sessionId = sessionRef.current;
    setIsSubmitting(true);
    setError(null);
    setFieldWithError(null);

    try {
      await createProductProposal(storeId, {
        name_fr: normalizedNameFr,
        name_ar: nameAr.trim() || null,
        brand_name: brandName.trim() || null,
        category_id: categoryId || null,
        category_name_proposed: !categoryId && categoryNameProposed.trim() ? categoryNameProposed.trim() : null,
        local_product_id: product.local_product_id ?? null,
        volume: product.volume ?? null,
        unit: product.unit as never,
        barcode: null,
      });

      if (!isCurrentSession(sessionId)) return;

      setSuccess(true);
    } catch {
      if (!isCurrentSession(sessionId)) return;

      setError("Impossible d'envoyer la proposition. Veuillez réessayer.");
    } finally {
      if (!isCurrentSession(sessionId)) return;

      setIsSubmitting(false);
    }
  };

  const sortedCategories = [...categories].sort((a, b) => a.name_fr.localeCompare(b.name_fr));
  const matchedBrand = brands.find(
    (b) => b.name.toLowerCase() === (product.brand ?? '').toLowerCase(),
  );

  return (
    <div className="fixed inset-0 z-50 flex justify-end">
      <div className="absolute inset-0 bg-black/40" onClick={handleClose} />
      <div
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby="propose-product-title"
        className="relative flex h-full w-full max-w-xl flex-col bg-card shadow-floating"
      >
        <div className="border-b border-line px-6 py-4">
          <h2 id="propose-product-title" className="font-black text-ink">
            Proposer au référentiel
          </h2>
          <p className="mt-1 text-sm text-muted">
            {"L'administrateur recevra la proposition et décidera de l'intégrer au catalogue global."}
          </p>
        </div>

        <div className="flex-1 space-y-4 overflow-y-auto px-6 py-5">
          {success ? (
            <div
              role="status"
              className="rounded-md bg-status-ready-bg px-4 py-3 text-sm text-status-ready"
            >
              {"Proposition envoyée à l'admin pour validation."}
            </div>
          ) : (
            <>
              {error && (
                <div
                  id="propose-product-error"
                  role="alert"
                  className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel"
                >
                  {error}
                </div>
              )}

              <div>
                <label htmlFor="propose-name-fr" className="mb-1 block text-sm font-bold">
                  Nom en français
                </label>
                <input
                  id="propose-name-fr"
                  value={nameFr}
                  required
                  aria-required="true"
                  aria-invalid={fieldWithError === 'name'}
                  aria-describedby={
                    fieldWithError === 'name' ? 'propose-product-error' : undefined
                  }
                  onChange={(event) => {
                    setNameFr(event.target.value);
                    clearError();
                  }}
                  className="h-11 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                />
              </div>

              <div>
                <label htmlFor="propose-name-ar" className="mb-1 block text-sm font-bold">
                  Nom en arabe
                </label>
                <input
                  id="propose-name-ar"
                  value={nameAr}
                  dir="auto"
                  onChange={(event) => setNameAr(event.target.value)}
                  className="h-11 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                />
              </div>

              <div>
                <label htmlFor="propose-brand" className="mb-1 block text-sm font-bold">
                  Marque
                  {matchedBrand && (
                    <span className="ml-2 font-normal text-muted">
                      {`(correspond à « ${matchedBrand.name} » dans le référentiel)`}
                    </span>
                  )}
                </label>
                <input
                  id="propose-brand"
                  value={brandName}
                  onChange={(event) => setBrandName(event.target.value)}
                  className="h-11 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                />
              </div>

              <div>
                <label htmlFor="propose-category" className="mb-1 block text-sm font-bold">
                  Catégorie globale
                </label>
                {isLoadingRefs ? (
                  <p className="text-sm text-muted">Chargement des catégories…</p>
                ) : (
                  <select
                    id="propose-category"
                    value={categoryId}
                    onChange={(event) => {
                      setCategoryId(event.target.value);
                      clearError();
                    }}
                    className="h-11 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                  >
                    <option value="">— Catégorie inconnue —</option>
                    {sortedCategories.map((cat) => (
                      <option key={cat.id} value={cat.id}>
                        {cat.name_fr}
                      </option>
                    ))}
                  </select>
                )}
              </div>

              {!categoryId && (
                <div>
                  <label htmlFor="propose-category-name" className="mb-1 block text-sm font-bold">
                    Nom de catégorie proposé{' '}
                    <span className="font-normal text-muted">(si catégorie globale inconnue)</span>
                  </label>
                  <input
                    id="propose-category-name"
                    value={categoryNameProposed}
                    placeholder="ex. Boissons, Épicerie…"
                    onChange={(event) => setCategoryNameProposed(event.target.value)}
                    className="h-11 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                  />
                </div>
              )}

              <div className="rounded-md bg-soft px-4 py-3 text-sm text-muted">
                Volume, unité et code-barres seront repris automatiquement depuis le produit local.
              </div>
            </>
          )}
        </div>

        <div className="flex gap-3 border-t border-line px-6 py-4">
          <Button variant="ghost" onClick={handleClose} disabled={isSubmitting} full>
            {success ? 'Fermer' : 'Annuler'}
          </Button>
          {!success && (
            <Button
              onClick={() => void handleSubmit()}
              disabled={isSubmitting || isLoadingRefs}
              full
            >
              {isSubmitting ? 'Envoi…' : 'Envoyer la proposition'}
            </Button>
          )}
        </div>
      </div>
    </div>
  );
}
