"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { TopBar } from "@/components/layout/TopBar";
import { Card } from "@/components/ui/Card";
import { Button } from "@/components/ui/Button";
import { Summary, SummaryRow } from "@/components/ui/Summary";
import { StickyBottom } from "@/components/layout/StickyBottom";
import { KadhiaLineRow } from "@/components/product/KadhiaLineRow";
import {
  getCurrentKadhia,
  updateLineQuantity,
  readLocalKadhia,
} from "@/lib/services";
import { formatTnd } from "@/lib/format";
import type { Kadhia } from "@/types";

export default function KadhiaPage() {
  const [kadhia, setKadhia] = useState<Kadhia | null>(null);
  const [shopId, setShopId] = useState<string | null>(null);
  const [quantityError, setQuantityError] = useState<string | null>(null);

  useEffect(() => {
    const local = readLocalKadhia();
    const sid = local?.shopId ?? null;
    setShopId(sid);
    if (sid) {
      void getCurrentKadhia(sid)
        .then(setKadhia)
        .catch((err: unknown) => {
          const status = (err as { response?: { status?: number } }).response?.status;
          if (status !== 404 && status !== 405) {
            console.error("[KadhiaPage] getCurrentKadhia failed:", err);
          }
        });
    }
  }, []);

  const onQuantity = async (lineId: string, q: number) => {
    if (!shopId) return;
    setQuantityError(null);
    try {
      const next = await updateLineQuantity(shopId, lineId, q);
      setKadhia(next);
    } catch {
      setQuantityError("Impossible de mettre à jour la quantité. Réessaie.");
    }
  };

  const empty = !kadhia || kadhia.lines.length === 0;
  const articleCount = kadhia?.lines.reduce((a, l) => a + l.quantity, 0) ?? 0;
  const catalogHref = shopId ? `/stores/${shopId}/catalog` : "/stores";

  return (
    <>
      <TopBar
        title="Ma Kadhia"
        subtitle={
          empty
            ? "Aucun article pour le moment"
            : `${articleCount} article${articleCount > 1 ? "s" : ""}`
        }
        backHref={catalogHref}
      />

      {empty ? (
        <Card className="text-center">
          <h3 className="mt-2 text-h3 font-extrabold">Ta Kadhia est vide</h3>
          <p className="mt-2 text-sm text-muted">
            Ajoute des produits depuis le catalogue de ta supérette.
          </p>
          <Link href={catalogHref} className="mt-4 inline-block">
            <Button>Aller au catalogue</Button>
          </Link>
        </Card>
      ) : (
        <>
          <section className="grid gap-2.5">
            {kadhia.lines.map((l) => (
              <KadhiaLineRow key={l.id} line={l} onQuantity={onQuantity} />
            ))}
          </section>

          {quantityError && (
            <p className="mt-2 rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">
              {quantityError}
            </p>
          )}

          <Card className="mt-4">
            <Summary>
              <SummaryRow
                label="Sous-total"
                value={formatTnd(kadhia.totalTnd)}
              />
              <SummaryRow label="Service" value={formatTnd("0.000")} />
              <SummaryRow
                total
                label="Total estimé"
                value={formatTnd(kadhia.totalTnd)}
              />
            </Summary>
          </Card>

          <p className="mt-3 text-xs text-muted leading-relaxed">
            Le prix sera figé au moment de la soumission de commande.
          </p>

          <StickyBottom>
            <Link href="/kadhia/slot">
              <Button full>Choisir un créneau</Button>
            </Link>
          </StickyBottom>
        </>
      )}
    </>
  );
}
