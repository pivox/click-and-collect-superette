"use client";

import { Plus, Star } from "lucide-react";
import type { ProductOffer } from "@/types";
import { formatTnd } from "@/lib/format";
import { cn } from "@/lib/cn";
import { useClientLocale } from "@/lib/i18n/ClientLocaleContext";
import { ProductThumbnail } from "./ProductThumbnail";

export interface ProductCardProps {
  product: ProductOffer;
  onAdd?: (product: ProductOffer) => void;
  /** Favorite state — the star is rendered only when onToggleFavorite is provided (authenticated client). */
  isFavorite?: boolean;
  onToggleFavorite?: (product: ProductOffer, next: boolean) => void;
  className?: string;
}

/**
 * Compact product tile used in the mobile catalog (2-column grid) and the
 * desktop catalog (auto-fill grid). Visual matches the prototype's
 * `.product-card` / `.product` patterns.
 */
export function ProductCard({ product, onAdd, isFavorite = false, onToggleFavorite, className }: ProductCardProps) {
  const { t } = useClientLocale();
  const stockLabel = product.isAvailable
    ? t("client.product.available")
    : t("client.product.outOfStock");
  const hasActivePromotion = product.promotionActive && product.effectivePriceTnd;
  return (
    <article
      className={cn(
        "bg-card rounded-lg border border-line p-3 shadow-card",
        className,
      )}
    >
      <div className="relative">
        <ProductThumbnail
          image={product.image}
          nameFr={product.nameFr}
          emoji={product.emoji}
          sizes="(max-width: 768px) 45vw, 200px"
          className="mb-2 h-[94px] rounded-md text-3xl"
        />
        {onToggleFavorite && (
          <button
            type="button"
            onClick={() => onToggleFavorite(product, !isFavorite)}
            aria-pressed={isFavorite}
            aria-label={`${isFavorite ? t("client.favorites.remove") : t("client.favorites.add")} ${product.nameFr}`}
            className="absolute end-1 top-1 grid h-7 w-7 place-items-center rounded-full bg-white/90 shadow-card"
          >
            <Star
              size={15}
              className={isFavorite ? "fill-amber-400 text-amber-400" : "text-muted"}
            />
          </button>
        )}
      </div>
      <strong className="block min-h-[36px] text-sm leading-snug">
        {product.nameFr}
      </strong>
      <span className="block text-xs text-muted">
        {product.volume != null ? `${product.volume} ${product.unit ?? ""} · ` : ""}
        {stockLabel}
      </span>
      <div className="mt-2 flex items-center justify-between">
        {hasActivePromotion ? (
          <span className="flex flex-col">
            <span className="text-xs font-bold text-muted line-through">
              {formatTnd(product.priceTnd)}
            </span>
            <span className="font-black text-status-ready">
              {formatTnd(product.effectivePriceTnd ?? product.priceTnd)}
            </span>
          </span>
        ) : (
          <span className="font-black text-primary-dark">
            {formatTnd(product.priceTnd)}
          </span>
        )}
        {onAdd && (
          <button
            type="button"
            onClick={() => onAdd(product)}
            aria-label={`${t("client.product.add")} ${product.nameFr}`}
            className="grid h-9 w-9 place-items-center rounded bg-primary text-white hover:bg-primary-dark"
          >
            <Plus size={18} strokeWidth={3} />
          </button>
        )}
      </div>
    </article>
  );
}
