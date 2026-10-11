import type { MerchantSubscription, SubscriptionPricingMode, SubscriptionPricingTerms } from '@/lib/types/subscriptions.types';

type Locale = 'fr' | 'ar';

export const PRICING_MODE_LABELS: Record<Locale, Record<SubscriptionPricingMode, string>> = {
  fr: { paid: 'Payant', discounted: 'Avec remise', free: 'Gratuit' },
  ar: { paid: 'مدفوع', discounted: 'بتخفيض', free: 'مجاني' },
};

export function subscriptionMoney(value: string, locale: Locale = 'fr'): string {
  return `${Number(value).toLocaleString(locale === 'ar' ? 'ar-TN' : 'fr-TN', {
    minimumFractionDigits: 3, maximumFractionDigits: 3,
  })} TND`;
}

export function subscriptionDate(value: string, locale: Locale = 'fr'): string {
  return new Date(value).toLocaleDateString(locale === 'ar' ? 'ar-TN' : 'fr-TN', { timeZone: 'Africa/Tunis' });
}

export function SubscriptionTermsSummary({ terms, locale = 'fr' }: { terms: SubscriptionPricingTerms; locale?: Locale }) {
  const ar = locale === 'ar';
  return (
    <div className="space-y-2 text-sm">
      <p className="font-semibold">
        {PRICING_MODE_LABELS[locale][terms.pricing_mode]} · {terms.billing_interval === 'annual' ? (ar ? 'سنوي' : 'Annuel') : (ar ? 'شهري' : 'Mensuel')}
      </p>
      <p>{ar ? 'التعريفة الأساسية للفترة' : 'Tarif de base de la période'} : {subscriptionMoney(terms.base_price_tnd, locale)}</p>
      {terms.pricing_mode === 'free' && <p>{terms.free_months === null
        ? (ar ? 'مجاني دون تاريخ نهاية.' : 'Gratuité sans date de fin.')
        : (ar ? `${terms.free_months} أشهر مجانية، ثم التعريفة الأساسية.` : `${terms.free_months} mois gratuits, puis tarif de base.`)}</p>}
      {terms.discounts.length > 0 && (
        <ul className="list-disc space-y-1 ps-5">
          {terms.discounts.map((discount, i) => (
            <li key={i}>
              {ar ? 'الأشهر' : 'Mois'} {discount.from_month}–{discount.to_month} : −{discount.type === 'percentage'
                ? `${Number(discount.value)} %`
                : `${subscriptionMoney(discount.value, locale)} / ${ar ? 'شهر' : 'mois'}`}
            </li>
          ))}
        </ul>
      )}
      {(terms.discounts.length > 0 || terms.free_months !== null) && <p className="text-xs text-muted">
        {ar ? 'تُحسب الأشهر من تاريخ سريان الشروط، ولا تتكرر التخفيضات تلقائياً كل سنة.' : 'Les mois sont comptés depuis la date d’effet. Les remises ne se répètent pas automatiquement chaque année.'}
      </p>}
    </div>
  );
}

type PricingView = Pick<MerchantSubscription, 'billing_interval' | 'period_price_tnd' | 'monthly_price_tnd' | 'current_period_started_at' | 'current_period_ends_at' | 'pricing_terms' | 'scheduled_pricing' | 'period_breakdown'>;

export function SubscriptionPricingSummary({ subscription, locale = 'fr' }: { subscription: PricingView; locale?: Locale }) {
  const ar = locale === 'ar';
  const annual = subscription.billing_interval === 'annual';
  return (
    <div className="space-y-4" dir={ar ? 'rtl' : undefined}>
      <div className="rounded-md border border-line bg-soft p-4">
        <p className="text-sm font-semibold">{annual ? (ar ? 'المبلغ السنوي' : 'Montant annuel') : (ar ? 'المبلغ الشهري' : 'Montant mensuel')}</p>
        <p className="mt-1 text-xl font-black text-primary">{subscriptionMoney(subscription.period_price_tnd ?? subscription.monthly_price_tnd, locale)}</p>
        <p className="mt-1 text-sm text-muted">{subscriptionDate(subscription.current_period_started_at, locale)} → {subscriptionDate(subscription.current_period_ends_at, locale)}</p>
      </div>
      {subscription.pricing_terms && <SubscriptionTermsSummary terms={subscription.pricing_terms} locale={locale} />}
      {!!subscription.period_breakdown?.length && <details className="rounded-md border border-line p-3">
        <summary className="cursor-pointer font-semibold">{ar ? 'تفاصيل المبلغ حسب الشهر' : 'Détail du calcul par mois'}</summary>
        <div className="mt-3 overflow-x-auto">
          <table className="w-full text-start text-sm">
            <caption className="sr-only">{ar ? 'تفاصيل المبلغ حسب الشهر' : 'Détail du calcul par mois'}</caption>
            <thead><tr className="border-b border-line">
              <th className="p-2 text-start">{ar ? 'الشهر' : 'Mois'}</th>
              <th className="p-2 text-end">{ar ? 'الأساس' : 'Base'}</th>
              <th className="p-2 text-end">{ar ? 'التخفيض' : 'Remise'}</th>
              <th className="p-2 text-end">{ar ? 'الصافي' : 'Net'}</th>
            </tr></thead>
            <tbody>{subscription.period_breakdown.map((month) => <tr key={month.month} className="border-b border-line last:border-0">
              <th scope="row" className="p-2 text-start font-normal">{month.month} <span className="block text-xs text-muted">{subscriptionDate(month.starts_at, locale)}</span></th>
              <td className="p-2 text-end tabular-nums">{subscriptionMoney(month.base_amount_tnd, locale)}</td>
              <td className="p-2 text-end tabular-nums">−{subscriptionMoney(month.discount_amount_tnd, locale)}</td>
              <td className="p-2 text-end font-semibold tabular-nums">{subscriptionMoney(month.amount_tnd, locale)}</td>
            </tr>)}</tbody>
          </table>
        </div>
      </details>}
      {subscription.scheduled_pricing && <section className="space-y-2 rounded-md border border-primary/30 bg-primary/5 p-4">
        <h3 className="font-bold">{ar ? 'تغيير مبرمج ابتداءً من' : 'Changement programmé à partir du'} {subscriptionDate(subscription.scheduled_pricing.effective_at, locale)}</h3>
        <SubscriptionTermsSummary terms={subscription.scheduled_pricing} locale={locale} />
      </section>}
    </div>
  );
}
