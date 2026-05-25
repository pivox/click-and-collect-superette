'use client';

import type {
  MerchantCatalogAvailabilityFilter,
  MerchantCatalogListOptions,
  MerchantCatalogVisibilityFilter,
} from '@/lib/types/merchant-catalog.types';
import { cn } from '@/lib/cn';

interface MerchantCatalogFiltersProps {
  filters: MerchantCatalogListOptions;
  onFiltersChange: (filters: MerchantCatalogListOptions) => void;
  onSubmit: () => void;
}

const availabilityOptions: Array<{
  label: string;
  value: MerchantCatalogAvailabilityFilter;
}> = [
  { label: 'Tous', value: 'all' },
  { label: 'Disponibles', value: 'available' },
  { label: 'Indisponibles', value: 'unavailable' },
];

export function MerchantCatalogFilters({
  filters,
  onFiltersChange,
  onSubmit,
}: MerchantCatalogFiltersProps) {
  const updateFilter = (nextFilters: MerchantCatalogListOptions) => {
    onFiltersChange({ ...filters, ...nextFilters });
  };

  const visibility: MerchantCatalogVisibilityFilter = filters.visibility ?? 'all';
  const availability: MerchantCatalogAvailabilityFilter = filters.availability ?? 'all';

  return (
    <form
      className="mt-5 rounded-md bg-card p-4 shadow-card"
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit();
      }}
    >
      <div className="grid gap-3 lg:grid-cols-[minmax(220px,1fr)_auto_auto] lg:items-end">
        <div>
          <label className="mb-1 block text-sm font-bold" htmlFor="merchant-catalog-search">
            Recherche
          </label>
          <input
            id="merchant-catalog-search"
            aria-label="Rechercher dans le catalogue"
            value={filters.q ?? ''}
            onChange={(event) => updateFilter({ q: event.currentTarget.value })}
            className="h-11 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            type="search"
            placeholder="Nom, marque, catégorie, note marchand"
          />
        </div>

        <div>
          <span id="merchant-catalog-availability-label" className="mb-1 block text-sm font-bold">
            Disponibilité
          </span>
          <div
            role="group"
            aria-labelledby="merchant-catalog-availability-label"
            className="flex flex-wrap gap-2"
          >
            {availabilityOptions.map((option) => (
              <button
                key={option.value}
                type="button"
                className={cn(
                  'min-h-[44px] rounded-md px-3 text-sm font-black transition-colors',
                  availability === option.value ? 'bg-primary text-white' : 'bg-soft text-muted',
                )}
                aria-pressed={availability === option.value}
                onClick={() => updateFilter({ availability: option.value })}
              >
                {option.label}
              </button>
            ))}
          </div>
        </div>

        <div
          role="group"
          aria-label="Visibilité catalogue"
          className="flex flex-wrap gap-2"
        >
          <button
            type="button"
            className={cn(
              'min-h-[44px] rounded-md px-3 text-sm font-black transition-colors',
              visibility === 'hidden' ? 'bg-primary text-white' : 'bg-soft text-muted',
            )}
            aria-pressed={visibility === 'hidden'}
            aria-label={
              visibility === 'hidden'
                ? 'Afficher tous les produits visibles et masqués'
                : 'Afficher uniquement les produits masqués'
            }
            onClick={() => updateFilter({ visibility: visibility === 'hidden' ? 'all' : 'hidden' })}
          >
            Masqués
          </button>
          <button
            type="submit"
            className="min-h-[44px] rounded-md bg-primary px-4 text-sm font-black text-white transition-colors hover:bg-primary-dark"
          >
            Rechercher
          </button>
        </div>
      </div>
    </form>
  );
}
