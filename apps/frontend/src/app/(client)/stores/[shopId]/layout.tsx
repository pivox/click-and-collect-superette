'use client';

import { useSelectedStore } from '@/lib/store/SelectedStoreContext';
import { useStoreTheme } from '@/lib/hooks/useStoreTheme';

export default function StoreLayout({
  children,
  params,
}: {
  children: React.ReactNode;
  params: { shopId: string };
}) {
  const { selectedStore } = useSelectedStore();
  // StoreThemeSync (root layout) handles the theme when this store is already selected.
  // Only apply here as a fallback for direct URL navigation without prior store selection.
  useStoreTheme(selectedStore?.id === params.shopId ? null : params.shopId);
  return <>{children}</>;
}
