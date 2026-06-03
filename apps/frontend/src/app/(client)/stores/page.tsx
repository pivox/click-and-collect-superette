"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { TopBar } from "@/components/layout/TopBar";
import { StoreSearchCombobox } from "@/components/store/StoreSearchCombobox";
import { StoreSelectList } from "@/components/store/StoreSelectList";
import { listMyStores, listShops, removeStore, toggleFavorite } from "@/lib/services";
import { USE_MOCKS } from "@/lib/services";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";
import type { Shop } from "@/types";

const PAGE_SIZE = 20;

export default function StoresPage() {
  const { user, isLoading: authLoading } = useClientAuth();
  const [shops, setShops] = useState<Shop[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [hasMore, setHasMore] = useState(false);
  const [isPersonalList, setIsPersonalList] = useState(false);
  const pageRef = useRef(1);

  const loadPage = useCallback(async (pageNum: number) => {
    const isFirst = pageNum === 1;
    if (isFirst) setIsLoading(true); else setIsLoadingMore(true);
    try {
      if (USE_MOCKS) {
        const result = await listMyStores(pageNum, PAGE_SIZE);
        setShops((prev) => isFirst ? result.items : [...prev, ...result.items]);
        setHasMore(pageNum * PAGE_SIZE < result.total);
        setIsPersonalList(true);
      } else if (user) {
        const result = await listMyStores(pageNum, PAGE_SIZE);
        if (result.total > 0 || pageNum > 1) {
          setShops((prev) => isFirst ? result.items : [...prev, ...result.items]);
          setHasMore(pageNum * PAGE_SIZE < result.total);
          setIsPersonalList(true);
        } else {
          const publicShops = await listShops();
          setShops(publicShops);
          setHasMore(false);
          setIsPersonalList(false);
        }
      } else {
        const publicShops = await listShops();
        setShops(publicShops);
        setHasMore(false);
        setIsPersonalList(false);
      }
    } catch {
      if (isFirst) {
        setShops([]);
        setIsPersonalList(false);
      }
    } finally {
      if (isFirst) setIsLoading(false); else setIsLoadingMore(false);
    }
  }, [user]);

  useEffect(() => {
    if (!authLoading) {
      pageRef.current = 1;
      void loadPage(1);
    }
  }, [authLoading, loadPage]);

  const handleLoadMore = useCallback(() => {
    const next = pageRef.current + 1;
    pageRef.current = next;
    void loadPage(next);
  }, [loadPage]);

  const handleRemove = useCallback(
    async (shopId: string) => {
      setShops((prev) => prev.filter((s) => s.id !== shopId));
      try {
        await removeStore(shopId);
      } catch {
        pageRef.current = 1;
        void loadPage(1);
      }
    },
    [loadPage],
  );

  const handleToggleFavorite = useCallback(
    async (shopId: string) => {
      const current = shops.find((s) => s.id === shopId);
      if (!current) return;
      const newValue = !current.isFavorite;
      setShops((prev) =>
        prev.map((s) => (s.id === shopId ? { ...s, isFavorite: newValue } : s)),
      );
      try {
        await toggleFavorite(shopId, newValue);
      } catch {
        setShops((prev) =>
          prev.map((s) => (s.id === shopId ? { ...s, isFavorite: !newValue } : s)),
        );
      }
    },
    [shops],
  );

  return (
    <>
      <TopBar
        title="Trouver une supérette"
        subtitle="Scanner le QR code ou rechercher par nom"
        backHref="/"
      />
      <StoreSearchCombobox />

      {isLoading ? (
        <div className="grid gap-2.5 md:grid-cols-3">
          {[1, 2, 3].map((i) => (
            <div key={i} className="h-[74px] animate-pulse rounded-lg bg-gray-100" />
          ))}
        </div>
      ) : (
        <>
          <StoreSelectList
            shops={shops}
            onRemove={isPersonalList ? handleRemove : undefined}
            onToggleFavorite={isPersonalList ? handleToggleFavorite : undefined}
          />
          {hasMore && (
            <div className="mt-4 text-center">
              <button
                type="button"
                onClick={handleLoadMore}
                disabled={isLoadingMore}
                className="text-sm font-bold text-primary disabled:opacity-40 disabled:cursor-not-allowed"
              >
                {isLoadingMore ? "Chargement…" : "Afficher plus"}
              </button>
            </div>
          )}
        </>
      )}

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
