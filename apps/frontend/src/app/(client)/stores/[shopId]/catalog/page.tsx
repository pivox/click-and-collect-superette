"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { TopBar } from "@/components/layout/TopBar";
import { Pill, PillRow } from "@/components/ui/Pill";
import { SearchInput } from "@/components/ui/SearchInput";
import { ProductCard } from "@/components/product/ProductCard";
import { KadhiaPanel } from "@/components/product/KadhiaPanel";
import { Button } from "@/components/ui/Button";
import { KadhiaSelectorDialog } from "@/components/client/KadhiaSelectorDialog";
import { ShoppingBasket } from "lucide-react";
import {
  addLine,
  createKadhia,
  activateKadhia,
  getCurrentKadhia,
  getShop,
  listCatalog,
} from "@/lib/services";
import type { KadhiaListItem } from "@/lib/services/kadhia.service";
import type { Kadhia, ProductOffer, Shop } from "@/types";
import { formatTnd } from "@/lib/format";

interface CategoryOption {
  key: string;
  labelFr: string;
}

function buildCategoryOptions(products: ProductOffer[]): CategoryOption[] {
  const bySlug = new Map<string, string>();

  products.forEach((product) => {
    if (!product.category || bySlug.has(product.category)) return;
    bySlug.set(product.category, product.categoryNameFr ?? product.category);
  });

  return Array.from(bySlug.entries())
    .map(([key, labelFr]) => ({ key, labelFr }))
    .sort((a, b) => a.labelFr.localeCompare(b.labelFr, "fr"));
}

export default function CatalogPage({
  params,
}: {
  params: { shopId: string };
}) {
  const { shopId } = params;
  const [category, setCategory] = useState<string>("all");
  const [categoryOptions, setCategoryOptions] = useState<CategoryOption[]>([]);
  const [search, setSearch] = useState("");
  const [products, setProducts] = useState<ProductOffer[]>([]);
  const [kadhia, setKadhia] = useState<Kadhia | null>(null);
  const [shop, setShop] = useState<Shop | null>(null);
  const [catalogError, setCatalogError] = useState<string | null>(null);
  const [addError, setAddError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isStarting, setIsStarting] = useState(false);
  const [selectorDrafts, setSelectorDrafts] = useState<KadhiaListItem[] | null>(null);
  const [retryKey, setRetryKey] = useState(0);
  const [kadhiaLoadError, setKadhiaLoadError] = useState<string | null>(null);

  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => {
    let cancelled = false;
    setCatalogError(null);
    setIsLoading(true);
    void listCatalog({ shopId, category, search })
      .then((data) => { if (!cancelled) { setProducts(data); setIsLoading(false); } })
      .catch(() => { if (!cancelled) { setCatalogError("Impossible de charger le catalogue."); setIsLoading(false); } });
    return () => { cancelled = true; };
  }, [shopId, category, search, retryKey]);

  useEffect(() => {
    let cancelled = false;
    void listCatalog({ shopId, category: "all", search: "" })
      .then((data) => {
        if (!cancelled) setCategoryOptions(buildCategoryOptions(data));
      })
      .catch(() => {
        if (!cancelled) setCategoryOptions([]);
      });
    return () => { cancelled = true; };
  }, [shopId, retryKey]);

  useEffect(() => {
    setKadhiaLoadError(null);
    void getCurrentKadhia(shopId)
      .then((result) => {
        if (result.type === "active") setKadhia(result.kadhia);
        else if (result.type === "multiple") setSelectorDrafts(result.drafts);
        // "none" → kadhia stays null → "Commencer" bar is shown
      })
      .catch((err: unknown) => {
        const status = (err as { response?: { status?: number } }).response?.status;
        // 401 = visitor not logged in. Catalog is public, so stay on the page
        // and let the "Commencer une Kadhia" bar prompt for login on demand.
        if (status === 401) return;
        if (status !== 404 && status !== 405) {
          setKadhiaLoadError("Impossible de charger ta Kadhia. Réessaie.");
        }
      });
  }, [shopId]);

  useEffect(() => {
    void getShop(shopId)
      .then(setShop)
      .catch(() => {});
  }, [shopId]);

  const onStart = async () => {
    setIsStarting(true);
    setAddError(null);
    try {
      const created = await createKadhia(shopId);
      setKadhia(created);
    } catch {
      setAddError("Impossible de créer une Kadhia. Réessaie.");
    } finally {
      setIsStarting(false);
    }
  };

  const onSelectDraft = async (kadhiaId: string) => {
    try {
      const activated = await activateKadhia(shopId, kadhiaId);
      setSelectorDrafts(null);
      setKadhia(activated);
    } catch {
      setAddError("Impossible de charger cette Kadhia. Réessaie.");
    }
  };

  const onCreateNewFromSelector = async () => {
    setSelectorDrafts(null);
    await onStart();
  };

  const onAdd = async (p: ProductOffer) => {
    if (!kadhia?.id) return;
    setAddError(null);
    try {
      const existingLine = kadhia.lines.find((l) => l.productOffer.id === p.id);
      const newQty = (existingLine?.quantity ?? 0) + 1;
      const next = await addLine(shopId, kadhia.id, p, newQty);
      setKadhia(next);
    } catch {
      setAddError("Impossible d'ajouter le produit. Réessaie.");
    }
  };

  const cartCount = useMemo(
    () => kadhia?.lines.reduce((acc, l) => acc + l.quantity, 0) ?? 0,
    [kadhia],
  );

  const cartLabel = cartCount === 0
    ? "Kadhia vide"
    : `${cartCount} article${cartCount > 1 ? "s" : ""}`;

  const hasActiveKadhia = !!kadhia?.id;

  return (
    <>
      {selectorDrafts && (
        <KadhiaSelectorDialog
          drafts={selectorDrafts}
          onSelect={onSelectDraft}
          onCreateNew={onCreateNewFromSelector}
        />
      )}

      <TopBar
        title="Catalogue"
        subtitle={shop?.name}
        backHref={`/stores/${shopId}`}
        action={
          <Link
            href={kadhia?.id ? `/kadhia/${kadhia.id}` : "/kadhia"}
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
        {[{ key: "all", labelFr: "Tous" }, ...categoryOptions].map((c) => (
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
            {hasActiveKadhia && (
              <Link href={`/kadhia/${kadhia!.id}`} className="text-xs font-extrabold text-primary md:hidden">
                {cartLabel}
              </Link>
            )}
          </header>
          {addError && (
            <p className="mb-2 rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">
              {addError}
            </p>
          )}
          {catalogError ? (
            <div className="py-8 text-center">
              <p className="text-sm text-muted">{catalogError}</p>
              <button
                type="button"
                onClick={() => setRetryKey((k) => k + 1)}
                className="mt-3 text-sm font-extrabold text-primary underline"
              >
                Réessayer
              </button>
            </div>
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
          ) : products.length === 0 ? (
            <div className="py-8 text-center">
              {search ? (
                <>
                  <p className="text-sm text-muted">Aucun résultat pour « {search} ».</p>
                  <button
                    type="button"
                    onClick={() => setSearch("")}
                    className="mt-3 text-sm font-extrabold text-primary underline"
                  >
                    Effacer la recherche
                  </button>
                </>
              ) : category !== "all" ? (
                <>
                  <p className="text-sm text-muted">Aucun produit dans cette catégorie.</p>
                  <button
                    type="button"
                    onClick={() => setCategory("all")}
                    className="mt-3 text-sm font-extrabold text-primary underline"
                  >
                    Voir tous les produits
                  </button>
                </>
              ) : (
                <p className="text-sm text-muted">Catalogue vide pour le moment.</p>
              )}
            </div>
          ) : (
            <div className="grid grid-cols-2 gap-2.5 md:grid-cols-3">
              {products.map((p) => (
                <ProductCard
                  key={p.id}
                  product={p}
                  onAdd={hasActiveKadhia && p.isAvailable ? onAdd : undefined}
                />
              ))}
            </div>
          )}
        </section>

        <div className="hidden md:block">
          <KadhiaPanel kadhia={kadhia} />
        </div>
      </div>

      {/* "Commencer une Kadhia" bar — shown only when no active kadhia */}
      {!hasActiveKadhia && !selectorDrafts && (
        <div className="fixed bottom-[calc(60px+env(safe-area-inset-bottom))] left-0 right-0 z-30 border-t border-line bg-white px-4 pb-3 pt-3 shadow-[0_-4px_16px_rgba(18,30,20,.08)] md:bottom-0">
          {kadhiaLoadError ? (
            <div className="mx-auto flex max-w-md items-center justify-between gap-3">
              <p className="text-sm text-red-600">{kadhiaLoadError}</p>
              <Button
                onClick={() => {
                  setKadhiaLoadError(null);
                  void getCurrentKadhia(shopId)
                    .then((result) => {
                      if (result.type === "active") setKadhia(result.kadhia);
                      else if (result.type === "multiple") setSelectorDrafts(result.drafts);
                    })
                    .catch(() => setKadhiaLoadError("Impossible de charger ta Kadhia. Réessaie."));
                }}
                className="shrink-0"
              >
                Réessayer
              </Button>
            </div>
          ) : (
            <div className="mx-auto flex max-w-md items-center justify-between gap-3">
              <p className="text-sm text-muted">Commence une Kadhia pour ajouter des produits.</p>
              <Button onClick={onStart} disabled={isStarting} className="shrink-0">
                {isStarting ? "…" : "Commencer"}
              </Button>
            </div>
          )}
        </div>
      )}

      {/* Active Kadhia summary bar — shown on mobile when kadhia has items */}
      {hasActiveKadhia && cartCount > 0 && (
        <div className="fixed bottom-[calc(60px+env(safe-area-inset-bottom))] left-0 right-0 z-30 border-t border-line bg-white px-4 pb-3 pt-3 shadow-[0_-4px_16px_rgba(18,30,20,.08)] md:hidden">
          <Link href={`/kadhia/${kadhia!.id}`} className="mx-auto flex max-w-md items-center justify-between">
            <span className="text-sm font-bold">{cartLabel}</span>
            <span className="text-sm font-extrabold text-primary">{formatTnd(kadhia!.totalTnd)}</span>
          </Link>
        </div>
      )}
    </>
  );
}
