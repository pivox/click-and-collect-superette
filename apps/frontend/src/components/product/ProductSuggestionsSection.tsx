"use client";

import type { ProductOffer } from "@/types";
import { ProductCard } from "./ProductCard";

export interface ProductSuggestionsSectionProps {
  title: string;
  products: ProductOffer[];
  onAdd?: (product: ProductOffer) => void;
  favoriteIds?: Set<string>;
  onToggleFavorite?: (product: ProductOffer, next: boolean) => void;
}

/**
 * Horizontal scroll row of product cards used for the catalog suggestion
 * sections (favorites, frequently bought together, recently ordered).
 * Renders nothing when the list is empty — no empty state by design.
 */
export function ProductSuggestionsSection({
  title,
  products,
  onAdd,
  favoriteIds,
  onToggleFavorite,
}: ProductSuggestionsSectionProps) {
  if (products.length === 0) return null;

  return (
    <section className="mb-4">
      <h3 className="mb-2 text-h3 font-extrabold">{title}</h3>
      <div className="flex gap-2.5 overflow-x-auto pb-1">
        {products.map((p) => (
          <ProductCard
            key={p.id}
            product={p}
            onAdd={onAdd && p.isAvailable ? onAdd : undefined}
            isFavorite={favoriteIds?.has(p.id) ?? false}
            onToggleFavorite={onToggleFavorite}
            className="w-40 shrink-0"
          />
        ))}
      </div>
    </section>
  );
}
