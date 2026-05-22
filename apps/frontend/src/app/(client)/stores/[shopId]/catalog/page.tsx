"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { TopBar } from "@/components/layout/TopBar";
import { Pill, PillRow } from "@/components/ui/Pill";
import { SearchInput } from "@/components/ui/SearchInput";
import { ProductCard } from "@/components/product/ProductCard";
import { ShoppingBasket } from "lucide-react";
import {
  addLine,
  getCurrentKadhia,
  getShop,
  listCatalog,
} from "@/lib/services";
import type { ProductOffer, Shop } from "@/types";
import { PRODUCT_CATEGORIES } from "@/lib/mock/products.mock";

export default function CatalogPage({
  params,
}: {
  params: { shopId: string };
}) {
  const { shopId } = params;
  const [category, setCategory] = useState<
    "all" | ProductOffer["category"]
  >("all");
  const [search, setSearch] = useState("");
  const [products, setProducts] = useState<ProductOffer[]>([]);
  const [cartCount, setCartCount] = useState(0);
  const [shop, setShop] = useState<Shop | null>(null);

  useEffect(() => {
    void listCatalog({ shopId, category, search }).then(setProducts);
  }, [shopId, category, search]);

  useEffect(() => {
    void getCurrentKadhia(shopId).then((k) =>
      setCartCount(k.lines.reduce((acc, l) => acc + l.quantity, 0)),
    );
  }, [shopId]);

  useEffect(() => {
    void getShop(shopId).then(setShop);
  }, [shopId]);

  const onAdd = async (p: ProductOffer) => {
    const next = await addLine(shopId, p, 1);
    setCartCount(next.lines.reduce((acc, l) => acc + l.quantity, 0));
  };

  const cartLabel = useMemo(
    () => (cartCount === 0 ? "Kadhia vide" : `${cartCount} article${cartCount > 1 ? "s" : ""}`),
    [cartCount],
  );

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
            className="relative grid h-10 w-10 place-items-center rounded-[15px] border border-line bg-card shadow-[0_8px_18px_rgba(18,30,20,.06)]"
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

      <section>
        <header className="mb-2.5 flex items-baseline justify-between">
          <h3 className="m-0 text-h3 font-extrabold">Produits</h3>
          <Link
            href="/kadhia"
            className="text-xs font-extrabold text-primary"
          >
            {cartLabel}
          </Link>
        </header>
        <div className="grid grid-cols-2 gap-2.5">
          {products.map((p) => (
            <ProductCard key={p.id} product={p} onAdd={onAdd} />
          ))}
        </div>
      </section>
    </>
  );
}
