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
} from "@/lib/services";
import { formatTnd } from "@/lib/format";
import type { Kadhia } from "@/types";

// MVP: Kadhia lives on a single demo shop on the client side.
const DEMO_SHOP_ID = "shop-el-amel";

export default function KadhiaPage() {
  const [kadhia, setKadhia] = useState<Kadhia | null>(null);

  useEffect(() => {
    void getCurrentKadhia(DEMO_SHOP_ID).then(setKadhia);
  }, []);

  const onQuantity = async (lineId: string, q: number) => {
    const next = await updateLineQuantity(DEMO_SHOP_ID, lineId, q);
    setKadhia(next);
  };

  const empty = !kadhia || kadhia.lines.length === 0;
  const articleCount = kadhia?.lines.reduce((a, l) => a + l.quantity, 0) ?? 0;

  return (
    <>
      <TopBar
        title="Ma Kadhia"
        subtitle={
          empty
            ? "Aucun article pour le moment"
            : `${articleCount} article${articleCount > 1 ? "s" : ""} · Superette El Amel`
        }
        backHref={`/stores/${DEMO_SHOP_ID}/catalog`}
      />

      {empty ? (
        <Card className="text-center">
          <h3 className="mt-2 text-h3 font-extrabold">Ta Kadhia est vide</h3>
          <p className="mt-2 text-sm text-muted">
            Ajoute des produits depuis le catalogue de ta supérette.
          </p>
          <Link href={`/stores/${DEMO_SHOP_ID}/catalog`} className="mt-4 inline-block">
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
