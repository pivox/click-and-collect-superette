import Link from "next/link";
import { Hero } from "@/components/layout/Hero";
import { getButtonClassName } from "@/components/ui/Button";
import { StoreCard } from "@/components/store/StoreCard";
import { Card } from "@/components/ui/Card";
import { ActiveKadhiaBanner } from "@/components/store/ActiveKadhiaBanner";
import { listShops } from "@/lib/services";
import type { Shop } from "@/types";

export const dynamic = "force-dynamic";

export default async function HomePage() {
  let shops: Shop[] = [];
  try {
    shops = await listShops();
  } catch {
    // backend unavailable in this env — show empty state
  }
  const featured = shops.slice(0, 3);
  const featuredShop = shops[0];

  return (
    <>
      <ActiveKadhiaBanner />

      {/* Desktop hero : pitch gauche + store featured droite */}
      <div className="hidden md:grid md:grid-cols-[1.3fr_0.7fr] md:gap-5 md:mb-6">
        <Hero
          badge="Supérettes de quartier"
          title="Ta Kadhia prête sans attendre"
          subtitle="Scanne le QR code d'une supérette ou trouve un magasin proche."
          actions={
            <>
              <Link
                href="/stores/by-qr-scan"
                className={getButtonClassName({ variant: "secondary" })}
              >
                Scanner un QR code
              </Link>
              <Link href="/stores" className={getButtonClassName({ variant: "ghost" })}>
                Chercher une supérette
              </Link>
            </>
          }
        />
        {featuredShop && (
          <Card className="rounded-xl flex flex-col">
            <span className="text-xs font-extrabold uppercase tracking-widest text-primary">
              Supérette en vedette
            </span>
            <h2 className="mt-2 text-h2 font-extrabold text-ink">{featuredShop.name}</h2>
            <p className="text-sm text-muted">
              {[featuredShop.address, featuredShop.city].filter(Boolean).join(", ")}
            </p>
            <div className="mt-4 grid grid-cols-3 gap-3">
              {featuredShop.distanceKm != null && (
                <KPI label="distance" value={`${featuredShop.distanceKm} km`} />
              )}
              {featuredShop.nextPickupAt && (
                <KPI label="prochain retrait" value={featuredShop.nextPickupAt} />
              )}
              {featuredShop.rating != null && (
                <KPI label="note" value={featuredShop.rating.toFixed(1)} />
              )}
            </div>
            <Link
              href={`/stores/${featuredShop.id}/catalog`}
              className={getButtonClassName({ full: true, className: "mt-4" })}
            >
              Voir le catalogue
            </Link>
          </Card>
        )}
      </div>

      {/* Mobile hero */}
      <div className="md:hidden">
        <Hero
          badge="Supérettes de quartier"
          title="Ta Kadhia prête sans attendre"
          subtitle="Scanne le QR code d'une supérette ou trouve un magasin proche."
          actions={
            <>
              <Link
                href="/stores/by-qr-scan"
                className={getButtonClassName({ variant: "secondary", full: true })}
              >
                Scanner un QR code
              </Link>
              <Link
                href="/stores"
                className={getButtonClassName({ variant: "ghost", full: true })}
              >
                Chercher une supérette
              </Link>
            </>
          }
        />
      </div>

      {/* Supérettes récentes */}
      <section className="mt-5">
        <header className="mb-2.5 flex items-baseline justify-between">
          <h2 className="text-h3 font-extrabold m-0">Supérettes récentes</h2>
          <Link href="/stores" className="text-xs font-extrabold text-primary">
            Voir tout
          </Link>
        </header>
        <div className="grid gap-2.5 md:grid-cols-3">
          {featured.length === 0 ? (
            <p className="col-span-3 py-4 text-center text-sm text-muted">
              Aucune supérette disponible. Scanne un QR code à l&apos;entrée.
            </p>
          ) : (
            featured.map((s) => (
              <StoreCard key={s.id} shop={s} href={`/stores/${s.id}/catalog`} />
            ))
          )}
        </div>
      </section>
    </>
  );
}

function KPI({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-md bg-soft p-3">
      <strong className="block text-base">{value}</strong>
      <span className="text-xs text-muted">{label}</span>
    </div>
  );
}
