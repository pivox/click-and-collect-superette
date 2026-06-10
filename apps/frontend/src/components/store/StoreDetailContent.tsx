'use client';

import { useClientLocale } from '@/lib/i18n/ClientLocaleContext';
import { Hero } from '@/components/layout/Hero';
import { TopBar } from '@/components/layout/TopBar';
import { Card } from '@/components/ui/Card';
import { Summary, SummaryRow } from '@/components/ui/Summary';
import { StickyBottom } from '@/components/layout/StickyBottom';
import { StartKadhiaCta } from '@/components/store/StartKadhiaCta';
import type { Shop } from '@/types';

interface StoreDetailContentProps {
  shop: Shop;
}

export function StoreDetailContent({ shop }: StoreDetailContentProps) {
  const { t } = useClientLocale();

  const badgeText = shop.isActive
    ? `${t('client.store.open')} · ${t('client.store.pickupFrom')} ${shop.nextPickupAt ?? '—'}`
    : t('client.store.closed');

  const shopForCta = { id: shop.id, name: shop.name, logoLetter: shop.logoLetter };

  return (
    <>
      <TopBar
        title={shop.name}
        subtitle={[shop.address, shop.city].filter(Boolean).join(' · ')}
        backHref="/"
      />

      {/* Desktop : hero gauche + infos droite */}
      <div className="md:grid md:grid-cols-[1.3fr_0.7fr] md:gap-5">
        <Hero
          badge={badgeText}
          title={shop.name}
          subtitle={t('client.storeDetail.heroSubtitle')}
        />

        <div className="mt-4 md:mt-0 space-y-3">
          <div className="grid grid-cols-2 gap-2.5">
            <Card compact>
              <strong className="block text-sm">{t('client.storeDetail.hours')}</strong>
              <span className="mt-1 block text-xs text-muted">
                {shop.opensAt && shop.closesAt ? `${shop.opensAt} — ${shop.closesAt}` : '—'}
              </span>
            </Card>
            <Card compact>
              <strong className="block text-sm">{t('client.storeDetail.distance')}</strong>
              <span className="mt-1 block text-xs text-muted">
                {shop.distanceKm != null ? `${shop.distanceKm} ${t('client.storeDetail.km')}` : '—'}
              </span>
            </Card>
          </div>

          <Card>
            <Summary>
              <SummaryRow label={t('client.storeDetail.payment')} value={t('client.storeDetail.paymentOnSite')} />
              <SummaryRow
                label={t('client.storeDetail.slot')}
                value={shop.nextPickupAt ? `${t('client.storeDetail.from')} ${shop.nextPickupAt}` : '—'}
              />
              <SummaryRow label={t('client.storeDetail.rating')} value={shop.rating?.toFixed(1) ?? '—'} />
            </Summary>
          </Card>

          {/* CTA inline sur desktop */}
          <div className="hidden md:block">
            <StartKadhiaCta shop={shopForCta} />
          </div>
        </div>
      </div>

      {/* CTA sticky sur mobile */}
      <StickyBottom className="md:hidden">
        <StartKadhiaCta shop={shopForCta} />
      </StickyBottom>
    </>
  );
}
