'use client';

import { useStoreTheme } from '@/lib/hooks/useStoreTheme';

export default function StoreLayout({
  children,
  params,
}: {
  children: React.ReactNode;
  params: { shopId: string };
}) {
  useStoreTheme(params.shopId);
  return <>{children}</>;
}
