import Link from 'next/link';
import { TopBar } from '@/components/layout/TopBar';
import { StoreSearchCombobox } from '@/components/store/StoreSearchCombobox';
import { StoreSelectList } from '@/components/store/StoreSelectList';
import { listShops } from '@/lib/services';
import type { Shop } from '@/types';

export const dynamic = 'force-dynamic';

export default async function StoresPage() {
  let shops: Shop[] = [];
  try {
    shops = await listShops();
  } catch {
    // backend unavailable — render empty state
  }
  return (
    <>
      <TopBar
        title="Trouver une supérette"
        subtitle="Scanner le QR code ou rechercher par nom"
        backHref="/"
      />
      <StoreSearchCombobox />
      <StoreSelectList shops={shops} />
      <p className="mt-4 text-center text-xs text-muted">
        Tu peux aussi{' '}
        <Link href="/" className="font-extrabold text-primary">
          scanner directement
        </Link>{' '}
        le QR à l&apos;entrée.
      </p>
    </>
  );
}
