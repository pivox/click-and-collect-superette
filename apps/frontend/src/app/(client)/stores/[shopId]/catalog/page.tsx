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

  useEffect(() => {
    setCatalogError(null);
    void listCatalog({ shopId, category, search })
      .then(setProducts)
      .catch(() => setCatalogError("Impossible de charger le catalogue. Veuillez réessayer."));
  }, [shopId, category, search]);

  useEffect(() => {
    void getCurrentKadhia(shopId).then(setKadhia);
  }, [shopId]);

  useEffect(() => {
    void getShop(shopId).then(setShop);
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
