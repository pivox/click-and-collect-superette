'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { getButtonClassName } from '@/components/ui/Button';
import { StoreSwitchWarning } from '@/components/store/StoreSwitchWarning';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';
import { useHydrated } from '@/lib/hooks/useHydrated';
import { hasActiveKadhia } from '@/lib/store/hasActiveKadhia';

interface StartKadhiaCtaProps {
  shop: { id: string; name: string; logoLetter?: string | null };
}

export function StartKadhiaCta({ shop }: StartKadhiaCtaProps) {
  const router = useRouter();
  const { selectedStore, selectStore } = useSelectedStore();
  const isHydrated = useHydrated();
  const [showWarning, setShowWarning] = useState(false);

  // Wait for the provider to hydrate from localStorage before auto-selecting,
  // otherwise selectedStore is null on first render and overwrites the persisted store.
  useEffect(() => {
    if (isHydrated && !selectedStore) {
      selectStore(shop);
    }
  }, [isHydrated, selectedStore, selectStore, shop]);

  function handleClick() {
    if (selectedStore && selectedStore.id !== shop.id && hasActiveKadhia(selectedStore.id)) {
      setShowWarning(true);
      return;
    }
    selectStore(shop);
    router.push(`/stores/${shop.id}/catalog`);
  }

  function confirmSwitch() {
    setShowWarning(false);
    selectStore(shop);
    router.push(`/stores/${shop.id}/catalog`);
  }

  return (
    <>
      {showWarning && selectedStore && (
        <StoreSwitchWarning
          currentStoreName={selectedStore.name}
          onConfirm={confirmSwitch}
          onCancel={() => setShowWarning(false)}
        />
      )}
      <button type="button" onClick={handleClick} className={getButtonClassName({ full: true })}>
        Commencer ma Kadhia
      </button>
    </>
  );
}
