import Link from "next/link";
import { TopBar } from "@/components/layout/TopBar";
import { StoreSearchCombobox } from "@/components/store/StoreSearchCombobox";
import { StoreCard } from "@/components/store/StoreCard";
import { listShops } from "@/lib/services";
import type { Shop } from "@/types";

export const dynamic = "force-dynamic";

export default async function StoresPage() {
  let shops: Shop[] = [];
  try {
    shops = await listShops();
  } catch {
    // backend unavailable — render empty state
  }
  return (
    <>
      <TopBar
        title="Trouver une supérette"
        subtitle="Scan QR ou recherche par nom"
        backHref="/"
      />
      <StoreSearchCombobox />
      <div className="grid gap-2.5 md:grid-cols-3">
        {shops.length === 0 ? (
          <p className="col-span-3 py-6 text-center text-sm text-muted">
            Aucune supérette disponible pour le moment.
          </p>
        ) : (
          shops.map((s) => (
            <StoreCard key={s.id} shop={s} href={`/stores/${s.id}`} />
          ))
        )}
      </div>
      <p className="mt-4 text-center text-xs text-muted">
        Tu peux aussi{" "}
        <Link href="/" className="font-extrabold text-primary">
          scanner directement
        </Link>{" "}
        le QR à l&apos;entrée.
      </p>
    </>
  );
}
