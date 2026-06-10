import { notFound } from 'next/navigation';
import { StoreDetailContent } from '@/components/store/StoreDetailContent';
import { getShop } from '@/lib/services';

export default async function StoreDetailPage({
  params,
}: {
  params: { shopId: string };
}) {
  const shop = await getShop(params.shopId);
  if (!shop) notFound();

  return <StoreDetailContent shop={shop} />;
}
