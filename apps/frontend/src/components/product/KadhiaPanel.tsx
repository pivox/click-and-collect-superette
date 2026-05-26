"use client";

import Link from "next/link";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { Summary, SummaryRow } from "@/components/ui/Summary";
import { KadhiaLineRow } from "@/components/product/KadhiaLineRow";
import { formatTnd } from "@/lib/format";
import type { Kadhia } from "@/types";

interface KadhiaPanelProps {
  kadhia: Kadhia | null;
}

export function KadhiaPanel({ kadhia }: KadhiaPanelProps) {
  const lines = kadhia?.lines ?? [];
  const total = kadhia?.totalTnd ?? "0.000";
  const isEmpty = lines.length === 0;
  const totalQty = lines.reduce((acc, l) => acc + l.quantity, 0);

  return (
    <Card className="sticky top-7 rounded-xl p-5">
      <div className="mb-3 flex items-baseline justify-between">
        <h2 className="m-0 text-h2 font-extrabold">Ma Kadhia</h2>
        {!isEmpty && (
          <span className="text-xs font-extrabold text-primary">
            {totalQty} article{totalQty > 1 ? "s" : ""}
          </span>
        )}
      </div>

      {isEmpty ? (
        <p className="py-4 text-center text-sm text-muted">
          Kadhia vide — ajoute des produits
        </p>
      ) : (
        <>
          <div className="grid gap-2 mb-4">
            {lines.map((l) => (
              <KadhiaLineRow key={l.id} line={l} />
            ))}
          </div>
          <Summary>
            <SummaryRow label="Total estimé" value={formatTnd(total)} total />
          </Summary>
        </>
      )}

      <div className="mt-4">
        {isEmpty ? (
          <Button full disabled>
            Choisir un créneau
          </Button>
        ) : (
          <Link href="/kadhia/slot">
            <Button full>Choisir un créneau</Button>
          </Link>
        )}
      </div>

      <p className="mt-2 text-xs text-muted">
        Prix figés à la soumission.
      </p>
    </Card>
  );
}
