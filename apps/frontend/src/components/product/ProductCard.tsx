"use client";

import { Plus } from "lucide-react";
import type { ProductOffer } from "@/types";
import { formatTnd } from "@/lib/format";
import { cn } from "@/lib/cn";
import { useClientLocale } from "@/lib/i18n/ClientLocaleContext";
import { ProductThumbnail } from "./ProductThumbnail";

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
  const { t } = useClientLocale();
  const stockLabel = product.isAvailable
    ? t("client.product.available")
    : t("client.product.outOfStock");
  return (
    <article
      className={cn(
        "bg-card rounded-lg border border-line p-3 shadow-card",
        className,
      )}
    >
      <ProductThumbnail
        image={product.image}
        nameFr={product.nameFr}
        emoji={product.emoji}
        sizes="(max-width: 768px) 45vw, 200px"
        className="mb-2 h-[94px] rounded-md text-3xl"
      />
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
