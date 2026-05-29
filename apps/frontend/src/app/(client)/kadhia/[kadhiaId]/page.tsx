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
import { fetchKadhia, updateLineQuantity, discardKadhia } from "@/lib/services";
import { formatTnd } from "@/lib/format";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";
import type { Kadhia } from "@/types";

export default function KadhiaDetailPage({
  params,
}: {
  params: { kadhiaId: string };
}) {
  const { kadhiaId } = params;
  const router = useRouter();
  const { user, isLoading } = useClientAuth();
  const [kadhia, setKadhia] = useState<Kadhia | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [quantityError, setQuantityError] = useState<string | null>(null);
  const [discarding, setDiscarding] = useState(false);

  useEffect(() => {
    if (!isLoading && !user) {
      router.push(`/login?redirect=/kadhia/${kadhiaId}`);
    }
  }, [isLoading, user, router, kadhiaId]);

  useEffect(() => {
    if (isLoading || !user) return;
    void fetchKadhia(kadhiaId)
      .then(setKadhia)
      .catch(() => setLoadError("Kadhia introuvable."));
  }, [kadhiaId, isLoading, user]);

  const onDiscard = async () => {
    if (!kadhia?.shopId) return;
    if (!window.confirm("Supprimer cette Kadhia ? Tu pourras recommencer depuis le catalogue.")) return;
    setDiscarding(true);
    try {
      await discardKadhia(kadhia.shopId);
      router.push("/kadhia");
    } catch {
      setDiscarding(false);
    }
  };

  const onQuantity = async (lineId: string, q: number) => {
    if (!kadhia?.shopId || !kadhia.id) return;
    setQuantityError(null);
    try {
      const next = await updateLineQuantity(kadhia.shopId, kadhia.id, lineId, q);
      setKadhia(next);
    } catch {
      setQuantityError("Impossible de mettre à jour la quantité. Réessaie.");
    }
  };

  if (isLoading || !user) return null;

  if (loadError) {
    return (
      <>
        <TopBar title="Kadhia" backHref="/kadhia" />
        <Card className="text-center">
          <p className="text-sm text-muted">{loadError}</p>
          <Link href="/kadhia" className="mt-4 inline-block">
            <Button>Mes Kadhia</Button>
          </Link>
        </Card>
      </>
    );
  }

  if (!kadhia) {
    return (
      <>
        <TopBar title="Kadhia" backHref="/kadhia" />
        <div className="animate-pulse grid gap-2.5">
          {[1, 2, 3].map((i) => (
            <div key={i} className="h-20 rounded-lg bg-gray-100" />
          ))}
        </div>
      </>
    );
  }

  const isDraft = kadhia.status === "draft";
  const articleCount = kadhia.lines.reduce((a, l) => a + l.quantity, 0);
  const catalogHref = `/stores/${kadhia.shopId}/catalog`;

  return (
    <>
      <TopBar
        title="Ma Kadhia"
        subtitle={
          kadhia.lines.length === 0
            ? "Aucun article"
            : `${articleCount} article${articleCount > 1 ? "s" : ""}`
        }
        backHref="/kadhia"
      />

      {kadhia.lines.length === 0 ? (
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
              <KadhiaLineRow
                key={l.id}
                line={l}
                onQuantity={isDraft ? onQuantity : undefined}
              />
            ))}
          </section>

          {quantityError && (
            <p className="mt-2 rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">
              {quantityError}
            </p>
          )}

          <Card className="mt-4">
            <Summary>
              <SummaryRow label="Sous-total" value={formatTnd(kadhia.totalTnd)} />
              <SummaryRow label="Service" value={formatTnd("0.000")} />
              <SummaryRow total label="Total estimé" value={formatTnd(kadhia.totalTnd)} />
            </Summary>
          </Card>

          <p className="mt-3 text-xs text-muted leading-relaxed">
            Le prix sera figé au moment de la soumission de commande.
          </p>

          {isDraft && (
            <div className="mt-4 text-center">
              <button
                onClick={onDiscard}
                disabled={discarding}
                className="text-sm text-red-500 underline disabled:opacity-50"
              >
                {discarding ? "Suppression…" : "Supprimer cette Kadhia"}
              </button>
            </div>
          )}

          <StickyBottom>
            {isDraft ? (
              <Link href="/kadhia/slot">
                <Button full>Choisir un créneau</Button>
              </Link>
            ) : kadhia.orderId ? (
              <Link href={`/orders/${kadhia.orderId}`}>
                <Button full>Suivre la commande</Button>
              </Link>
            ) : null}
          </StickyBottom>
        </>
      )}
    </>
  );
}
