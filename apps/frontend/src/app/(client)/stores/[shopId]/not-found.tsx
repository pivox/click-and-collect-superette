import Link from "next/link";
import { TopBar } from "@/components/layout/TopBar";
import { getButtonClassName } from "@/components/ui/Button";

export default function StoreNotFound() {
  return (
    <>
      <TopBar title="Supérette introuvable" backHref="/stores" />
      <div className="flex flex-col items-center justify-center gap-4 py-16 text-center">
        <p className="text-sm text-muted">
          Cette supérette n&apos;existe pas ou n&apos;est plus disponible.
        </p>
        <Link href="/stores" className={getButtonClassName()}>
          Voir toutes les supérettes
        </Link>
      </div>
    </>
  );
}
