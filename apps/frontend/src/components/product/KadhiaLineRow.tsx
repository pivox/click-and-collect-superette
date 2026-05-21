"use client";

import type { KadhiaLine } from "@/types";
import { Card } from "@/components/ui/Card";
import { QtyControl } from "@/components/ui/QtyControl";
import { formatTnd } from "@/lib/format";

export interface KadhiaLineRowProps {
  line: KadhiaLine;
  onQuantity?: (lineId: string, quantity: number) => void;
}

/**
 * One row in the cart screen. The product image swatch on the left, name +
 * line total in the middle, qty stepper on the right.
 */
export function KadhiaLineRow({ line, onQuantity }: KadhiaLineRowProps) {
  const p = line.productOffer;
  return (
    <Card compact className="flex items-center gap-3">
      <div className="grid h-[54px] w-[54px] flex-shrink-0 place-items-center rounded-md bg-product-tile text-2xl">
        {p.emoji ?? p.nameFr.charAt(0)}
      </div>
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
      </div>
      <QtyControl
        value={line.quantity}
        onChange={(q) => onQuantity?.(line.id, q)}
      />
    </Card>
  );
}
