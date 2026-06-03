'use client';

import { usePathname } from 'next/navigation';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';
import { useStoreTheme } from '@/lib/hooks/useStoreTheme';

const STORE_ROUTE_ID_PATTERN =
  /^\/stores\/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})(?:\/|$)/i;

export function StoreThemeSync() {
  const { selectedStore } = useSelectedStore();
  const pathname = usePathname();
  const routeStoreId = pathname.match(STORE_ROUTE_ID_PATTERN)?.[1];
  const selectedStoreId =
    routeStoreId && routeStoreId !== selectedStore?.id ? null : selectedStore?.id;

  useStoreTheme(selectedStoreId ?? null);
  return null;
}
