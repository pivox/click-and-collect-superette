'use client';

import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import type { Shop } from "@/types";
import { Card } from "@/components/ui/Card";

export interface StoreCardProps {
  shop: Shop;
  href?: string;
  selected?: boolean;
  onRemove?: () => void;
  onToggleFavorite?: () => void;
}

export function StoreCard({ shop, href, selected, onRemove, onToggleFavorite }: StoreCardProps) {
  const [menuOpen, setMenuOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);
  const hasActions = onRemove !== undefined || onToggleFavorite !== undefined;

  useEffect(() => {
    if (!menuOpen) return;
    function handleOutside(e: MouseEvent) {
      if (menuRef.current && !menuRef.current.contains(e.target as Node)) {
        setMenuOpen(false);
      }
    }
    document.addEventListener("mousedown", handleOutside);
    return () => document.removeEventListener("mousedown", handleOutside);
  }, [menuOpen]);

  function toggleMenu(e: React.MouseEvent) {
    e.stopPropagation();
    e.preventDefault();
    setMenuOpen((v) => !v);
  }

  function handleFavorite(e: React.MouseEvent) {
    e.stopPropagation();
    e.preventDefault();
    setMenuOpen(false);
    onToggleFavorite?.();
  }

  function handleRemove(e: React.MouseEvent) {
    e.stopPropagation();
    e.preventDefault();
    setMenuOpen(false);
    onRemove?.();
  }

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
      {shop.isFavorite && (
        <span className="text-base leading-none" aria-label="Favori">
          ⭐
        </span>
      )}
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
      {hasActions && (
        <div ref={menuRef} className="relative ml-1 shrink-0">
          <button
            type="button"
            aria-label="Actions"
            onClick={toggleMenu}
            className="flex h-8 w-8 items-center justify-center rounded-full text-muted hover:bg-soft transition-colors text-lg leading-none"
          >
            ⋮
          </button>
          {menuOpen && (
            <div className="absolute right-0 top-9 z-20 min-w-[190px] rounded-xl border border-line bg-white py-1 shadow-xl">
              {onToggleFavorite && (
                <button
                  type="button"
                  onClick={handleFavorite}
                  className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-extrabold text-ink hover:bg-soft"
                >
                  <span>⭐</span>
                  {shop.isFavorite ? "Retirer des favoris" : "Mettre en favori"}
                </button>
              )}
              {onRemove && (
                <button
                  type="button"
                  onClick={handleRemove}
                  className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-extrabold text-danger hover:bg-soft"
                >
                  <span>🗑</span>
                  Retirer de ma liste
                </button>
              )}
            </div>
          )}
        </div>
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
