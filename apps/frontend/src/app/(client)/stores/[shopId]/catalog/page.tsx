"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { TopBar } from "@/components/layout/TopBar";
import { Pill, PillRow } from "@/components/ui/Pill";
import { SearchInput } from "@/components/ui/SearchInput";
import { ProductCard } from "@/components/product/ProductCard";
import { KadhiaPanel } from "@/components/product/KadhiaPanel";
import { ShoppingBasket } from "lucide-react";
import {
  addLine,
  getCurrentKadhia,
  getShop,
  listCatalog,
} from "@/lib/services";
import type { Kadhia, ProductOffer, Shop } from "@/types";
import { PRODUCT_CATEGORIES } from "@/lib/mock/products.mock";

export default function CatalogPage({
  params,
}: {
  params: { shopId: string };
}) {
  const { shopId } = params;
  const [category, setCategory] = useState<"all" | ProductOffer["category"]>("all");
  const [search, setSearch] = useState("");
  const [products, setProducts] = useState<ProductOffer[]>([]);
  const [kadhia, setKadhia] = useState<Kadhia | null>(null);
  const [shop, setShop] = useState<Shop | null>(null);
  const [catalogError, setCatalogError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    setCatalogError(null);
    setIsLoading(true);
    void listCatalog({ shopId, category, search })
      .then((data) => { if (!cancelled) { setProducts(data); setIsLoading(false); } })
      .catch(() => { if (!cancelled) { setCatalogError("Impossible de charger le catalogue. Veuillez réessayer."); setIsLoading(false); } });
    return () => { cancelled = true; };
  }, [shopId, category, search]);

  useEffect(() => {
    void getCurrentKadhia(shopId)
      .then(setKadhia)
      .catch(() => { /* kadhia indisponible, panier affiché vide */ });
  }, [shopId]);

  useEffect(() => {
    void getShop(shopId)
      .then(setShop)
      .catch(() => { /* nom de la supérette indisponible */ });
  }, [shopId]);

  const onAdd = async (p: ProductOffer) => {
    const next = await addLine(shopId, p, 1);
    setKadhia(next);
  };

  const cartCount = useMemo(
    () => kadhia?.lines.reduce((acc, l) => acc + l.quantity, 0) ?? 0,
    [kadhia],
  );

  const cartLabel = cartCount === 0
    ? "Kadhia vide"
    : `${cartCount} article${cartCount > 1 ? "s" : ""}`;

  return (
    <>
      <TopBar
        title="Catalogue"
        subtitle={shop?.name}
        backHref={`/stores/${shopId}`}
        action={
          <Link
            href="/kadhia"
            aria-label="Voir ma Kadhia"
            className="relative grid h-10 w-10 place-items-center rounded-[15px] border border-line bg-card shadow-[0_8px_18px_rgba(18,30,20,.06)] md:hidden"
          >
            <ShoppingBasket size={18} />
            {cartCount > 0 && (
              <span className="absolute -right-1 -top-1 grid h-5 min-w-[20px] place-items-center rounded-full bg-primary px-1 text-[10px] font-black text-white">
                {cartCount}
              </span>
            )}
          </Link>
        }
      />

      <SearchInput
        placeholder="Rechercher un produit"
        value={search}
        onChange={(e) => setSearch(e.currentTarget.value)}
        className="mb-3"
      />

      <PillRow className="mb-4">
        {PRODUCT_CATEGORIES.map((c) => (
          <Pill
            key={c.key}
            active={category === c.key}
            onClick={() => setCategory(c.key)}
          >
            {c.labelFr}
          </Pill>
        ))}
      </PillRow>

      {/* Desktop : catalogue + KadhiaPanel sticky */}
      <div className="md:grid md:grid-cols-[1fr_360px] md:gap-5 md:items-start">
        <section>
          <header className="mb-2.5 flex items-baseline justify-between">
            <h3 className="m-0 text-h3 font-extrabold">Produits</h3>
            <Link href="/kadhia" className="text-xs font-extrabold text-primary md:hidden">
              {cartLabel}
            </Link>
          </header>
          {catalogError ? (
            <p className="py-8 text-center text-sm text-muted">{catalogError}</p>
          ) : isLoading ? (
            <div className="grid grid-cols-2 gap-2.5 md:grid-cols-3">
              {Array.from({ length: 6 }).map((_, i) => (
                <div
                  key={i}
                  className="animate-pulse rounded-lg border border-line bg-card p-3 shadow-card"
                >
                  <div className="mb-2 h-[94px] rounded-md bg-gray-200" />
                  <div className="mb-1 h-4 w-3/4 rounded bg-gray-200" />
                  <div className="mb-2 h-3 w-1/2 rounded bg-gray-200" />
                  <div className="h-4 w-1/3 rounded bg-gray-200" />
                </div>
              ))}
            </div>
          ) : (
            <div className="grid grid-cols-2 gap-2.5 md:grid-cols-3">
              {products.map((p) => (
                <ProductCard key={p.id} product={p} onAdd={onAdd} />
              ))}
            </div>
          )}
        </section>

        <div className="hidden md:block">
          <KadhiaPanel kadhia={kadhia} />
        </div>
      </div>
    </>
  );
}
