'use client';

import { usePathname } from 'next/navigation';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';
import { useStoreTheme } from '@/lib/hooks/useStoreTheme';

export function StoreThemeSync() {
  const { selectedStore } = useSelectedStore();
  const pathname = usePathname();
  const routeStoreId = pathname.match(/^\/stores\/([^/]+)/)?.[1];
  const selectedStoreId =
    routeStoreId && routeStoreId !== selectedStore?.id ? null : selectedStore?.id;

  useStoreTheme(selectedStoreId ?? null);
  return null;
}
