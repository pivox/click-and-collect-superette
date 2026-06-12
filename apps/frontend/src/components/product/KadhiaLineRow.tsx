"use client";

import type { KadhiaLine } from "@/types";
import { Card } from "@/components/ui/Card";
import { QtyControl } from "@/components/ui/QtyControl";
import { formatTnd } from "@/lib/format";
import { useClientLocale } from "@/lib/i18n/ClientLocaleContext";
import { ProductThumbnail } from "./ProductThumbnail";

export interface KadhiaLineRowProps {
  line: KadhiaLine;
  onQuantity?: (lineId: string, quantity: number) => void;
}

/**
 * One row in the cart screen. The product image swatch on the left, name +
 * line total in the middle, qty stepper on the right.
 */
export function KadhiaLineRow({ line, onQuantity }: KadhiaLineRowProps) {
  const { t } = useClientLocale();
  const p = line.productOffer;
  return (
    <Card compact className="flex items-center gap-3">
      <ProductThumbnail
        image={p.image}
        nameFr={p.nameFr}
        emoji={p.emoji}
        sizes="54px"
        className="h-[54px] w-[54px] flex-shrink-0 rounded-md text-2xl"
      />
      <div className="flex-1 min-w-0">
        <strong className="block text-sm truncate">
          {p.nameFr}
          {p.volume != null && (
            <span className="text-muted font-normal"> · {p.volume}{p.unit}</span>
          )}
        </strong>
        <span className="mt-0.5 block text-xs text-muted">
          {formatTnd(line.unitPriceTnd)} · x{line.quantity}
        </span>
        {!p.isAvailable && (
          <span className="mt-0.5 inline-block rounded bg-red-50 px-1.5 py-0.5 text-[10px] font-bold text-red-600">
            {t("client.product.outOfStock")}
          </span>
        )}
      </div>
      <QtyControl
        value={line.quantity}
        onChange={(q) => onQuantity?.(line.id, q)}
      />
    </Card>
  );
}
