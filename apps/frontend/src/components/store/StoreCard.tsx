import Link from "next/link";
import type { Shop } from "@/types";
import { Card } from "@/components/ui/Card";

export interface StoreCardProps {
  shop: Shop;
  href?: string;
  selected?: boolean;
}

/**
 * Store summary tile used on the home and stores list. Matches
 * `.store-card` from user-client-flow.css.
 */
export function StoreCard({ shop, href, selected }: StoreCardProps) {
  const inner = (
    <>
      <div className="grid h-[54px] w-[54px] flex-shrink-0 place-items-center rounded-md bg-product-tile text-2xl font-black text-primary-dark">
        {shop.logoLetter ?? shop.name.charAt(0)}
      </div>
      <div className="flex-1 min-w-0">
        <strong className="block text-sm truncate">{shop.name}</strong>
        <span className="mt-0.5 block text-xs text-muted truncate">
          {shop.isActive ? "Ouverte" : "Fermée"}
          {shop.distanceKm != null && ` · ${shop.distanceKm.toFixed(1).replace(".", ",")} km`}
          {shop.nextPickupAt && ` · Retrait dès ${shop.nextPickupAt}`}
        </span>
      </div>
      {shop.rating != null && (
        <span className="font-black text-primary-dark whitespace-nowrap">
          {shop.rating.toFixed(1)}
        </span>
      )}
      {selected && (
        <span className="ml-1 rounded-full bg-primary px-2 py-0.5 text-[10px] font-extrabold text-white">
          ✓
        </span>
      )}
    </>
  );
  if (href) {
    return (
      <Link href={href}>
        <Card compact className="flex items-center gap-3 hover:bg-soft transition-colors">
          {inner}
        </Card>
      </Link>
    );
  }
  return (
    <Card compact className="flex items-center gap-3 hover:bg-soft transition-colors cursor-pointer">
      {inner}
    </Card>
  );
}
