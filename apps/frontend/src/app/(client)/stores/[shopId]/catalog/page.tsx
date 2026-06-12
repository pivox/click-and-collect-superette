"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { TopBar } from "@/components/layout/TopBar";
import { Pill, PillRow } from "@/components/ui/Pill";
import { SearchInput } from "@/components/ui/SearchInput";
import { ProductCard } from "@/components/product/ProductCard";
import { ProductSuggestionsSection } from "@/components/product/ProductSuggestionsSection";
import { KadhiaPanel } from "@/components/product/KadhiaPanel";
import { Button } from "@/components/ui/Button";
import { KadhiaSelectorDialog } from "@/components/client/KadhiaSelectorDialog";
import { ShoppingBasket } from "lucide-react";
import { useHydrated } from "@/lib/hooks/useHydrated";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";
import { useClientLocale } from "@/lib/i18n/ClientLocaleContext";
import {
  addLine,
  createKadhia,
  activateKadhia,
  getCurrentKadhia,
  getShop,
  getStoreSuggestions,
  listCatalog,
  listFavoriteProducts,
  toggleProductFavorite,
} from "@/lib/services";
import type { StoreSuggestions } from "@/lib/services/suggestions.service";
import type { CatalogCategoryOption } from "@/lib/services/catalog.service";
import type { KadhiaListItem } from "@/lib/services/kadhia.service";
import type { Kadhia, ProductOffer, Shop } from "@/types";
import { formatTnd } from "@/lib/format";

const CATALOG_PAGE_SIZE = 30;

export default function CatalogPage({
  params,
}: {
  params: { shopId: string };
}) {
  const { shopId } = params;
  const isHydrated = useHydrated();
  const { user } = useClientAuth();
  const { t } = useClientLocale();
  const itemsWord = (n: number) =>
    n > 1 ? t("client.itemsPlural") : t("client.itemsSingular");
  const [category, setCategory] = useState<string>("all");
  const [categoryOptions, setCategoryOptions] = useState<CatalogCategoryOption[]>([]);
  const [search, setSearch] = useState("");
  const [products, setProducts] = useState<ProductOffer[]>([]);
  const [kadhia, setKadhia] = useState<Kadhia | null>(null);
  const [shop, setShop] = useState<Shop | null>(null);
  const [catalogError, setCatalogError] = useState(false);
  const [addError, setAddError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isStarting, setIsStarting] = useState(false);
  const [selectorDrafts, setSelectorDrafts] = useState<KadhiaListItem[] | null>(null);
  const [retryKey, setRetryKey] = useState(0);
  const [kadhiaLoadError, setKadhiaLoadError] = useState(false);
  const [catalogPage, setCatalogPage] = useState(1);
  const [catalogPages, setCatalogPages] = useState(1);
  const [catalogTotal, setCatalogTotal] = useState(0);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [suggestions, setSuggestions] = useState<StoreSuggestions | null>(null);
  const [favorites, setFavorites] = useState<ProductOffer[]>([]);
  const [favoriteIds, setFavoriteIds] = useState<Set<string>>(new Set());

  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => {
    let cancelled = false;
    setCatalogError(false);
    setIsLoading(true);
    void listCatalog({
      shopId,
      category,
      search,
      page: 1,
      itemsPerPage: CATALOG_PAGE_SIZE,
    })
      .then((data) => {
        if (!cancelled) {
          setProducts(data.items);
          setCatalogPage(data.page);
          setCatalogPages(data.pages);
          setCatalogTotal(data.total);
          if (category === "all") {
            setCategoryOptions(data.categories);
          }
          setIsLoading(false);
        }
      })
      .catch(() => { if (!cancelled) { setCatalogError(true); setIsLoading(false); } });
    return () => { cancelled = true; };
  }, [shopId, category, search, retryKey]);

  useEffect(() => {
    setKadhiaLoadError(false);
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
          setKadhiaLoadError(true);
        }
      });
  }, [shopId]);

  useEffect(() => {
    void getShop(shopId)
      .then(setShop)
      .catch(() => {});
  }, [shopId]);

  // Favorites of the authenticated client for this store (silent on failure).
  useEffect(() => {
    if (!user) return;
    let cancelled = false;
    void listFavoriteProducts(shopId)
      .then((items) => {
        if (cancelled) return;
        setFavorites(items);
        setFavoriteIds(new Set(items.map((p) => p.id)));
      })
      .catch(() => {});
    return () => { cancelled = true; };
  }, [shopId, user]);

  // Suggestions seeded by the active Kadhia (silent on failure). Depends on
  // the kadhia object so adding/removing lines refetches the co-occurrence
  // seed — every mutation goes through setKadhia with a fresh reference.
  useEffect(() => {
    if (!user) return;
    let cancelled = false;
    void getStoreSuggestions(shopId, kadhia?.id ?? undefined)
      .then((data) => { if (!cancelled) setSuggestions(data); })
      .catch(() => {});
    return () => { cancelled = true; };
  }, [shopId, user, kadhia]);

  const onToggleFavorite = async (product: ProductOffer, next: boolean) => {
    // Optimistic update with rollback on failure.
    const previousIds = favoriteIds;
    const previousFavorites = favorites;
    setFavoriteIds((current) => {
      const updated = new Set(current);
      if (next) updated.add(product.id);
      else updated.delete(product.id);
      return updated;
    });
    setFavorites((current) =>
      next
        ? current.some((p) => p.id === product.id) ? current : [product, ...current]
        : current.filter((p) => p.id !== product.id),
    );
    try {
      await toggleProductFavorite(product.id, next);
    } catch {
      setFavoriteIds(previousIds);
      setFavorites(previousFavorites);
      setAddError(t("client.favorites.error"));
    }
  };

  const onStart = async () => {
    setIsStarting(true);
    setAddError(null);
    try {
      const created = await createKadhia(shopId);
      setKadhia(created);
    } catch {
      setAddError(t("client.catalog.createError"));
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
      setAddError(t("client.catalog.loadKadhiaError"));
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
      setAddError(t("client.catalog.addError"));
    }
  };

  const onLoadMore = async () => {
    if (isLoadingMore || catalogPage >= catalogPages) return;

    setIsLoadingMore(true);
    setAddError(null);
    try {
      const nextPage = catalogPage + 1;
      const data = await listCatalog({
        shopId,
        category,
        search,
        page: nextPage,
        itemsPerPage: CATALOG_PAGE_SIZE,
      });
      setProducts((current) => [...current, ...data.items]);
      setCatalogPage(data.page);
      setCatalogPages(data.pages);
      setCatalogTotal(data.total);
    } catch {
      setAddError(t("client.catalog.loadMoreError"));
    } finally {
      setIsLoadingMore(false);
    }
  };

  const cartCount = useMemo(
    () => kadhia?.lines.reduce((acc, l) => acc + l.quantity, 0) ?? 0,
    [kadhia],
  );

  const cartLabel = cartCount === 0
    ? t("client.catalog.emptyKadhia")
    : `${cartCount} ${itemsWord(cartCount)}`;

  const hasActiveKadhia = !!kadhia?.id;
  const startLabel = !isHydrated
    ? t("client.catalog.preparing")
    : isStarting
      ? "…"
      : t("client.catalog.start");
  const remainingProductCount = Math.max(catalogTotal - products.length, 0);

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
        title={t("client.catalog.title")}
        subtitle={shop?.name}
        backHref={`/stores/${shopId}`}
        action={
          <Link
            href={kadhia?.id ? `/kadhia/${kadhia.id}` : "/kadhia"}
            aria-label={t("client.catalog.viewKadhia")}
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
        placeholder={t("client.catalog.searchPlaceholder")}
        value={search}
        onChange={(e) => setSearch(e.currentTarget.value)}
        className="mb-3"
      />

      <PillRow className="mb-4">
        {[{ key: "all", labelFr: t("client.catalog.all") }, ...categoryOptions].map((c) => (
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
      <div className="min-w-0 md:grid md:grid-cols-[minmax(0,1fr)_360px] md:gap-5 md:items-start">
        <section className="min-w-0">
          <header className="mb-2.5 flex items-baseline justify-between">
            <h3 className="m-0 text-h3 font-extrabold">{t("client.catalog.products")}</h3>
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
          {user && category === "all" && !search && (
            <>
              <ProductSuggestionsSection
                title={t("client.favorites.title")}
                products={favorites}
                onAdd={hasActiveKadhia ? onAdd : undefined}
                favoriteIds={favoriteIds}
                onToggleFavorite={onToggleFavorite}
              />
              <ProductSuggestionsSection
                title={t("client.suggestions.frequentlyBoughtTogether")}
                products={suggestions?.frequentlyBoughtTogether ?? []}
                onAdd={hasActiveKadhia ? onAdd : undefined}
                favoriteIds={favoriteIds}
                onToggleFavorite={onToggleFavorite}
              />
              <ProductSuggestionsSection
                title={t("client.suggestions.recentlyOrdered")}
                products={suggestions?.recentlyOrdered ?? []}
                onAdd={hasActiveKadhia ? onAdd : undefined}
                favoriteIds={favoriteIds}
                onToggleFavorite={onToggleFavorite}
              />
            </>
          )}
          {catalogError ? (
            <div className="py-8 text-center">
              <p className="text-sm text-muted">{t("client.catalog.loadError")}</p>
              <button
                type="button"
                onClick={() => setRetryKey((k) => k + 1)}
                className="mt-3 text-sm font-extrabold text-primary underline"
              >
                {t("client.catalog.retry")}
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
                  <p className="text-sm text-muted">
                    {t("client.catalog.noSearchResultPrefix")} « {search} ».
                  </p>
                  <button
                    type="button"
                    onClick={() => setSearch("")}
                    className="mt-3 text-sm font-extrabold text-primary underline"
                  >
                    {t("client.catalog.clearSearch")}
                  </button>
                </>
              ) : category !== "all" ? (
                <>
                  <p className="text-sm text-muted">{t("client.catalog.noCategoryResult")}</p>
                  <button
                    type="button"
                    onClick={() => setCategory("all")}
                    className="mt-3 text-sm font-extrabold text-primary underline"
                  >
                    {t("client.catalog.showAll")}
                  </button>
                </>
              ) : (
                <p className="text-sm text-muted">{t("client.catalog.emptyCatalog")}</p>
              )}
            </div>
          ) : (
            <>
              <div className="grid grid-cols-2 gap-2.5 md:grid-cols-3">
                {products.map((p) => (
                  <ProductCard
                    key={p.id}
                    product={p}
                    onAdd={hasActiveKadhia && p.isAvailable ? onAdd : undefined}
                    isFavorite={favoriteIds.has(p.id)}
                    onToggleFavorite={user ? onToggleFavorite : undefined}
                  />
                ))}
              </div>
              {remainingProductCount > 0 && (
                <div className="mt-4 flex justify-center">
                  <Button
                    type="button"
                    variant="ghost"
                    onClick={onLoadMore}
                    disabled={isLoadingMore}
                  >
                    {isLoadingMore
                      ? t("client.catalog.loadingMore")
                      : `${t("client.catalog.showMorePrefix")} ${Math.min(CATALOG_PAGE_SIZE, remainingProductCount)} ${t("client.catalog.showMoreSuffix")}`}
                  </Button>
                </div>
              )}
            </>
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
              <p className="text-sm text-red-600">{t("client.catalog.kadhiaLoadError")}</p>
              <Button
                onClick={() => {
                  setKadhiaLoadError(false);
                  void getCurrentKadhia(shopId)
                    .then((result) => {
                      if (result.type === "active") setKadhia(result.kadhia);
                      else if (result.type === "multiple") setSelectorDrafts(result.drafts);
                    })
                    .catch(() => setKadhiaLoadError(true));
                }}
                className="shrink-0"
              >
                {t("client.catalog.retry")}
              </Button>
            </div>
          ) : (
            <div className="mx-auto flex max-w-md items-center justify-between gap-3">
              <p className="text-sm text-muted">{t("client.catalog.startMessage")}</p>
              <Button
                onClick={onStart}
                disabled={!isHydrated || isStarting}
                className="shrink-0"
              >
                {startLabel}
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
