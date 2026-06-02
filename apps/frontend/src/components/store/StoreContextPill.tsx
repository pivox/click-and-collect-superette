'use client';

import Link from 'next/link';
import { useHydrated } from '@/lib/hooks/useHydrated';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';

export function StoreContextPill() {
  const isHydrated = useHydrated();
  const { selectedStore } = useSelectedStore();

  if (!isHydrated) return null;

  if (selectedStore) {
    return (
      <Link
        href="/stores"
        className="mb-4 flex w-fit items-center gap-2 rounded-full border border-primary/30 bg-primary/5 px-4 py-2 text-sm font-extrabold text-primary-dark transition-colors hover:bg-primary/10"
      >
        <span aria-hidden>📍</span>
        <span className="max-w-[180px] truncate">{selectedStore.name}</span>
        <span aria-label="changer" className="text-muted">↕</span>
      </Link>
    );
  }

  return (
    <Link
      href="/stores"
      className="mb-4 flex w-fit items-center gap-2 rounded-full border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-extrabold text-amber-800 transition-colors hover:bg-amber-100"
    >
      <span aria-hidden>🏪</span>
      <span>Choisir une supérette</span>
      <span aria-hidden>→</span>
    </Link>
  );
}
