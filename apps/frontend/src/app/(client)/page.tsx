import Link from "next/link";
import { Hero } from "@/components/layout/Hero";
import { Button } from "@/components/ui/Button";
import { StoreCard } from "@/components/store/StoreCard";
import { listShops } from "@/lib/services";

export default async function HomePage() {
  const shops = await listShops();
  const featured = shops.slice(0, 3);

  return (
    <>
      <Hero
        badge="Supérettes de quartier"
        title="Ta Kadhia prête sans attendre"
        subtitle="Scanne le QR code d'une supérette ou trouve un magasin proche."
        actions={
          <>
            <Link href="/stores">
              <Button variant="secondary" full>
                Scanner un QR code
              </Button>
            </Link>
            <Link href="/stores">
              <Button variant="ghost" full>
                Chercher une supérette
              </Button>
            </Link>
          </>
        }
      />

      <section className="mt-5">
        <header className="mb-2.5 flex items-baseline justify-between">
          <h2 className="text-h3 font-extrabold m-0">Stores récents</h2>
          <Link
            href="/stores"
            className="text-xs font-extrabold text-primary"
          >
            Voir tout
          </Link>
        </header>
        <div className="grid gap-2.5">
          {featured.map((s) => (
            <StoreCard key={s.id} shop={s} href={`/stores/${s.id}`} />
          ))}
        </div>
      </section>
    </>
  );
}
