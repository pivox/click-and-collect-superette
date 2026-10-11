import type { BillingDocument } from '@/lib/types/billing-documents.types';
import { SubscriptionPricingSummary } from './SubscriptionPricingSummary';

export function BillingDocumentPricingSummary({ document, locale = 'fr' }: { document: BillingDocument; locale?: 'fr' | 'ar' }) {
  if (!document.pricing_snapshot) return null;
  return <section className="mt-5 space-y-3">
    <h3 className="font-bold">{locale === 'ar' ? 'تفاصيل التسعير عند الإصدار' : 'Tarification conservée à l’émission'}</h3>
    <SubscriptionPricingSummary locale={locale} subscription={{
      ...document.pricing_snapshot,
      monthly_price_tnd: document.amount_tnd,
      current_period_started_at: document.billing_period_start,
      current_period_ends_at: document.billing_period_end,
    }} />
  </section>;
}
