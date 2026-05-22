import { DesktopShell } from "@/components/layout/DesktopShell";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { SearchInput } from "@/components/ui/SearchInput";
import { Pill, PillRow } from "@/components/ui/Pill";
import { ProductCard } from "@/components/product/ProductCard";
import { KadhiaLineRow } from "@/components/product/KadhiaLineRow";
import { Summary, SummaryRow } from "@/components/ui/Summary";
import { SlotTile } from "@/components/ui/SlotTile";
import { Timeline } from "@/components/ui/Timeline";
import { QrPlaceholder } from "@/components/ui/QrPlaceholder";
import { listCatalog, listShops, listSlotsForShop, getOrder, projectTimeline } from "@/lib/services";
import { MOCK_ORDER } from "@/lib/mock/orders.mock";
import { formatTime, formatTnd } from "@/lib/format";

/**
 * Desktop client landing — mirrors prototype-html/user-web-flow.html.
 * Long single-page composition showing hero + catalog + slot + cart +
 * order tracking, all rendered server-side.
 */
export default async function DesktopHome() {
  const [shops, products, slots, order] = await Promise.all([
    listShops(),
    listCatalog({ shopId: "shop-el-amel" }),
    listSlotsForShop("shop-el-amel"),
    getOrder("CMD-4821"),
  ]);

  const featuredShop = shops[0];
  const steps = order ? projectTimeline(order) : [];

  return (
    <DesktopShell
      featuredShopName={featuredShop?.name}
      featuredShopHours={
        featuredShop
          ? `Ouverte aujourd'hui jusqu'à ${featuredShop.closesAt} · retrait dès ${featuredShop.nextPickupAt}`
          : undefined
      }
    >
      {/* Topbar */}
      <header className="mb-6 flex flex-wrap items-center justify-between gap-4">
        <SearchInput
          size="lg"
          placeholder="Rechercher produit, marque, format ou supérette"
          className="max-w-2xl flex-1"
        />
        <div className="flex items-center gap-2">
          <Badge>🇹🇳 TND</Badge>
          <Button variant="ghost">Mon compte</Button>
        </div>
      </header>

      {/* Hero */}
      <section className="mb-6 grid gap-4 md:grid-cols-[1.3fr_0.7fr]">
        <div className="rounded-xl bg-hero-emerald p-7 text-white shadow-cta_lg">
          <Badge tone="onPrimary" className="mb-3">
            Parcours client web
          </Badge>
          <h1 className="m-0 text-display-lg font-black">
            Préparer sa Kadhia depuis un grand écran
          </h1>
          <p className="mt-2.5 mb-5 max-w-xl text-white/80 text-base leading-relaxed">
            Retrouve une supérette, consulte le catalogue, compose ton panier,
            réserve un créneau et suis ta commande.
          </p>
          <Button variant="secondary">Commencer les courses</Button>
        </div>
        <Card className="rounded-xl">
          <Badge>Store reconnu</Badge>
          <h2 className="mt-2 text-h2 font-extrabold">{featuredShop?.name}</h2>
          <p className="text-sm text-muted">
            {featuredShop?.address}, {featuredShop?.city} · paiement sur place · retrait magasin.
          </p>
          <div className="mt-4 grid grid-cols-3 gap-3">
            <KPI label="distance" value={`${featuredShop?.distanceKm} km`} />
            <KPI label="prochain retrait" value={featuredShop?.nextPickupAt ?? "—"} />
            <KPI label="note" value={featuredShop?.rating?.toFixed(1) ?? "—"} />
          </div>
        </Card>
      </section>

      {/* Catalog + cart */}
      <section className="grid gap-5 lg:grid-cols-[1fr_360px] items-start">
        <div>
          <Card id="catalog" className="rounded-xl p-6">
            <div className="mb-3 flex items-baseline justify-between">
              <h2 className="m-0 text-h2 font-extrabold">Catalogue</h2>
              <span className="text-sm text-muted">Produits populaires</span>
            </div>
            <PillRow className="mb-4">
              <Pill active>Tous</Pill>
              <Pill>Lait &amp; frais</Pill>
              <Pill>Boissons</Pill>
              <Pill>Épicerie</Pill>
              <Pill>Hygiène</Pill>
            </PillRow>
            <div className="grid gap-3" style={{
              gridTemplateColumns: "repeat(auto-fill, minmax(180px, 1fr))",
            }}>
              {products.map((p) => (
                <ProductCard key={p.id} product={p} />
              ))}
            </div>
          </Card>

          {/* Slots */}
          <Card id="slot" className="mt-5 rounded-xl p-6">
            <div className="mb-3 flex items-baseline justify-between">
              <h2 className="m-0 text-h2 font-extrabold">Choisir un créneau</h2>
              <span className="text-sm text-muted">Aujourd&apos;hui</span>
            </div>
            <div className="grid gap-3 md:grid-cols-4">
              {slots.map((s, i) => (
                <SlotTile
                  key={s.id}
                  time={formatTime(s.startsAt)}
                  label={s.label}
                  active={i === 1}
                  disabled={!s.available}
                />
              ))}
            </div>
          </Card>
        </div>

        {/* Cart */}
        <Card id="cart" className="sticky top-7 rounded-xl p-6">
          <div className="mb-3 flex items-baseline justify-between">
            <h2 className="m-0 text-h2 font-extrabold">Ma Kadhia</h2>
            <Badge>{MOCK_ORDER.lines.length} lignes</Badge>
          </div>
          <div className="grid gap-2.5">
            {MOCK_ORDER.lines.map((l) => (
              <KadhiaLineRow key={l.id} line={l} />
            ))}
          </div>
          <div className="mt-4">
            <Summary>
              <SummaryRow
                label="Sous-total"
                value={formatTnd(MOCK_ORDER.totalAmountTnd)}
              />
              <SummaryRow label="Service" value={formatTnd("0.000")} />
              <SummaryRow
                total
                label="Total estimé"
                value={formatTnd(MOCK_ORDER.totalAmountTnd)}
              />
            </Summary>
          </div>
          <Button full className="mt-4">
            Soumettre la commande
          </Button>
          <p className="mt-3 text-xs text-muted">
            Les prix sont figés au moment de la soumission.
          </p>
        </Card>
      </section>

      {/* Tracking + QR */}
      {order && (
        <section className="mt-5 grid gap-5 md:grid-cols-2">
          <Card className="rounded-xl p-6">
            <div className="mb-4 flex items-baseline justify-between">
              <h2 className="m-0 text-h2 font-extrabold">
                Suivi commande {order.code}
              </h2>
              <Badge tone="ready">Commande prête</Badge>
            </div>
            <Timeline steps={steps} />
          </Card>

          <Card className="rounded-xl p-6">
            <div className="mb-4 flex items-baseline justify-between">
              <h2 className="m-0 text-h2 font-extrabold">QR code retrait</h2>
              <Badge tone="ready">Commande prête</Badge>
            </div>
            <QrPlaceholder code={order.code} />
            <div className="mt-4 text-center">
              <span className="inline-flex rounded-md bg-[#f4f4f0] px-4 py-2.5 font-black tracking-[2px]">
                {order.code}
              </span>
              <p className="mt-2 text-sm text-muted">
                À présenter au comptoir pour valider la récupération.
              </p>
            </div>
          </Card>
        </section>
      )}
    </DesktopShell>
  );
}

function KPI({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-md bg-[#f9faf6] p-3">
      <strong className="block text-base">{value}</strong>
      <span className="text-xs text-muted">{label}</span>
    </div>
  );
}
