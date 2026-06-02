'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import type { Shop } from '@/types';
import { StoreCard } from '@/components/store/StoreCard';
import { StoreSwitchWarning } from '@/components/store/StoreSwitchWarning';
import { RemoveStoreDialog } from '@/components/store/RemoveStoreDialog';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';
import { hasActiveKadhia } from '@/lib/store/hasActiveKadhia';

export interface StoreSelectListProps {
  shops: Shop[];
  onRemove?: (shopId: string) => void;
  onToggleFavorite?: (shopId: string) => void;
}

export function StoreSelectList({ shops, onRemove, onToggleFavorite }: StoreSelectListProps) {
  const router = useRouter();
  const { selectedStore, selectStore } = useSelectedStore();
  const [pendingShop, setPendingShop] = useState<Shop | null>(null);
  const [confirmRemove, setConfirmRemove] = useState<Shop | null>(null);

  // Favorites first, then alphabetical within each group
  const sorted = [...shops].sort((a, b) => {
    if (a.isFavorite && !b.isFavorite) return -1;
    if (!a.isFavorite && b.isFavorite) return 1;
    return 0;
  });

  function doSelect(shop: Shop) {
    selectStore({ id: shop.id, name: shop.name, logoLetter: shop.logoLetter });
    router.push(`/stores/${shop.id}`);
  }

  function handleSelect(shop: Shop) {
    if (selectedStore && selectedStore.id !== shop.id && hasActiveKadhia(selectedStore.id)) {
      setPendingShop(shop);
      return;
    }
    doSelect(shop);
  }

  return (
    <>
      {pendingShop && selectedStore && (
        <StoreSwitchWarning
          currentStoreName={selectedStore.name}
          onConfirm={() => { const s = pendingShop; setPendingShop(null); doSelect(s); }}
          onCancel={() => setPendingShop(null)}
        />
      )}
      {confirmRemove && (
        <RemoveStoreDialog
          storeName={confirmRemove.name}
          onConfirm={() => { onRemove?.(confirmRemove.id); setConfirmRemove(null); }}
          onCancel={() => setConfirmRemove(null)}
        />
      )}
      <div className="grid gap-2.5 md:grid-cols-3">
        {sorted.length === 0 ? (
          <p className="col-span-3 py-6 text-center text-sm text-muted">
            Aucune supérette disponible pour le moment.
          </p>
        ) : (
          sorted.map((s) => (
            <div
              key={s.id}
              role="button"
              tabIndex={0}
              onClick={() => handleSelect(s)}
              onKeyDown={(e) => (e.key === 'Enter' || e.key === ' ') && handleSelect(s)}
            >
              <StoreCard
                shop={s}
                selected={selectedStore?.id === s.id}
                onRemove={onRemove ? () => setConfirmRemove(s) : undefined}
                onToggleFavorite={onToggleFavorite ? () => onToggleFavorite(s.id) : undefined}
              />
            </div>
          ))
        )}
      </div>
    </>
  );
}
