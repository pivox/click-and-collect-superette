import Link from "next/link";
import { notFound } from "next/navigation";
import { Hero } from "@/components/layout/Hero";
import { TopBar } from "@/components/layout/TopBar";
import { Card } from "@/components/ui/Card";
import { Button } from "@/components/ui/Button";
import { Summary, SummaryRow } from "@/components/ui/Summary";
import { StickyBottom } from "@/components/layout/StickyBottom";
import { getShop } from "@/lib/services";

export default async function StoreDetailPage({
  params,
}: {
  params: { shopId: string };
}) {
  const shop = await getShop(params.shopId);
  if (!shop) notFound();

  const badgeText = shop.isActive
    ? `Ouverte · Retrait dès ${shop.nextPickupAt ?? "—"}`
    : "Fermée";

  return (
    <>
      <TopBar
        title={shop.name}
        subtitle={[shop.address, shop.city].filter(Boolean).join(" · ")}
        backHref="/"
      />

      {/* Desktop : hero gauche + infos droite */}
      <div className="md:grid md:grid-cols-[1.3fr_0.7fr] md:gap-5">
        <Hero
          badge={badgeText}
          title={shop.name}
          subtitle="Produits du quotidien, boissons, lait, pâtes, conserves, hygiène et snacks."
        />

        <div className="mt-4 md:mt-0 space-y-3">
          <div className="grid grid-cols-2 gap-2.5">
            <Card compact>
              <strong className="block text-sm">Horaires</strong>
              <span className="mt-1 block text-xs text-muted">
                {shop.opensAt} — {shop.closesAt}
              </span>
            </Card>
            <Card compact>
              <strong className="block text-sm">Distance</strong>
              <span className="mt-1 block text-xs text-muted">
                {shop.distanceKm != null ? `${shop.distanceKm} km` : "—"}
              </span>
            </Card>
          </div>

          <Card>
            <Summary>
              <SummaryRow label="Paiement" value="Sur place" />
              <SummaryRow
                label="Créneau"
                value={shop.nextPickupAt ? `Dès ${shop.nextPickupAt}` : "—"}
              />
              <SummaryRow label="Note" value={shop.rating?.toFixed(1) ?? "—"} />
            </Summary>
          </Card>

          {/* CTA inline sur desktop */}
          <div className="hidden md:block">
            <Link href={`/stores/${shop.id}/catalog`}>
              <Button full>Commencer ma Kadhia</Button>
            </Link>
          </div>
        </div>
      </div>

      {/* CTA sticky sur mobile */}
      <StickyBottom className="md:hidden">
        <Link href={`/stores/${shop.id}/catalog`}>
          <Button full>Commencer ma Kadhia</Button>
        </Link>
      </StickyBottom>
    </>
  );
}
