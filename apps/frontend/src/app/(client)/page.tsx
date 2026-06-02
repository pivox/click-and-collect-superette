import Link from 'next/link';
import { Hero } from '@/components/layout/Hero';
import { getButtonClassName } from '@/components/ui/Button';
import { StoreCard } from '@/components/store/StoreCard';
import { ActiveKadhiaBanner } from '@/components/store/ActiveKadhiaBanner';
import { listShops } from '@/lib/services';
import type { Shop } from '@/types';

export const dynamic = 'force-dynamic';

export default async function HomePage() {
  let shops: Shop[] = [];
  try {
    shops = await listShops();
  } catch {
    // backend unavailable in this env — show empty state
  }
  const recent = shops.slice(0, 3);

  return (
    <>
      <ActiveKadhiaBanner />

      <Hero
        badge="Supérettes de quartier"
        title="Ta Kadhia prête sans attendre"
        subtitle="Scanne le QR code d'une supérette ou trouve un magasin proche."
        actions={
          <>
            <Link
              href="/stores/by-qr-scan"
              className={getButtonClassName({ variant: 'secondary' })}
            >
              Scanner un QR code
            </Link>
            <Link href="/stores" className={getButtonClassName({ variant: 'ghost' })}>
              Chercher une supérette
            </Link>
          </>
        }
      />

      {/* Supérettes récentes */}
      <section className="mt-5">
        <header className="mb-2.5 flex items-baseline justify-between">
          <h2 className="text-h3 font-extrabold m-0">Supérettes récentes</h2>
          <Link href="/stores" className="text-xs font-extrabold text-primary">
            Voir tout
          </Link>
        </header>
        <div className="grid gap-2.5 md:grid-cols-3">
          {recent.length === 0 ? (
            <p className="col-span-3 py-4 text-center text-sm text-muted">
              Aucune supérette disponible. Scanne un QR code à l&apos;entrée.
            </p>
          ) : (
            recent.map((s) => (
              <StoreCard key={s.id} shop={s} href={`/stores/${s.id}/catalog`} />
            ))
          )}
        </div>
      </section>
    </>
  );
}
