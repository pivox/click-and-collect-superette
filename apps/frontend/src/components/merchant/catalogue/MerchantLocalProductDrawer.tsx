'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { MerchantCategorySelector } from '@/components/merchant/catalogue/MerchantCategorySelector';
import { Button } from '@/components/ui/Button';
import { createMerchantLocalProduct } from '@/lib/services/merchant-catalog.service';
import type {
  MerchantCategory,
  MerchantProductUnit,
} from '@/lib/types/merchant-catalog.types';

interface MerchantLocalProductDrawerProps {
  isOpen: boolean;
  storeId: string | null;
  categories: MerchantCategory[];
  categoryMessage?: string | null;
  onCreateCategory?: (nameFr: string) => Promise<MerchantCategory>;
  onClose: () => void;
  onCreated: () => void;
}

const productUnits: Array<{ value: MerchantProductUnit; label: string }> = [
  { value: 'piece', label: 'Pièce' },
  { value: 'paquet', label: 'Paquet' },
  { value: 'gramme', label: 'Gramme' },
  { value: 'kilogramme', label: 'Kilogramme' },
  { value: 'millilitre', label: 'Millilitre' },
  { value: 'litre', label: 'Litre' },
];

const priceErrorMessage = 'Le prix doit être supérieur à 0 avec au maximum 3 décimales.';
const volumeErrorMessage = 'Le volume doit être positif avec au maximum 3 décimales.';

function normalizeDecimal(value: string, { allowEmpty }: { allowEmpty: boolean }): string | null {
  const trimmedValue = value.trim().replace(',', '.');

  if (allowEmpty && trimmedValue === '') {
    return null;
  }

  if (!/^\d{1,7}(?:\.\d{1,3})?$/.test(trimmedValue)) {
    return null;
  }

  const parsedValue = Number(trimmedValue);

  if (!Number.isFinite(parsedValue) || parsedValue <= 0) {
    return null;
  }

  return parsedValue.toFixed(3);
}

function optionalText(value: string): string | null {
  const trimmedValue = value.trim();

  return trimmedValue === '' ? null : trimmedValue;
}

export function MerchantLocalProductDrawer({
  categories,
  categoryMessage,
  isOpen,
  onClose,
  onCreateCategory,
  onCreated,
  storeId,
}: MerchantLocalProductDrawerProps) {
  const [nameFr, setNameFr] = useState('');
  const [nameAr, setNameAr] = useState('');
  const [brandName, setBrandName] = useState('');
  const [categoryName, setCategoryName] = useState('');
  const [merchantCategoryId, setMerchantCategoryId] = useState<string | null>(null);
  const [volume, setVolume] = useState('');
  const [unit, setUnit] = useState<MerchantProductUnit>('piece');
  const [barcode, setBarcode] = useState('');
  const [priceTnd, setPriceTnd] = useState('');
  const [isAvailable, setIsAvailable] = useState(true);
  const [isVisible, setIsVisible] = useState(true);
  const [merchantNote, setMerchantNote] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [fieldWithError, setFieldWithError] = useState<'name' | 'price' | 'volume' | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const dialogRef = useRef<HTMLDivElement>(null);
  const nameInputRef = useRef<HTMLInputElement>(null);
  const previousFocusRef = useRef<HTMLElement | null>(null);
  const sessionRef = useRef(0);
  const isOpenRef = useRef(isOpen);

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

    if (!isOpen) return;

    setNameFr('');
    setNameAr('');
    setBrandName('');
    setCategoryName('');
    setMerchantCategoryId(null);
    setVolume('');
    setUnit('piece');
    setBarcode('');
    setPriceTnd('');
    setIsAvailable(true);
    setIsVisible(true);
    setMerchantNote('');
    setError(null);
    setFieldWithError(null);
    setIsSubmitting(false);
  }, [isOpen]);

  useEffect(() => {
    if (!isOpen) return;

    previousFocusRef.current =
      document.activeElement instanceof HTMLElement ? document.activeElement : null;
    nameInputRef.current?.focus();

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

  if (!isOpen || !storeId) return null;

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

    const normalizedPrice = normalizeDecimal(priceTnd, { allowEmpty: false });
    if (!normalizedPrice) {
      setFieldWithError('price');
      setError(priceErrorMessage);
      return;
    }

    const normalizedVolume = normalizeDecimal(volume, { allowEmpty: true });
    if (volume.trim() !== '' && !normalizedVolume) {
      setFieldWithError('volume');
      setError(volumeErrorMessage);
      return;
    }

    const sessionId = sessionRef.current;
    setIsSubmitting(true);
    setError(null);
    setFieldWithError(null);

    try {
      await createMerchantLocalProduct(storeId, {
        name_fr: normalizedNameFr,
        name_ar: optionalText(nameAr),
        brand_name: optionalText(brandName),
        volume: normalizedVolume,
        unit,
        barcode: optionalText(barcode),
        default_category_name: optionalText(categoryName),
        price_tnd: normalizedPrice,
        is_available: isAvailable,
        is_visible: isVisible,
        merchant_note: optionalText(merchantNote),
        merchant_category_id: merchantCategoryId,
      });
      if (!isCurrentSession(sessionId)) return;

      onCreated();
    } catch {
      if (!isCurrentSession(sessionId)) return;

      setError('Impossible de créer le produit local.');
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
        aria-labelledby="merchant-local-product-title"
        className="relative flex h-full w-full max-w-xl flex-col bg-card shadow-floating"
      >
        <div className="border-b border-line px-6 py-4">
          <h2 id="merchant-local-product-title" className="font-black text-ink">
            Créer un produit local
          </h2>
        </div>

        <div className="flex-1 space-y-4 overflow-y-auto px-6 py-5">
          {error && (
            <div
              id="merchant-local-product-error"
              role="alert"
              className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel"
            >
              {error}
            </div>
          )}

          <div>
            <label htmlFor="local-product-name-fr" className="mb-1 block text-sm font-bold">
              Nom en français
            </label>
            <input
              ref={nameInputRef}
              id="local-product-name-fr"
              value={nameFr}
              required
              aria-required="true"
              aria-invalid={fieldWithError === 'name'}
              aria-describedby={fieldWithError === 'name' ? 'merchant-local-product-error' : undefined}
              onChange={(event) => {
                setNameFr(event.target.value);
                clearError();
              }}
              className="h-11 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>

          <div>
            <label htmlFor="local-product-name-ar" className="mb-1 block text-sm font-bold">
              Nom en arabe
            </label>
            <input
              id="local-product-name-ar"
              value={nameAr}
              dir="auto"
              onChange={(event) => setNameAr(event.target.value)}
              className="h-11 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label htmlFor="local-product-brand" className="mb-1 block text-sm font-bold">
                Marque
              </label>
              <input
                id="local-product-brand"
                value={brandName}
                onChange={(event) => setBrandName(event.target.value)}
                className="h-11 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
              />
            </div>

            <div>
              <label htmlFor="local-product-category" className="mb-1 block text-sm font-bold">
                Catégorie par défaut
              </label>
              <input
                id="local-product-category"
                value={categoryName}
                onChange={(event) => setCategoryName(event.target.value)}
                className="h-11 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
              />
            </div>
          </div>

          <MerchantCategorySelector
            categories={categories}
            fallbackCategory={optionalText(categoryName) ?? 'Non renseignée'}
            value={merchantCategoryId}
            onChange={setMerchantCategoryId}
            onCreate={onCreateCategory}
            disabled={isSubmitting}
            message={categoryMessage}
          />

          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label htmlFor="local-product-volume" className="mb-1 block text-sm font-bold">
                Volume
              </label>
              <input
                id="local-product-volume"
                inputMode="decimal"
                value={volume}
                aria-invalid={fieldWithError === 'volume'}
                aria-describedby={fieldWithError === 'volume' ? 'merchant-local-product-error' : undefined}
                onChange={(event) => {
                  setVolume(event.target.value);
                  clearError();
                }}
                className="h-11 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
              />
            </div>

            <div>
              <label htmlFor="local-product-unit" className="mb-1 block text-sm font-bold">
                Unité
              </label>
              <select
                id="local-product-unit"
                value={unit}
                onChange={(event) => setUnit(event.target.value as MerchantProductUnit)}
                className="h-11 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
              >
                {productUnits.map((productUnit) => (
                  <option key={productUnit.value} value={productUnit.value}>
                    {productUnit.label}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label htmlFor="local-product-barcode" className="mb-1 block text-sm font-bold">
                Code-barres
              </label>
              <input
                id="local-product-barcode"
                value={barcode}
                onChange={(event) => setBarcode(event.target.value)}
                className="h-11 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
              />
            </div>

            <div>
              <label htmlFor="local-product-price" className="mb-1 block text-sm font-bold">
                Prix TND
              </label>
              <input
                id="local-product-price"
                inputMode="decimal"
                value={priceTnd}
                required
                aria-required="true"
                aria-invalid={fieldWithError === 'price'}
                aria-describedby={fieldWithError === 'price' ? 'merchant-local-product-error' : undefined}
                onChange={(event) => {
                  setPriceTnd(event.target.value);
                  clearError();
                }}
                className="h-11 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
              />
            </div>
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
            <label htmlFor="local-product-note" className="mb-1 block text-sm font-bold">
              Note marchand
            </label>
            <textarea
              id="local-product-note"
              value={merchantNote}
              onChange={(event) => setMerchantNote(event.target.value)}
              rows={4}
              className="w-full rounded-md border border-line bg-white px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>
        </div>

        <div className="flex gap-3 border-t border-line px-6 py-4">
          <Button variant="ghost" onClick={handleClose} disabled={isSubmitting} full>
            Fermer
          </Button>
          <Button onClick={() => void handleSubmit()} disabled={isSubmitting} full>
            {isSubmitting ? 'Création…' : 'Créer dans mon catalogue'}
          </Button>
        </div>
      </div>
    </div>
  );
}
