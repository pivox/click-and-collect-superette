'use client';
import { useState } from 'react';
import axios from 'axios';
import {
  updateCategory,
} from '@/lib/services/admin/categories.service';
import type { Category } from '@/lib/types/admin/referentiel.types';
import { cn } from '@/lib/cn';

interface CategoryEditRowProps {
  category: Category;
  colSpan: number;
  onSaved: () => void;
  onCancel: () => void;
}

export function CategoryEditRow({ category, colSpan, onSaved, onCancel }: CategoryEditRowProps) {
  const [nameFr, setNameFr] = useState(category.name_fr);
  const [nameAr, setNameAr] = useState(category.name_ar ?? '');
  const [isActive, setIsActive] = useState(category.is_active);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSave = async () => {
    setError(null);
    if (!nameFr.trim()) {
      setError('Le nom FR est obligatoire.');
      return;
    }
    setIsSubmitting(true);
    try {
      await updateCategory(category.id, {
        nameFr: nameFr.trim(),
        nameAr: nameAr.trim() || null,
        isActive,
      });
      onSaved();
    } catch (e) {
      if (axios.isAxiosError(e)) {
        if (e.response?.status === 409) {
          setError('Cette catégorie a déjà été modifiée ailleurs.');
          return;
        }
        if (e.response?.status === 422) {
          setError('Données invalides (peut-être un nom déjà utilisé).');
          return;
        }
      }
      console.error('[CategoryEditRow] updateCategory failed:', e);
      setError('Une erreur est survenue. Réessayez.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <tr>
      <td colSpan={colSpan} className="border-l-4 border-primary bg-primary/5 px-4 py-4">
        <p className="mb-3 text-xs font-semibold text-primary">
          ↳ Modifier — {category.name_fr}
        </p>

        <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
          <div>
            <label className="mb-1 block text-xs font-semibold">Nom FR *</label>
            <input
              type="text"
              value={nameFr}
              onChange={(e) => setNameFr(e.target.value)}
              disabled={isSubmitting}
              placeholder="Nom de la catégorie…"
              className={cn(
                'w-full rounded-md border border-line px-3 py-2 text-sm outline-none transition',
                'focus:border-primary focus:ring-2 focus:ring-primary/20',
                isSubmitting && 'bg-soft opacity-50 cursor-not-allowed',
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
              disabled={isSubmitting}
              placeholder="اسم الفئة…"
              className={cn(
                'w-full rounded-md border border-line px-3 py-2 text-sm outline-none transition',
                'focus:border-primary focus:ring-2 focus:ring-primary/20',
                isSubmitting && 'bg-soft opacity-50 cursor-not-allowed',
              )}
            />
          </div>

          <div>
            <label className="mb-1 block text-xs font-semibold">Statut</label>
            <select
              value={isActive ? 'actif' : 'inactif'}
              onChange={(e) => setIsActive(e.target.value === 'actif')}
              disabled={isSubmitting}
              className={cn(
                'w-full rounded-md border border-line px-3 py-2 text-sm outline-none transition',
                'focus:border-primary focus:ring-2 focus:ring-primary/20',
                isSubmitting && 'bg-soft opacity-50 cursor-not-allowed',
              )}
            >
              <option value="actif">Actif</option>
              <option value="inactif">Inactif</option>
            </select>
          </div>
        </div>

        {error && <p className="mt-2 text-xs text-red-600">{error}</p>}

        <div className="mt-3 flex items-center gap-2 border-t border-line pt-3">
          <button
            onClick={() => void handleSave()}
            disabled={isSubmitting}
            className={cn(
              'rounded-md px-3 py-2 text-sm font-semibold transition',
              isSubmitting
                ? 'bg-primary/50 text-white cursor-not-allowed'
                : 'bg-primary text-white hover:bg-primary-600',
            )}
          >
            {isSubmitting ? '…' : 'Enregistrer'}
          </button>
          <button
            onClick={onCancel}
            disabled={isSubmitting}
            className="rounded-md border border-line px-3 py-2 text-sm transition hover:bg-soft disabled:opacity-50"
          >
            Annuler l&apos;édition
          </button>
        </div>
      </td>
    </tr>
  );
}
