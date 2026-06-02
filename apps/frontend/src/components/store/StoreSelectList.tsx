'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import type { Shop } from '@/types';
import { StoreCard } from '@/components/store/StoreCard';
import { StoreSwitchWarning } from '@/components/store/StoreSwitchWarning';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';
import { hasActiveKadhia } from '@/lib/store/hasActiveKadhia';

export function StoreSelectList({ shops }: { shops: Shop[] }) {
  const router = useRouter();
  const { selectedStore, selectStore } = useSelectedStore();
  const [pendingShop, setPendingShop] = useState<Shop | null>(null);

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
      <div className="grid gap-2.5 md:grid-cols-3">
        {shops.length === 0 ? (
          <p className="col-span-3 py-6 text-center text-sm text-muted">
            Aucune supérette disponible pour le moment.
          </p>
        ) : (
          shops.map((s) => (
            <div
              key={s.id}
              role="button"
              tabIndex={0}
              onClick={() => handleSelect(s)}
              onKeyDown={(e) => e.key === 'Enter' && handleSelect(s)}
            >
              <StoreCard shop={s} selected={selectedStore?.id === s.id} />
            </div>
          ))
        )}
      </div>
    </>
  );
}
