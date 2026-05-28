'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { MerchantCategorySelector } from '@/components/merchant/catalogue/MerchantCategorySelector';
import { Button } from '@/components/ui/Button';
import {
  createBulkMerchantLocalProducts,
  createMerchantLocalProduct,
} from '@/lib/services/merchant-catalog.service';
import type {
  BulkLocalProductFormatPayload,
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

interface FormatRow {
  volume: string;
  unit: MerchantProductUnit;
  barcode: string;
  priceTnd: string;
  isAvailable: boolean;
  isVisible: boolean;
  merchantNote: string;
}

function makeEmptyFormat(): FormatRow {
  return {
    volume: '',
    unit: 'piece',
    barcode: '',
    priceTnd: '',
    isAvailable: true,
    isVisible: true,
    merchantNote: '',
  };
}

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
  const [formats, setFormats] = useState<FormatRow[]>([makeEmptyFormat()]);
  const [error, setError] = useState<string | null>(null);
  const [fieldWithError, setFieldWithError] = useState<string | null>(null);
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
    setFormats([makeEmptyFormat()]);
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

  const updateFormat = (index: number, patch: Partial<FormatRow>) => {
    setFormats((prev) => prev.map((f, i) => (i === index ? { ...f, ...patch } : f)));
    clearError();
  };

  const addFormat = () => setFormats((prev) => [...prev, makeEmptyFormat()]);

  const removeFormat = (index: number) =>
    setFormats((prev) => prev.filter((_, i) => i !== index));

  const duplicateFormat = (index: number) =>
    setFormats((prev) => [
      ...prev.slice(0, index + 1),
      { ...prev[index] },
      ...prev.slice(index + 1),
    ]);

  const handleSubmit = async () => {
    const normalizedNameFr = nameFr.trim();
    if (!normalizedNameFr) {
      setFieldWithError('name');
      setError('Le nom en français est obligatoire.');
      return;
    }

    for (let i = 0; i < formats.length; i++) {
      const fmt = formats[i];
      const normalizedPrice = normalizeDecimal(fmt.priceTnd, { allowEmpty: false });
      if (!normalizedPrice) {
        setFieldWithError(`price-${i}`);
        setError(
          formats.length === 1
            ? priceErrorMessage
            : `Format ${i + 1} : ${priceErrorMessage}`,
        );
        return;
      }

      if (fmt.volume.trim() !== '') {
        const normalizedVolume = normalizeDecimal(fmt.volume, { allowEmpty: true });
        if (!normalizedVolume) {
          setFieldWithError(`volume-${i}`);
          setError(
            formats.length === 1
              ? volumeErrorMessage
              : `Format ${i + 1} : ${volumeErrorMessage}`,
          );
          return;
        }
      }
    }

    const sessionId = sessionRef.current;
    setIsSubmitting(true);
    setError(null);
    setFieldWithError(null);

    try {
      if (formats.length === 1) {
        const fmt = formats[0];
        await createMerchantLocalProduct(storeId, {
          name_fr: normalizedNameFr,
          name_ar: optionalText(nameAr),
          brand_name: optionalText(brandName),
          volume: normalizeDecimal(fmt.volume, { allowEmpty: true }),
          unit: fmt.unit,
          barcode: optionalText(fmt.barcode),
          default_category_name: optionalText(categoryName),
          price_tnd: normalizeDecimal(fmt.priceTnd, { allowEmpty: false })!,
          is_available: fmt.isAvailable,
          is_visible: fmt.isVisible,
          merchant_note: optionalText(fmt.merchantNote),
          merchant_category_id: merchantCategoryId,
        });
      } else {
        const bulkFormats: BulkLocalProductFormatPayload[] = formats.map((fmt) => ({
          volume: normalizeDecimal(fmt.volume, { allowEmpty: true }),
          unit: fmt.unit,
          barcode: optionalText(fmt.barcode),
          price_tnd: normalizeDecimal(fmt.priceTnd, { allowEmpty: false })!,
          is_available: fmt.isAvailable,
          is_visible: fmt.isVisible,
          merchant_note: optionalText(fmt.merchantNote),
        }));

        await createBulkMerchantLocalProducts(storeId, {
          base_name_fr: normalizedNameFr,
          base_name_ar: optionalText(nameAr),
          brand_name: optionalText(brandName),
          default_category_name: optionalText(categoryName),
          merchant_category_id: merchantCategoryId,
          formats: bulkFormats,
        });
      }

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

          <div className="border-t border-line pt-4">
            <div className="mb-3 flex items-center justify-between">
              <h3 className="text-sm font-bold">
                Formats{' '}
                <span className="font-normal text-muted">({formats.length})</span>
              </h3>
              <button
                type="button"
                onClick={addFormat}
                disabled={isSubmitting || formats.length >= 20}
                className="rounded-md border border-line bg-white px-3 py-1 text-xs font-bold hover:bg-soft disabled:opacity-40"
              >
                + Format
              </button>
            </div>

            <div className="space-y-4">
              {formats.map((fmt, i) => (
                <div
                  key={i}
                  className="rounded-md border border-line bg-soft p-4"
                >
                  <div className="mb-3 flex items-center justify-between">
                    <span className="text-xs font-bold text-muted">Format {i + 1}</span>
                    <div className="flex gap-2">
                      <button
                        type="button"
                        onClick={() => duplicateFormat(i)}
                        disabled={isSubmitting || formats.length >= 20}
                        className="rounded px-2 py-1 text-xs text-muted hover:text-ink disabled:opacity-40"
                      >
                        Dupliquer
                      </button>
                      {formats.length > 1 && (
                        <button
                          type="button"
                          onClick={() => removeFormat(i)}
                          disabled={isSubmitting}
                          className="rounded px-2 py-1 text-xs text-status-cancel hover:text-status-cancel/80 disabled:opacity-40"
                        >
                          Supprimer
                        </button>
                      )}
                    </div>
                  </div>

                  <div className="grid gap-3 sm:grid-cols-2">
                    <div>
                      <label htmlFor={`fmt-volume-${i}`} className="mb-1 block text-xs font-bold">
                        Volume
                      </label>
                      <input
                        id={`fmt-volume-${i}`}
                        inputMode="decimal"
                        value={fmt.volume}
                        aria-invalid={fieldWithError === `volume-${i}`}
                        onChange={(event) => updateFormat(i, { volume: event.target.value })}
                        className="h-9 w-full rounded border border-line bg-white px-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                      />
                    </div>

                    <div>
                      <label htmlFor={`fmt-unit-${i}`} className="mb-1 block text-xs font-bold">
                        Unité
                      </label>
                      <select
                        id={`fmt-unit-${i}`}
                        value={fmt.unit}
                        onChange={(event) =>
                          updateFormat(i, { unit: event.target.value as MerchantProductUnit })
                        }
                        className="h-9 w-full rounded border border-line bg-white px-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                      >
                        {productUnits.map((u) => (
                          <option key={u.value} value={u.value}>
                            {u.label}
                          </option>
                        ))}
                      </select>
                    </div>

                    <div>
                      <label htmlFor={`fmt-barcode-${i}`} className="mb-1 block text-xs font-bold">
                        Code-barres
                      </label>
                      <input
                        id={`fmt-barcode-${i}`}
                        value={fmt.barcode}
                        onChange={(event) => updateFormat(i, { barcode: event.target.value })}
                        className="h-9 w-full rounded border border-line bg-white px-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                      />
                    </div>

                    <div>
                      <label htmlFor={`fmt-price-${i}`} className="mb-1 block text-xs font-bold">
                        Prix TND <span className="text-status-cancel">*</span>
                      </label>
                      <input
                        id={`fmt-price-${i}`}
                        inputMode="decimal"
                        value={fmt.priceTnd}
                        required
                        aria-required="true"
                        aria-invalid={fieldWithError === `price-${i}`}
                        onChange={(event) => updateFormat(i, { priceTnd: event.target.value })}
                        className="h-9 w-full rounded border border-line bg-white px-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                      />
                    </div>
                  </div>

                  <div className="mt-3 flex gap-6">
                    <label className="flex items-center gap-2 text-xs font-bold">
                      <input
                        type="checkbox"
                        checked={fmt.isAvailable}
                        onChange={(event) => updateFormat(i, { isAvailable: event.target.checked })}
                        className="h-4 w-4 rounded border-line"
                      />
                      Disponible
                    </label>

                    <label className="flex items-center gap-2 text-xs font-bold">
                      <input
                        type="checkbox"
                        checked={fmt.isVisible}
                        onChange={(event) => updateFormat(i, { isVisible: event.target.checked })}
                        className="h-4 w-4 rounded border-line"
                      />
                      Visible
                    </label>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        <div className="flex gap-3 border-t border-line px-6 py-4">
          <Button variant="ghost" onClick={handleClose} disabled={isSubmitting} full>
            Fermer
          </Button>
          <Button onClick={() => void handleSubmit()} disabled={isSubmitting} full>
            {isSubmitting
              ? 'Création…'
              : formats.length === 1
                ? 'Créer dans mon catalogue'
                : `Créer ${formats.length} formats`}
          </Button>
        </div>
      </div>
    </div>
  );
}
