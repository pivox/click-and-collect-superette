"use client";

import { Plus } from "lucide-react";
import type { ProductOffer } from "@/types";
import { formatTnd } from "@/lib/format";
import { cn } from "@/lib/cn";

export interface ProductCardProps {
  product: ProductOffer;
  onAdd?: (product: ProductOffer) => void;
  className?: string;
}

/**
 * Compact product tile used in the mobile catalog (2-column grid) and the
 * desktop catalog (auto-fill grid). Visual matches the prototype's
 * `.product-card` / `.product` patterns.
 */
export function ProductCard({ product, onAdd, className }: ProductCardProps) {
  const stockLabel = product.isAvailable ? "Disponible" : "Rupture";
  return (
    <article
      className={cn(
        "bg-card rounded-lg border border-line p-3 shadow-card",
        className,
      )}
    >
      <div className="mb-2 grid h-[94px] place-items-center rounded-md bg-product-tile text-3xl">
        {product.emoji ?? product.nameFr.charAt(0)}
      </div>
      <strong className="block min-h-[36px] text-sm leading-snug">
        {product.nameFr}
      </strong>
      <span className="block text-xs text-muted">
        {product.volume != null ? `${product.volume} ${product.unit ?? ""} · ` : ""}
        {stockLabel}
      </span>
      <div className="mt-2 flex items-center justify-between">
        <span className="font-black text-primary-dark">
          {formatTnd(product.priceTnd)}
        </span>
        {onAdd && (
          <button
            type="button"
            onClick={() => onAdd(product)}
            aria-label={`Ajouter ${product.nameFr}`}
            className="grid h-9 w-9 place-items-center rounded bg-primary text-white hover:bg-primary-dark"
          >
            <Plus size={18} strokeWidth={3} />
          </button>
        )}
      </div>
    </article>
  );
}
