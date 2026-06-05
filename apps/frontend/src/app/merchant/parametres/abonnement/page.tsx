'use client';

import Link from 'next/link';
import { ArrowLeft } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Card } from '@/components/ui/Card';
import { useMerchantLocale, type MerchantLocale } from '@/lib/i18n/MerchantLocaleContext';
import { getMerchantSubscription } from '@/lib/services/subscriptions.service';
import type {
  MerchantSubscription,
  SubscriptionLifecycle,
  SubscriptionPricingPhase,
} from '@/lib/types/subscriptions.types';

const LIFECYCLE_LABELS: Record<MerchantLocale, Record<SubscriptionLifecycle, string>> = {
  fr: {
    active: 'Actif',
    payment_due: 'Paiement attendu',
    grace_period: 'Délai de grâce',
    suspended: 'Suspendu',
    cancelled: 'Annulé',
  },
  ar: {
    active: 'نشط',
    payment_due: 'الدفع مطلوب',
    grace_period: 'مهلة سماح',
    suspended: 'معلّق',
    cancelled: 'ملغى',
  },
};

const PHASE_LABELS: Record<MerchantLocale, Record<SubscriptionPricingPhase, string>> = {
  fr: {
    trial: 'Essai gratuit',
    promo: 'Promotion',
    standard: 'Standard',
  },
  ar: {
    trial: 'تجربة مجانية',
    promo: 'عرض ترويجي',
    standard: 'عادي',
  },
};

function formatDate(value: string | null, locale: MerchantLocale): string {
  if (!value) return locale === 'ar' ? 'غير مخطط' : 'Non planifié';

  const calendarDate = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);
  if (calendarDate) {
    const [, year, month, day] = calendarDate;
    const utcDate = new Date(Date.UTC(Number(year), Number(month) - 1, Number(day)));

    return utcDate.toLocaleDateString(locale === 'ar' ? 'ar-TN' : 'fr-TN', {
      timeZone: 'UTC',
    });
  }

  return new Date(value).toLocaleDateString(locale === 'ar' ? 'ar-TN' : 'fr-TN');
}

function formatMoney(value: string, currency: string, locale: MerchantLocale): string {
  return `${Number(value).toLocaleString(locale === 'ar' ? 'ar-TN' : 'fr-TN', {
    minimumFractionDigits: 3,
    maximumFractionDigits: 3,
  })} ${currency}`;
}

export default function MerchantSubscriptionPage() {
  const { locale, t } = useMerchantLocale();
  const [subscription, setSubscription] = useState<MerchantSubscription | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    setIsLoading(true);
    setError(null);

    getMerchantSubscription()
      .then((data) => {
        if (!active) return;
        setSubscription(data);
      })
      .catch(() => {
        if (!active) return;
        setError(t('merchant.settings.subscription.error'));
      })
      .finally(() => {
        if (active) setIsLoading(false);
      });

    return () => {
      active = false;
    };
  }, [t]);

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <div className="space-y-2">
        <Link
          href="/merchant/parametres"
          className="inline-flex items-center gap-1 text-sm font-bold text-primary hover:underline"
        >
          <ArrowLeft className="h-4 w-4" aria-hidden="true" />
          {t('merchant.settings.subscription.back')}
        </Link>
        <h1 className="text-2xl font-black text-ink">{t('merchant.settings.subscription.title')}</h1>
        <p className="text-sm text-muted">{t('merchant.settings.subscription.pageSubtitle')}</p>
      </div>

      {isLoading && (
        <Card>
          <p className="text-sm text-muted">{t('merchant.settings.subscription.loading')}</p>
        </Card>
      )}

      {error && (
        <div role="alert" className="rounded-md bg-status-cancel-bg px-4 py-3 text-sm text-status-cancel">
          {error}
        </div>
      )}

      {!isLoading && !error && subscription && (
        <Card as="section" className="space-y-5">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p className="text-xs font-semibold uppercase text-muted">
                {t('merchant.settings.subscription.lifecycle')}
              </p>
              <p className="mt-1 text-xl font-black text-ink">
                {LIFECYCLE_LABELS[locale][subscription.lifecycle]}
              </p>
            </div>
            <div className="rounded-md bg-soft px-3 py-2 text-sm font-black text-primary">
              {PHASE_LABELS[locale][subscription.pricing_phase]}
            </div>
          </div>

          <dl className="grid gap-3 sm:grid-cols-2">
            <Info label={t('merchant.settings.subscription.monthlyPrice')} value={formatMoney(subscription.monthly_price_tnd, subscription.currency, locale)} />
            <Info label={t('merchant.settings.subscription.currentPeriod')} value={`${formatDate(subscription.current_period_started_at, locale)} - ${formatDate(subscription.current_period_ends_at, locale)}`} />
            <Info label={t('merchant.settings.subscription.nextBilling')} value={formatDate(subscription.current_period_ends_at, locale)} />
            <Info label={t('merchant.settings.subscription.nextPhase')} value={formatDate(subscription.next_phase_change_at, locale)} />
          </dl>
        </Card>
      )}
    </div>
  );
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-md border border-line bg-soft px-3 py-2">
      <dt className="text-xs font-semibold uppercase text-muted">{label}</dt>
      <dd className="mt-1 text-sm font-bold text-ink">{value}</dd>
    </div>
  );
}
