'use client';

import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/Button';
import type { MerchantCategory } from '@/lib/types/merchant-catalog.types';

interface MerchantCategorySelectorProps {
  categories: MerchantCategory[];
  fallbackCategory: string;
  value: string | null;
  onChange: (categoryId: string | null) => void;
  onCreate?: (nameFr: string) => Promise<MerchantCategory>;
  disabled?: boolean;
  message?: string | null;
}

export function MerchantCategorySelector({
  categories,
  disabled = false,
  fallbackCategory,
  message,
  onChange,
  onCreate,
  value,
}: MerchantCategorySelectorProps) {
  const [newCategoryName, setNewCategoryName] = useState('');
  const [createError, setCreateError] = useState<string | null>(null);
  const [isCreating, setIsCreating] = useState(false);
  const activeCategories = useMemo(
    () => categories.filter((category) => category.active),
    [categories],
  );

  const handleCreate = async () => {
    if (!onCreate) return;

    const normalizedName = newCategoryName.trim();
    if (!normalizedName) {
      setCreateError('Le nom français de la catégorie est obligatoire.');
      return;
    }

    setIsCreating(true);
    setCreateError(null);

    try {
      const createdCategory = await onCreate(normalizedName);
      onChange(createdCategory.id);
      setNewCategoryName('');
    } catch {
      setCreateError('Impossible de créer la catégorie marchand.');
    } finally {
      setIsCreating(false);
    }
  };

  return (
    <div className="space-y-3">
      <div>
        <label htmlFor="merchant-category-selector" className="mb-1 block text-sm font-bold">
          Catégorie marchand
        </label>
        <select
          id="merchant-category-selector"
          value={value ?? ''}
          disabled={disabled}
          onChange={(event) => onChange(event.currentTarget.value || null)}
          className="h-11 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        >
          <option value="">Catégorie par défaut : {fallbackCategory}</option>
          {activeCategories.map((category) => (
            <option key={category.id} value={category.id}>
              {category.name_fr}
            </option>
          ))}
        </select>
      </div>

      {message && <p className="text-sm text-muted">{message}</p>}
      {activeCategories.length === 0 && (
        <p className="text-sm text-muted">Aucune catégorie marchand active disponible.</p>
      )}

      {onCreate && (
        <div className="grid gap-2 sm:grid-cols-[1fr_auto]">
          <div>
            <label htmlFor="merchant-new-category" className="mb-1 block text-sm font-bold">
              Nouvelle catégorie marchand
            </label>
            <input
              id="merchant-new-category"
              value={newCategoryName}
              disabled={disabled || isCreating}
              onChange={(event) => {
                setNewCategoryName(event.currentTarget.value);
                if (createError) setCreateError(null);
              }}
              className="h-11 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>
          <Button
            type="button"
            variant="ghost"
            disabled={disabled || isCreating}
            onClick={() => void handleCreate()}
          >
            {isCreating ? 'Création…' : 'Créer la catégorie'}
          </Button>
        </div>
      )}

      {createError && (
        <div role="alert" className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
          {createError}
        </div>
      )}
    </div>
  );
}
