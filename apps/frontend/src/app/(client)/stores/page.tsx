"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { TopBar } from "@/components/layout/TopBar";
import { StoreSearchCombobox } from "@/components/store/StoreSearchCombobox";
import { StoreSelectList } from "@/components/store/StoreSelectList";
import { listMyStores, listShops, removeStore, toggleFavorite } from "@/lib/services";
import { USE_MOCKS } from "@/lib/services";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";
import type { Shop } from "@/types";

export default function StoresPage() {
  const { user, isLoading: authLoading } = useClientAuth();
  const [shops, setShops] = useState<Shop[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  // In mock mode, always use the personal list (mutable mock state).
  // In real mode, use the personal list when logged in, public list otherwise.
  const showActions = USE_MOCKS || !!user;

  const load = useCallback(async () => {
    setIsLoading(true);
    try {
      const data = showActions ? await listMyStores() : await listShops();
      setShops(data);
    } catch {
      setShops([]);
    } finally {
      setIsLoading(false);
    }
  }, [showActions]);

  useEffect(() => {
    if (!authLoading) void load();
  }, [authLoading, load]);

  const handleRemove = useCallback(
    async (shopId: string) => {
      setShops((prev) => prev.filter((s) => s.id !== shopId));
      try {
        await removeStore(shopId);
      } catch {
        void load();
      }
    },
    [load],
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
        <StoreSelectList
          shops={shops}
          onRemove={showActions ? handleRemove : undefined}
          onToggleFavorite={showActions ? handleToggleFavorite : undefined}
        />
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
