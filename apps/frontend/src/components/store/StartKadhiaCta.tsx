'use client';

import { useEffect, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { getButtonClassName } from '@/components/ui/Button';
import { StoreSwitchWarning } from '@/components/store/StoreSwitchWarning';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';
import { useClientLocale } from '@/lib/i18n/ClientLocaleContext';
import { useHydrated } from '@/lib/hooks/useHydrated';
import { hasActiveKadhia } from '@/lib/store/hasActiveKadhia';
import { countStoreDraftKadhias } from '@/lib/services/kadhia.service';

interface StartKadhiaCtaProps {
  shop: { id: string; name: string; logoLetter?: string | null };
}

export function StartKadhiaCta({ shop }: StartKadhiaCtaProps) {
  const router = useRouter();
  const { selectedStore, selectStore } = useSelectedStore();
  const { t } = useClientLocale();
  const isHydrated = useHydrated();
  const [showWarning, setShowWarning] = useState(false);
  // null = still resolving how many draft Kadhias exist for this store.
  const [draftCount, setDraftCount] = useState<number | null>(null);
  const [isNavigating, setIsNavigating] = useState(false);
  // Synchronous guard: two clicks in the same tick must not both navigate
  // (state updates are async and would let the second click through).
  const navigatingRef = useRef(false);

  // Wait for the provider to hydrate from localStorage before auto-selecting,
  // otherwise selectedStore is null on first render and overwrites the persisted store.
  useEffect(() => {
    if (isHydrated && !selectedStore) {
      selectStore(shop);
    }
  }, [isHydrated, selectedStore, selectStore, shop]);

  // Resolve existing draft Kadhias to make the CTA contextual. Read-only: never
  // creates a Kadhia. On error we fall back to "Commencer" so the entry point is
  // never blocked — real network errors surface later on the catalog / Mes Kadhia.
  useEffect(() => {
    if (!isHydrated) return;
    let cancelled = false;
    void countStoreDraftKadhias(shop.id)
      .then((count) => { if (!cancelled) setDraftCount(count); })
      .catch(() => { if (!cancelled) setDraftCount(0); });
    return () => { cancelled = true; };
  }, [isHydrated, shop.id]);

  // ≥2 drafts → orient the client to "Mes Kadhia" (drafts tab); otherwise the
  // catalog auto-activates the single draft (or lets the client start a new one).
  const target = (draftCount ?? 0) >= 2 ? '/kadhia' : `/stores/${shop.id}/catalog`;

  function navigate() {
    if (navigatingRef.current) return;
    navigatingRef.current = true;
    setIsNavigating(true);
    selectStore(shop);
    router.push(target);
  }

  function handleClick() {
    if (navigatingRef.current) return;
    if (selectedStore && selectedStore.id !== shop.id && hasActiveKadhia(selectedStore.id)) {
      setShowWarning(true);
      return;
    }
    navigate();
  }

  function confirmSwitch() {
    setShowWarning(false);
    navigate();
  }

  // 3 contextual states; neutral "start" default while the draft count loads (null → 0).
  const count = draftCount ?? 0;
  const label = count === 0
    ? t('client.storeDetail.ctaStart')
    : count === 1
      ? t('client.storeDetail.ctaContinue')
      : t('client.storeDetail.ctaResume');

  return (
    <>
      {showWarning && selectedStore && (
        <StoreSwitchWarning
          currentStoreName={selectedStore.name}
          onConfirm={confirmSwitch}
          onCancel={() => setShowWarning(false)}
        />
      )}
      <button
        type="button"
        onClick={handleClick}
        // Disabled until the draft count resolves: clicking early would route to
        // the catalog even for ≥2 drafts (where the target should be /kadhia).
        disabled={isNavigating || draftCount === null}
        aria-busy={draftCount === null}
        className={getButtonClassName({ full: true })}
      >
        {label}
      </button>
    </>
  );
}
