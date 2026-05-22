import Link from "next/link";
import { TopBar } from "@/components/layout/TopBar";
import { SearchInput } from "@/components/ui/SearchInput";
import { StoreCard } from "@/components/store/StoreCard";
import { listShops } from "@/lib/services";

export default async function StoresPage() {
  const shops = await listShops();
  return (
    <>
      <TopBar
        title="Trouver une supérette"
        subtitle="Scan QR ou recherche par nom"
        backHref="/"
      />
      <SearchInput placeholder="Nom de la supérette, quartier…" className="mb-4" />
      <div className="grid gap-2.5">
        {shops.map((s) => (
          <StoreCard key={s.id} shop={s} href={`/stores/${s.id}`} />
        ))}
      </div>
      <p className="mt-4 text-center text-xs text-muted">
        Tu peux aussi <Link href="/" className="font-extrabold text-primary">scanner directement</Link> le QR à l&apos;entrée.
      </p>
    </>
  );
}
