import Link from "next/link";
import { TopBar } from "@/components/layout/TopBar";
import { SearchInput } from "@/components/ui/SearchInput";
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
      <SearchInput placeholder="Nom de la supérette, quartier…" className="mb-4 md:max-w-lg" />
      <div className="grid gap-2.5 md:grid-cols-3">
        {shops.map((s) => (
          <StoreCard key={s.id} shop={s} href={`/stores/${s.id}`} />
        ))}
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
