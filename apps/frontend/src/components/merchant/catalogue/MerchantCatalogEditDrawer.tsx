'use client';

import { useEffect, useRef, useState } from 'react';
import { MerchantCategorySelector } from '@/components/merchant/catalogue/MerchantCategorySelector';
import { Button } from '@/components/ui/Button';
import {
  getMerchantProductPriceHistory,
  updateMerchantCatalogProduct,
} from '@/lib/services/merchant-catalog.service';
import type {
  MerchantCatalogProduct,
  MerchantCategory,
  MerchantProductPriceHistoryItem,
} from '@/lib/types/merchant-catalog.types';

function formatPriceTnd(price: string): string {
  return (
    Number(price).toLocaleString('fr-FR', {
      minimumFractionDigits: 3,
      maximumFractionDigits: 3,
    }) + ' TND'
  );
}

function formatHistoryDate(dateStr: string): string {
  const date = new Date(dateStr);
  const datePart = date.toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
  const h = String(date.getHours()).padStart(2, '0');
  const m = String(date.getMinutes()).padStart(2, '0');
  return `${datePart} ${h}h${m}`;
}

interface MerchantCatalogEditDrawerProps {
  product: MerchantCatalogProduct | null;
  categories: MerchantCategory[];
  categoryMessage?: string | null;
  onCreateCategory?: (nameFr: string) => Promise<MerchantCategory>;
  onClose: () => void;
  onSaved: () => void;
}

function productCategory(product: MerchantCatalogProduct): string {
  return product.merchant_category_name ?? product.category;
}

function validatePrice(value: string): string | null {
  const trimmedValue = value.trim();

  if (!/^\d+(?:[.,]\d{1,3})?$/.test(trimmedValue)) {
    return null;
  }

  const parsedPrice = Number(trimmedValue.replace(',', '.'));

  if (!Number.isFinite(parsedPrice) || parsedPrice <= 0) {
    return null;
  }

  return parsedPrice.toFixed(3);
}

export function MerchantCatalogEditDrawer({
  categories,
  categoryMessage,
  onCreateCategory,
  onClose,
  onSaved,
  product,
}: MerchantCatalogEditDrawerProps) {
  const [priceTnd, setPriceTnd] = useState('');
  const [isAvailable, setIsAvailable] = useState(true);
  const [isVisible, setIsVisible] = useState(true);
  const [merchantNote, setMerchantNote] = useState('');
  const [merchantCategoryId, setMerchantCategoryId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [hasPriceError, setHasPriceError] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [showPriceHistory, setShowPriceHistory] = useState(false);
  const [priceHistory, setPriceHistory] = useState<MerchantProductPriceHistoryItem[] | null>(null);
  const [isPriceHistoryLoading, setIsPriceHistoryLoading] = useState(false);
  const [priceHistoryError, setPriceHistoryError] = useState(false);
  const currentProductIdRef = useRef<string | null>(null);
  const dialogRef = useRef<HTMLDivElement>(null);
  const priceInputRef = useRef<HTMLInputElement>(null);
  const previousFocusRef = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (!product) return;

    setPriceTnd(product.price_tnd);
    setIsAvailable(product.is_available);
    setIsVisible(product.is_visible);
    setMerchantNote(product.merchant_note ?? '');
    setMerchantCategoryId(product.merchant_category_id ?? null);
    setError(null);
    setHasPriceError(false);
    setIsSubmitting(false);
    setShowPriceHistory(false);
    setPriceHistory(null);
    setIsPriceHistoryLoading(false);
    setPriceHistoryError(false);
    currentProductIdRef.current = product.id;
  }, [product]);

  useEffect(() => {
    if (!product) return;

    previousFocusRef.current =
      document.activeElement instanceof HTMLElement ? document.activeElement : null;
    priceInputRef.current?.focus();

    return () => {
      const previousFocus = previousFocusRef.current;
      if (previousFocus && document.contains(previousFocus)) {
        previousFocus.focus();
      }
      previousFocusRef.current = null;
    };
  }, [product]);

  useEffect(() => {
    if (!product) return;

    const handler = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        onClose();
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
  }, [onClose, product]);

  if (!product) return null;

  const handleTogglePriceHistory = async () => {
    if (showPriceHistory) {
      setShowPriceHistory(false);
      return;
    }
    setShowPriceHistory(true);
    if (priceHistory !== null) return;

    setIsPriceHistoryLoading(true);
    setPriceHistoryError(false);
    const requestedProductId = product.id;
    try {
      const result = await getMerchantProductPriceHistory(requestedProductId);
      if (currentProductIdRef.current === requestedProductId) {
        setPriceHistory(result.priceHistory);
      }
    } catch {
      if (currentProductIdRef.current === requestedProductId) {
        setPriceHistoryError(true);
      }
    } finally {
      if (currentProductIdRef.current === requestedProductId) {
        setIsPriceHistoryLoading(false);
      }
    }
  };

  const handleSubmit = async () => {
    const normalizedPrice = validatePrice(priceTnd);

    if (!normalizedPrice) {
      setHasPriceError(true);
      setError('Le prix doit être supérieur à 0 avec au maximum 3 décimales.');
      return;
    }

    setIsSubmitting(true);
    setError(null);
    setHasPriceError(false);

    try {
      await updateMerchantCatalogProduct(product.id, {
        price_tnd: normalizedPrice,
        is_available: isAvailable,
        is_visible: isVisible,
        merchant_note: merchantNote.trim() || null,
        merchant_category_id: merchantCategoryId,
      });
      onSaved();
    } catch {
      setError("Impossible d'enregistrer le produit.");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex justify-end">
      <div className="absolute inset-0 bg-black/40" onClick={onClose} />
      <div
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby="merchant-catalog-edit-title"
        className="relative flex h-full w-full max-w-md flex-col bg-card shadow-floating"
      >
        <div className="border-b border-line px-6 py-4">
          <h2 id="merchant-catalog-edit-title" className="font-black text-ink">
            Modifier {product.name_fr}
          </h2>
          <p className="mt-1 text-sm text-muted">Catégorie : {productCategory(product)}</p>
        </div>

        <div className="flex-1 space-y-4 overflow-y-auto px-6 py-5">
          {error && (
            <div
              id="merchant-catalog-edit-error"
              role="alert"
              className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel"
            >
              {error}
            </div>
          )}

          <div>
            <label htmlFor="merchant-catalog-price" className="mb-1 block text-sm font-bold">
              Prix TND
            </label>
            <input
              ref={priceInputRef}
              id="merchant-catalog-price"
              type="text"
              inputMode="decimal"
              required
              value={priceTnd}
              aria-invalid={hasPriceError}
              aria-describedby={hasPriceError ? 'merchant-catalog-edit-error' : undefined}
              onChange={(event) => {
                setPriceTnd(event.target.value);
                if (hasPriceError) {
                  setHasPriceError(false);
                  setError(null);
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

          <MerchantCategorySelector
            categories={categories}
            fallbackCategory={product.category}
            value={merchantCategoryId}
            onChange={setMerchantCategoryId}
            onCreate={onCreateCategory}
            disabled={isSubmitting}
            message={categoryMessage}
          />

          <div>
            <label htmlFor="merchant-catalog-note" className="mb-1 block text-sm font-bold">
              Note marchand
            </label>
            <textarea
              id="merchant-catalog-note"
              value={merchantNote}
              onChange={(event) => setMerchantNote(event.target.value)}
              rows={4}
              className="w-full rounded-md border border-line bg-white px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>

          <div className="border-t border-line pt-4">
            <button
              type="button"
              onClick={() => void handleTogglePriceHistory()}
              className="text-sm font-bold text-primary hover:underline"
            >
              {showPriceHistory ? "Masquer l'historique" : "Voir l'historique des prix"}
            </button>

            {showPriceHistory && (
              <div className="mt-3">
                {isPriceHistoryLoading && (
                  <p className="text-sm text-muted">Chargement…</p>
                )}
                {priceHistoryError && (
                  <p className="text-sm text-status-cancel">
                    Impossible de charger l&apos;historique.
                  </p>
                )}
                {!isPriceHistoryLoading && !priceHistoryError && priceHistory !== null && (
                  <>
                    {priceHistory.length <= 1 ? (
                      <p className="text-sm text-muted">Aucun changement de prix enregistré.</p>
                    ) : (
                      <ul className="space-y-2">
                        {priceHistory.map((item, index) => (
                          <li key={index} className="flex items-baseline justify-between text-sm">
                            <span className="font-bold">{formatPriceTnd(item.newPrice)}</span>
                            <span className="text-muted">{formatHistoryDate(item.changedAt)}</span>
                          </li>
                        ))}
                      </ul>
                    )}
                  </>
                )}
              </div>
            )}
          </div>
        </div>

        <div className="flex gap-3 border-t border-line px-6 py-4">
          <Button variant="ghost" onClick={onClose} disabled={isSubmitting} full>
            Fermer
          </Button>
          <Button onClick={() => void handleSubmit()} disabled={isSubmitting} full>
            {isSubmitting ? 'Enregistrement…' : 'Enregistrer'}
          </Button>
        </div>
      </div>
    </div>
  );
}
