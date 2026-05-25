'use client';

import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { updateMerchantCatalogProduct } from '@/lib/services/merchant-catalog.service';
import type { MerchantCatalogProduct } from '@/lib/types/merchant-catalog.types';

interface MerchantCatalogEditDrawerProps {
  product: MerchantCatalogProduct | null;
  onClose: () => void;
  onSaved: () => void;
}

function productCategory(product: MerchantCatalogProduct): string {
  return product.merchant_category_name ?? product.category;
}

function normalizePrice(value: string): string | null {
  const parsedPrice = Number(value.replace(',', '.'));

  if (!Number.isFinite(parsedPrice) || parsedPrice <= 0) {
    return null;
  }

  return parsedPrice.toFixed(3);
}

export function MerchantCatalogEditDrawer({
  onClose,
  onSaved,
  product,
}: MerchantCatalogEditDrawerProps) {
  const [priceTnd, setPriceTnd] = useState('');
  const [isAvailable, setIsAvailable] = useState(true);
  const [isVisible, setIsVisible] = useState(true);
  const [merchantNote, setMerchantNote] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (!product) return;

    setPriceTnd(product.price_tnd);
    setIsAvailable(product.is_available);
    setIsVisible(product.is_visible);
    setMerchantNote(product.merchant_note ?? '');
    setError(null);
    setIsSubmitting(false);
  }, [product]);

  useEffect(() => {
    if (!product) return;

    const handler = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', handler);

    return () => document.removeEventListener('keydown', handler);
  }, [onClose, product]);

  if (!product) return null;

  const handleSubmit = async () => {
    const normalizedPrice = normalizePrice(priceTnd);

    if (!normalizedPrice) {
      setError('Le prix doit être positif.');
      return;
    }

    setIsSubmitting(true);
    setError(null);

    try {
      await updateMerchantCatalogProduct(product.id, {
        price_tnd: normalizedPrice,
        is_available: isAvailable,
        is_visible: isVisible,
        merchant_note: merchantNote.trim() || null,
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
            <div className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
              {error}
            </div>
          )}

          <div>
            <label htmlFor="merchant-catalog-price" className="mb-1 block text-sm font-bold">
              Prix TND
            </label>
            <input
              id="merchant-catalog-price"
              type="number"
              min="0.001"
              step="0.001"
              required
              value={priceTnd}
              onChange={(event) => setPriceTnd(event.target.value)}
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
