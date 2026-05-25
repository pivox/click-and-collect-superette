'use client';

import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/Button';
import {
  addMerchantCatalogProduct,
  searchMerchantProductReferences,
} from '@/lib/services/merchant-catalog.service';
import type { MerchantProductReferenceSearchItem } from '@/lib/types/merchant-catalog.types';

interface ProductReferenceSearchDrawerProps {
  isOpen: boolean;
  storeId: string | null;
  onClose: () => void;
  onAdded: () => void;
}

const priceErrorMessage = 'Le prix doit être supérieur à 0 avec au maximum 3 décimales.';

function productFormat(productReference: MerchantProductReferenceSearchItem): string {
  return [productReference.volume, productReference.unit].filter(Boolean).join(' ');
}

function validatePrice(value: string): string | null {
  const trimmedValue = value.trim().replace(',', '.');

  if (!/^\d+(?:\.\d{1,3})?$/.test(trimmedValue)) {
    return null;
  }

  const parsedPrice = Number(trimmedValue);

  if (!Number.isFinite(parsedPrice) || parsedPrice <= 0) {
    return null;
  }

  return parsedPrice.toFixed(3);
}

function isConflictError(error: unknown): boolean {
  if (!error || typeof error !== 'object' || !('response' in error)) {
    return false;
  }

  const response = (error as { response?: { status?: number } }).response;

  return response?.status === 409;
}

export function ProductReferenceSearchDrawer({
  isOpen,
  onAdded,
  onClose,
  storeId,
}: ProductReferenceSearchDrawerProps) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<MerchantProductReferenceSearchItem[]>([]);
  const [selectedProductReference, setSelectedProductReference] =
    useState<MerchantProductReferenceSearchItem | null>(null);
  const [priceTnd, setPriceTnd] = useState('');
  const [isAvailable, setIsAvailable] = useState(true);
  const [isVisible, setIsVisible] = useState(true);
  const [merchantNote, setMerchantNote] = useState('');
  const [searchError, setSearchError] = useState<string | null>(null);
  const [addError, setAddError] = useState<string | null>(null);
  const [hasPriceError, setHasPriceError] = useState(false);
  const [isSearching, setIsSearching] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const dialogRef = useRef<HTMLDivElement>(null);
  const searchInputRef = useRef<HTMLInputElement>(null);
  const priceInputRef = useRef<HTMLInputElement>(null);
  const previousFocusRef = useRef<HTMLElement | null>(null);
  const sessionRef = useRef(0);
  const searchRequestRef = useRef(0);
  const isOpenRef = useRef(isOpen);

  const isCurrentSession = useCallback((sessionId: number) => {
    return isOpenRef.current && sessionRef.current === sessionId;
  }, []);

  const handleClose = useCallback(() => {
    isOpenRef.current = false;
    sessionRef.current += 1;
    searchRequestRef.current += 1;
    onClose();
  }, [onClose]);

  useEffect(() => {
    isOpenRef.current = isOpen;
    sessionRef.current += 1;
    searchRequestRef.current += 1;

    if (!isOpen) return;

    setQuery('');
    setResults([]);
    setSelectedProductReference(null);
    setPriceTnd('');
    setIsAvailable(true);
    setIsVisible(true);
    setMerchantNote('');
    setSearchError(null);
    setAddError(null);
    setHasPriceError(false);
    setIsSearching(false);
    setIsSubmitting(false);
  }, [isOpen]);

  useEffect(() => {
    if (!isOpen) return;

    previousFocusRef.current =
      document.activeElement instanceof HTMLElement ? document.activeElement : null;
    searchInputRef.current?.focus();

    return () => {
      const previousFocus = previousFocusRef.current;
      if (previousFocus && document.contains(previousFocus)) {
        previousFocus.focus();
      }
      previousFocusRef.current = null;
    };
  }, [isOpen]);

  useEffect(() => {
    if (selectedProductReference) {
      priceInputRef.current?.focus();
    }
  }, [selectedProductReference]);

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

  if (!isOpen || !storeId) return null;

  const handleSearch = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const sessionId = sessionRef.current;
    const searchRequestId = searchRequestRef.current + 1;
    searchRequestRef.current = searchRequestId;

    setIsSearching(true);
    setSearchError(null);
    setSelectedProductReference(null);

    try {
      const searchResult = await searchMerchantProductReferences(storeId, {
        q: query.trim(),
        page: 1,
        limit: 20,
      });
      if (!isCurrentSession(sessionId) || searchRequestRef.current !== searchRequestId) return;

      setResults(searchResult.items);
    } catch {
      if (!isCurrentSession(sessionId) || searchRequestRef.current !== searchRequestId) return;

      setResults([]);
      setSearchError('Impossible de rechercher dans le référentiel.');
    } finally {
      if (!isCurrentSession(sessionId) || searchRequestRef.current !== searchRequestId) return;

      setIsSearching(false);
    }
  };

  const handleSubmit = async () => {
    if (!selectedProductReference) return;
    const sessionId = sessionRef.current;

    const normalizedPrice = validatePrice(priceTnd);

    if (!normalizedPrice) {
      setHasPriceError(true);
      setAddError(priceErrorMessage);
      return;
    }

    setIsSubmitting(true);
    setAddError(null);
    setHasPriceError(false);

    try {
      await addMerchantCatalogProduct(storeId, {
        product_reference_id: selectedProductReference.id,
        price_tnd: normalizedPrice,
        is_available: isAvailable,
        is_visible: isVisible,
        merchant_note: merchantNote.trim() || null,
      });
      if (!isCurrentSession(sessionId)) return;

      onAdded();
    } catch (error) {
      if (!isCurrentSession(sessionId)) return;

      setAddError(
        isConflictError(error)
          ? 'Ce produit est déjà dans mon catalogue.'
          : "Impossible d'ajouter le produit au catalogue.",
      );
    } finally {
      if (!isCurrentSession(sessionId)) return;

      setIsSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex justify-end">
      <div className="absolute inset-0 bg-black/40" onClick={handleClose} />
      <div
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby="product-reference-search-title"
        className="relative flex h-full w-full max-w-xl flex-col bg-card shadow-floating"
      >
        <div className="border-b border-line px-6 py-4">
          <h2 id="product-reference-search-title" className="font-black text-ink">
            Ajouter un produit
          </h2>
          <p className="mt-1 text-sm text-muted">
            Recherche dans le référentiel puis ajoute le produit au catalogue marchand.
          </p>
        </div>

        <div className="flex-1 space-y-5 overflow-y-auto px-6 py-5">
          <form className="grid gap-3 sm:grid-cols-[1fr_auto]" onSubmit={handleSearch}>
            <div>
              <label htmlFor="product-reference-search" className="mb-1 block text-sm font-bold">
                Recherche
              </label>
              <input
                ref={searchInputRef}
                id="product-reference-search"
                type="search"
                aria-label="Rechercher dans le référentiel"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                className="h-11 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
              />
            </div>
            <Button type="submit" disabled={isSearching}>
              {isSearching ? 'Recherche…' : 'Chercher'}
            </Button>
          </form>

          {searchError && (
            <div role="alert" className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
              {searchError}
            </div>
          )}

          {results.length > 0 && (
            <div className="space-y-3">
              {results.map((productReference) => (
                <article key={productReference.id} className="rounded-md border border-line p-4">
                  <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                      <h3 className="font-black text-ink">{productReference.name_fr}</h3>
                      <p className="mt-1 text-sm text-muted">{productReference.brand}</p>
                      <p className="mt-1 text-sm text-muted">{productReference.category}</p>
                      <p className="mt-1 text-sm text-muted">{productFormat(productReference)}</p>
                    </div>
                    <div className="flex flex-col gap-2 sm:items-end">
                      {productReference.already_in_catalog && (
                        <span className="inline-flex min-h-[28px] items-center rounded-md bg-soft px-2 text-xs font-black text-muted">
                          Déjà dans mon catalogue
                        </span>
                      )}
                      <Button
                        variant="ghost"
                        disabled={productReference.already_in_catalog}
                        onClick={() => {
                          if (productReference.already_in_catalog) return;

                          setSelectedProductReference(productReference);
                          setPriceTnd('');
                          setIsAvailable(true);
                          setIsVisible(true);
                          setMerchantNote('');
                          setAddError(null);
                          setHasPriceError(false);
                        }}
                      >
                        Ajouter {productReference.name_fr}
                      </Button>
                    </div>
                  </div>
                </article>
              ))}
            </div>
          )}

          {selectedProductReference && (
            <section className="space-y-4 border-t border-line pt-5">
              <div>
                <h3 className="font-black text-ink">{selectedProductReference.name_fr}</h3>
                <p className="mt-1 text-sm text-muted">
                  Catégorie référentiel : {selectedProductReference.category}
                </p>
              </div>

              {addError && (
                <div
                  id="product-reference-add-error"
                  role="alert"
                  className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel"
                >
                  {addError}
                </div>
              )}

              <div>
                <label htmlFor="product-reference-price" className="mb-1 block text-sm font-bold">
                  Prix TND
                </label>
                <input
                  ref={priceInputRef}
                  id="product-reference-price"
                  type="text"
                  inputMode="decimal"
                  required
                  value={priceTnd}
                  aria-invalid={hasPriceError}
                  aria-describedby={hasPriceError ? 'product-reference-add-error' : undefined}
                  onChange={(event) => {
                    setPriceTnd(event.target.value);
                    if (hasPriceError) {
                      setHasPriceError(false);
                      setAddError(null);
                    }
                  }}
                  className="h-11 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                />
              </div>

              <label className="flex min-h-[44px] items-center gap-3 text-sm font-bold">
                <input
                  type="checkbox"
                  checked={isAvailable}
                  onChange={(event) => setIsAvailable(event.target.checked)}
                  className="h-5 w-5 rounded border-line"
                />
                Disponible
              </label>

              <label className="flex min-h-[44px] items-center gap-3 text-sm font-bold">
                <input
                  type="checkbox"
                  checked={isVisible}
                  onChange={(event) => setIsVisible(event.target.checked)}
                  className="h-5 w-5 rounded border-line"
                />
                Visible
              </label>

              <div>
                <label htmlFor="product-reference-note" className="mb-1 block text-sm font-bold">
                  Note marchand
                </label>
                <textarea
                  id="product-reference-note"
                  value={merchantNote}
                  onChange={(event) => setMerchantNote(event.target.value)}
                  rows={4}
                  className="w-full rounded-md border border-line bg-white px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                />
              </div>
            </section>
          )}
        </div>

        <div className="flex gap-3 border-t border-line px-6 py-4">
          <Button variant="ghost" onClick={handleClose} disabled={isSubmitting} full>
            Fermer
          </Button>
          <Button
            onClick={() => void handleSubmit()}
            disabled={!selectedProductReference || isSubmitting}
            full
          >
            {isSubmitting ? 'Ajout…' : 'Ajouter à mon catalogue'}
          </Button>
        </div>
      </div>
    </div>
  );
}
