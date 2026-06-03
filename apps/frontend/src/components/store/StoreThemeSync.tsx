'use client';

import { useSelectedStore } from '@/lib/store/SelectedStoreContext';
import { useStoreTheme } from '@/lib/hooks/useStoreTheme';

export function StoreThemeSync() {
  const { selectedStore } = useSelectedStore();
  useStoreTheme(selectedStore?.id ?? null);
  return null;
}
