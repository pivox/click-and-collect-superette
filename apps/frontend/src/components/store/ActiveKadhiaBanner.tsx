"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { ShoppingBasket, ArrowRight } from "lucide-react";
import { readLocalKadhia } from "@/lib/services";

/**
 * Shown on the home page when the customer has an in-progress kadhia stored
 * in localStorage. Provides a one-tap shortcut back to the catalog.
 */
export function ActiveKadhiaBanner() {
  const [shopId, setShopId] = useState<string | null>(null);
  const [articleCount, setArticleCount] = useState(0);

  useEffect(() => {
    const kadhia = readLocalKadhia();
    if (kadhia?.shopId && kadhia.lines.length > 0) {
      setShopId(kadhia.shopId);
      setArticleCount(kadhia.lines.reduce((acc, l) => acc + l.quantity, 0));
    }
  }, []);

  if (!shopId) return null;

  return (
    <Link
      href={`/stores/${shopId}/catalog`}
      className="mb-4 flex items-center justify-between gap-3 rounded-xl bg-primary px-4 py-3 text-white shadow-[0_8px_18px_rgba(31,122,77,.25)]"
    >
      <span className="flex items-center gap-2 text-sm font-extrabold">
        <ShoppingBasket size={16} />
        Kadhia en cours · {articleCount} article{articleCount > 1 ? "s" : ""}
      </span>
      <span className="flex items-center gap-1 text-xs font-extrabold opacity-90">
        Continuer <ArrowRight size={14} />
      </span>
    </Link>
  );
}
