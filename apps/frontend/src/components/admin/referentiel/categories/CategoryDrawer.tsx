'use client';
import { useState, useEffect } from 'react';
import axios from 'axios';
import { AdminDrawer } from '@/components/admin/ui/AdminDrawer';
import { createCategory, updateCategory } from '@/lib/services/admin/categories.service';
import type { Category } from '@/lib/types/admin/referentiel.types';

interface CategoryDrawerProps {
  open: boolean;
  onClose: () => void;
  category: Category | null;
  onSaved: () => void;
}

export function CategoryDrawer({ open, onClose, category, onSaved }: CategoryDrawerProps) {
  const [nameFr, setNameFr] = useState('');
  const [nameAr, setNameAr] = useState('');
  const [slug, setSlug] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (category) {
      setNameFr(category.name_fr);
      setNameAr(category.name_ar ?? '');
      setIsActive(category.is_active);
    } else {
      setNameFr('');
      setNameAr('');
      setSlug('');
      setIsActive(true);
    }
    setError(null);
  }, [category, open]);

  const handleSubmit = async () => {
    if (!nameFr.trim()) { setError('Le nom FR est obligatoire.'); return; }
    setIsSubmitting(true);
    setError(null);
    try {
      if (category) {
        await updateCategory(category.id, {
          nameFr: nameFr.trim(),
          nameAr: nameAr.trim() || undefined,
          isActive,
        });
      } else {
        await createCategory({
          nameFr: nameFr.trim(),
          nameAr: nameAr.trim() || undefined,
          slug: slug.trim() || undefined,
        });
      }
      onSaved();
    } catch (e) {
      setError(axios.isAxiosError(e) && e.response?.status === 409
        ? 'Un nom ou slug identique existe déjà.'
        : 'Une erreur est survenue. Réessayez.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <AdminDrawer
      open={open}
      onClose={onClose}
      title={category ? 'Modifier la catégorie' : 'Nouvelle catégorie'}
      onSubmit={handleSubmit}
      isSubmitting={isSubmitting}
    >
      <div className="space-y-4">
        {error && (
          <div className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
            {error}
          </div>
        )}
        <div>
          <label className="mb-1 block text-sm font-semibold">Nom FR *</label>
          <input
            type="text"
            value={nameFr}
            onChange={(e) => setNameFr(e.target.value)}
            maxLength={160}
            className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>
        <div>
          <label className="mb-1 block text-sm font-semibold">Nom AR</label>
          <input
            type="text"
            value={nameAr}
            onChange={(e) => setNameAr(e.target.value)}
            maxLength={160}
            dir="rtl"
            className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>
        {!category && (
          <div>
            <label className="mb-1 block text-sm font-semibold">
              Slug{' '}
              <span className="font-normal text-muted">(auto-généré si vide)</span>
            </label>
            <input
              type="text"
              value={slug}
              onChange={(e) => setSlug(e.target.value)}
              maxLength={180}
              placeholder="produits-laitiers"
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>
        )}
        {category && (
          <div className="flex items-center gap-3">
            <label className="text-sm font-semibold">Actif</label>
            <button
              type="button"
              onClick={() => setIsActive((v) => !v)}
              className={`relative h-6 w-11 rounded-full transition-colors ${isActive ? 'bg-primary' : 'bg-line'}`}
            >
              <span
                className={`absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform ${isActive ? 'translate-x-5' : 'translate-x-0.5'}`}
              />
            </button>
            <span className="text-sm text-muted">{isActive ? 'Oui' : 'Non'}</span>
          </div>
        )}
      </div>
    </AdminDrawer>
  );
}
