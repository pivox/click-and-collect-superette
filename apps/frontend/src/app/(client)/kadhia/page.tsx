"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
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
  discardKadhia,
} from "@/lib/services";
import { formatTnd } from "@/lib/format";
import type { Kadhia } from "@/types";

export default function KadhiaPage() {
  const router = useRouter();
  const [kadhia, setKadhia] = useState<Kadhia | null>(null);
  const [shopId, setShopId] = useState<string | null>(null);
  const [quantityError, setQuantityError] = useState<string | null>(null);
  const [discarding, setDiscarding] = useState(false);

  useEffect(() => {
    const local = readLocalKadhia();
    const sid = local?.shopId ?? null;
    setShopId(sid);
    if (sid) {
      void getCurrentKadhia(sid)
        .then((remote) => {
          // If the backend has no kadhia yet, prefer the locally-built one
          if (remote.lines.length === 0) {
            const local = readLocalKadhia();
            if (local?.shopId === sid && local.lines.length > 0) {
              setKadhia(local);
              return;
            }
          }
          setKadhia(remote);
        })
        .catch((err: unknown) => {
          const status = (err as { response?: { status?: number } }).response?.status;
          if (status !== 404 && status !== 405) {
            console.error("[KadhiaPage] getCurrentKadhia failed:", err);
          }
          const local = readLocalKadhia();
          if (local?.shopId === sid) setKadhia(local);
        });
    }
  }, []);

  const onDiscard = async () => {
    if (!shopId) return;
    if (!window.confirm("Supprimer cette Kadhia ? Tu pourras recommencer depuis le catalogue.")) return;
    setDiscarding(true);
    try {
      await discardKadhia(shopId);
      router.push(shopId ? `/stores/${shopId}/catalog` : "/stores");
    } catch {
      setDiscarding(false);
    }
  };

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

          <div className="mt-4 text-center">
            <button
              onClick={onDiscard}
              disabled={discarding}
              className="text-sm text-red-500 underline disabled:opacity-50"
            >
              {discarding ? "Suppression…" : "Supprimer cette Kadhia"}
            </button>
          </div>

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
