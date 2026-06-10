'use client';
import { useState, useEffect } from 'react';
import axios from 'axios';
import {
  updateProductReference,
  rejectProductReference,
} from '@/lib/services/admin/product-references.service';
import { createBrand } from '@/lib/services/admin/brands.service';
import { createCategory } from '@/lib/services/admin/categories.service';
import { InlineCreateCombobox } from '@/components/admin/ui/InlineCreateCombobox';
import type {
  ProductReference,
  Brand,
  Category,
} from '@/lib/types/admin/referentiel.types';
import { cn } from '@/lib/cn';

const UNITS = ['litre', 'millilitre', 'kilogramme', 'gramme', 'piece', 'paquet'] as const;

interface ProductReferenceEditRowProps {
  product: ProductReference;
  brands: Brand[];
  categories: Category[];
  colSpan: number;
  onSaved: () => void;
  onCancel: () => void;
}

export function ProductReferenceEditRow({
  product,
  brands: initialBrands,
  categories: initialCategories,
  colSpan,
  onSaved,
  onCancel,
}: ProductReferenceEditRowProps) {
  const [nameFr, setNameFr] = useState(product.name_fr);
  const [nameAr, setNameAr] = useState(product.name_ar ?? '');
  const [unit, setUnit] = useState(product.unit);
  const [brandId, setBrandId] = useState(product.brand_id);
  const [categoryId, setCategoryId] = useState(product.category_id);
  const [barcode, setBarcode] = useState(product.barcode ?? '');
  const [volume, setVolume] = useState(product.volume ?? '');
  const [brands, setBrands] = useState<Brand[]>(initialBrands);
  const [categories, setCategories] = useState<Category[]>(initialCategories);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [showRejectPrompt, setShowRejectPrompt] = useState(false);
  const [rejectReason, setRejectReason] = useState('');
  const [isRejecting, setIsRejecting] = useState(false);

  useEffect(() => {
    setBrands(initialBrands);
  }, [initialBrands]);

  useEffect(() => {
    setCategories(initialCategories);
  }, [initialCategories]);

  const handleSave = async () => {
    setError(null);
    if (!nameFr.trim() || !brandId || !categoryId || !unit) {
      setError('Nom FR, Marque, Catégorie et Unité sont obligatoires.');
      return;
    }
    setIsSubmitting(true);
    try {
      await updateProductReference(product.id, {
        nameFr: nameFr.trim(),
        nameAr: nameAr.trim() || undefined,
        brandId,
        categoryId,
        unit,
        barcode: barcode.trim() || undefined,
        volume: volume.trim() || undefined,
      });
      onSaved();
    } catch (e) {
      if (axios.isAxiosError(e)) {
        if (e.response?.status === 409) {
          setError('Ce produit a déjà été modifié ailleurs.');
          return;
        }
        if (e.response?.status === 422) {
          setError('Données invalides. Vérifiez les champs obligatoires.');
          return;
        }
      }
      console.error('[ProductReferenceEditRow] updateProductReference failed:', e);
      setError('Une erreur est survenue. Réessayez.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleRejectConfirm = async () => {
    if (!rejectReason.trim()) {
      setError('La raison du rejet est obligatoire.');
      return;
    }
    setIsRejecting(true);
    setError(null);
    try {
      await rejectProductReference(product.id, rejectReason.trim());
      onSaved();
    } catch (e) {
      if (axios.isAxiosError(e)) {
        if (e.response?.status === 409) {
          setError('Ce produit a déjà été traité ailleurs.');
          return;
        }
        if (e.response?.status === 422) {
          setError('Données invalides pour le rejet.');
          return;
        }
      }
      console.error('[ProductReferenceEditRow] rejectProductReference failed:', e);
      setError('Une erreur est survenue. Réessayez.');
    } finally {
      setIsRejecting(false);
    }
  };

  const handleCreateBrand = async (name: string) => {
    const created = await createBrand({ canonicalName: name });
    setBrands((prev) => [...prev, created]);
    return created;
  };

  const handleCreateCategory = async (name: string) => {
    const created = await createCategory({ nameFr: name });
    setCategories((prev) => [...prev, created]);
    return created;
  };

  const isArchived = product.status === 'archived';
  const canReject = product.status !== 'rejected' && product.status !== 'archived';

  return (
    <tr>
      <td colSpan={colSpan} className="border-l-4 border-primary bg-primary/5 px-4 py-4">
        <p className="mb-3 text-xs font-semibold text-primary">
          ↳ Modifier — {product.name_fr}
        </p>

        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <div>
            <label className="mb-1 block text-xs font-semibold">Nom FR *</label>
            <input
              type="text"
              value={nameFr}
              onChange={(e) => setNameFr(e.target.value)}
              disabled={isArchived || isSubmitting}
              placeholder="Nom du produit…"
              className={cn(
                'w-full rounded-md border border-line px-3 py-2 text-sm outline-none transition',
                'focus:border-primary focus:ring-2 focus:ring-primary/20',
                (isArchived || isSubmitting) && 'bg-soft opacity-50 cursor-not-allowed',
              )}
            />
          </div>

          <div>
            <label className="mb-1 block text-xs font-semibold">Nom AR</label>
            <input
              type="text"
              dir="rtl"
              value={nameAr}
              onChange={(e) => setNameAr(e.target.value)}
              disabled={isArchived || isSubmitting}
              placeholder="اسم المنتج…"
              className={cn(
                'w-full rounded-md border border-line px-3 py-2 text-sm outline-none transition',
                'focus:border-primary focus:ring-2 focus:ring-primary/20',
                (isArchived || isSubmitting) && 'bg-soft opacity-50 cursor-not-allowed',
              )}
            />
          </div>

          <div>
            <label className="mb-1 block text-xs font-semibold">Marque *</label>
            <InlineCreateCombobox
              items={brands}
              value={brandId}
              onChange={setBrandId}
              getId={(b) => b.id}
              getLabel={(b) => b.canonical_name}
              placeholder="Marque…"
              onCreate={handleCreateBrand}
              disabled={isArchived || isSubmitting}
            />
          </div>

          <div>
            <label className="mb-1 block text-xs font-semibold">Catégorie *</label>
            <InlineCreateCombobox
              items={categories}
              value={categoryId}
              onChange={setCategoryId}
              getId={(c) => c.id}
              getLabel={(c) => c.name_fr}
              placeholder="Catégorie…"
              onCreate={handleCreateCategory}
              disabled={isArchived || isSubmitting}
            />
          </div>

          <div>
            <label className="mb-1 block text-xs font-semibold">Unité *</label>
            <select
              value={unit}
              onChange={(e) => setUnit(e.target.value)}
              disabled={isArchived || isSubmitting}
              className={cn(
                'w-full rounded-md border border-line px-3 py-2 text-sm outline-none transition',
                'focus:border-primary focus:ring-2 focus:ring-primary/20',
                (isArchived || isSubmitting) && 'bg-soft opacity-50 cursor-not-allowed',
              )}
            >
              <option value="">Sélectionner…</option>
              {UNITS.map((u) => (
                <option key={u} value={u}>
                  {u}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="mb-1 block text-xs font-semibold">Code-barres</label>
            <input
              type="text"
              value={barcode}
              onChange={(e) => setBarcode(e.target.value)}
              disabled={isArchived || isSubmitting}
              placeholder="ex. 3017620422003"
              className={cn(
                'w-full rounded-md border border-line px-3 py-2 text-sm outline-none transition',
                'focus:border-primary focus:ring-2 focus:ring-primary/20',
                (isArchived || isSubmitting) && 'bg-soft opacity-50 cursor-not-allowed',
              )}
            />
          </div>

          <div>
            <label className="mb-1 block text-xs font-semibold">Volume</label>
            <input
              type="text"
              value={volume}
              onChange={(e) => setVolume(e.target.value)}
              disabled={isArchived || isSubmitting}
              placeholder="ex. 500ml"
              className={cn(
                'w-full rounded-md border border-line px-3 py-2 text-sm outline-none transition',
                'focus:border-primary focus:ring-2 focus:ring-primary/20',
                (isArchived || isSubmitting) && 'bg-soft opacity-50 cursor-not-allowed',
              )}
            />
          </div>
        </div>

        {showRejectPrompt && (
          <div className="mt-3 flex items-start gap-3 border-t border-line pt-3">
            <textarea
              value={rejectReason}
              onChange={(e) => setRejectReason(e.target.value)}
              disabled={isRejecting}
              rows={2}
              placeholder="Raison du rejet *"
              className={cn(
                'max-w-sm flex-1 rounded-md border border-line px-3 py-2 text-sm outline-none transition',
                'focus:border-primary focus:ring-2 focus:ring-primary/20',
                isRejecting && 'opacity-50 cursor-not-allowed',
              )}
            />
            <button
              onClick={() => void handleRejectConfirm()}
              disabled={isRejecting || !rejectReason.trim()}
              className={cn(
                'rounded-md px-3 py-2 text-sm font-semibold transition',
                isRejecting || !rejectReason.trim()
                  ? 'bg-red-100 text-red-500 cursor-not-allowed'
                  : 'bg-red-600 text-white hover:bg-red-700',
              )}
            >
              {isRejecting ? '…' : 'Confirmer le rejet'}
            </button>
            <button
              onClick={() => {
                setShowRejectPrompt(false);
                setRejectReason('');
                setError(null);
              }}
              disabled={isRejecting}
              className="rounded-md border border-line px-3 py-2 text-sm transition hover:bg-soft disabled:opacity-50"
            >
              Annuler
            </button>
          </div>
        )}

        {error && <p className="mt-2 text-xs text-red-600">{error}</p>}

        <div className="mt-3 flex items-center gap-2 border-t border-line pt-3">
          <button
            onClick={() => void handleSave()}
            disabled={isSubmitting || isArchived}
            className={cn(
              'rounded-md px-3 py-2 text-sm font-semibold transition',
              isSubmitting || isArchived
                ? 'bg-primary/50 text-white cursor-not-allowed'
                : 'bg-primary text-white hover:bg-primary-600',
            )}
          >
            {isSubmitting ? '…' : 'Enregistrer'}
          </button>
          <button
            onClick={onCancel}
            disabled={isSubmitting || isRejecting}
            className="rounded-md border border-line px-3 py-2 text-sm transition hover:bg-soft disabled:opacity-50"
          >
            Annuler l&apos;édition
          </button>
          {canReject && (
            <button
              onClick={() => setShowRejectPrompt(true)}
              disabled={isSubmitting || isRejecting || showRejectPrompt}
              className={cn(
                'ml-auto rounded-md px-3 py-2 text-sm font-semibold transition',
                showRejectPrompt || isSubmitting || isRejecting
                  ? 'bg-red-50 text-red-500 cursor-not-allowed'
                  : 'bg-red-50 text-red-700 hover:bg-red-100',
              )}
            >
              Rejeter
            </button>
          )}
        </div>
      </td>
    </tr>
  );
}
