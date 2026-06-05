'use client';

import Link from 'next/link';
import { ArrowLeft } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Card } from '@/components/ui/Card';
import { useMerchantLocale, type MerchantLocale } from '@/lib/i18n/MerchantLocaleContext';
import {
  getMerchantBillingDocument,
  listMerchantBillingDocuments,
} from '@/lib/services/billing-documents.service';
import { listMerchantSubscriptionPayments } from '@/lib/services/subscription-payments.service';
import { getMerchantSubscription } from '@/lib/services/subscriptions.service';
import type {
  BillingDocument,
  BillingDocumentStatus,
} from '@/lib/types/billing-documents.types';
import type { SubscriptionPayment } from '@/lib/types/subscription-payments.types';
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

const DOCUMENT_STATUS_LABELS: Record<MerchantLocale, Record<BillingDocumentStatus, string>> = {
  fr: {
    draft: 'Brouillon',
    issued: 'Émis',
    paid: 'Payé',
    overdue: 'En retard',
    cancelled: 'Annulé',
  },
  ar: {
    draft: 'مسودة',
    issued: 'صادر',
    paid: 'مدفوع',
    overdue: 'متأخر',
    cancelled: 'ملغى',
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

function hasPaymentDelay(subscription: MerchantSubscription | null, documents: BillingDocument[]): boolean {
  if (!subscription) return documents.some((document) => document.status === 'overdue');

  return ['payment_due', 'grace_period', 'suspended'].includes(subscription.lifecycle)
    || documents.some((document) => document.status === 'overdue');
}

export default function MerchantSubscriptionPage() {
  const { locale, t } = useMerchantLocale();
  const [subscription, setSubscription] = useState<MerchantSubscription | null>(null);
  const [documents, setDocuments] = useState<BillingDocument[]>([]);
  const [payments, setPayments] = useState<SubscriptionPayment[]>([]);
  const [documentDetail, setDocumentDetail] = useState<BillingDocument | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isDocumentsLoading, setIsDocumentsLoading] = useState(true);
  const [isPaymentsLoading, setIsPaymentsLoading] = useState(true);
  const [isDocumentDetailLoading, setIsDocumentDetailLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [documentsError, setDocumentsError] = useState<string | null>(null);
  const [paymentsError, setPaymentsError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    setIsLoading(true);
    setIsDocumentsLoading(true);
    setIsPaymentsLoading(true);
    setError(null);
    setDocumentsError(null);
    setPaymentsError(null);

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

    listMerchantBillingDocuments()
      .then((data) => {
        if (!active) return;
        setDocuments(data.items);
      })
      .catch(() => {
        if (!active) return;
        setDocumentsError(locale === 'ar'
          ? 'تعذر تحميل المستندات الشهرية.'
          : 'Impossible de charger les documents mensuels.');
      })
      .finally(() => {
        if (active) setIsDocumentsLoading(false);
      });

    listMerchantSubscriptionPayments()
      .then((data) => {
        if (!active) return;
        setPayments(data.items);
      })
      .catch(() => {
        if (!active) return;
        setPaymentsError(locale === 'ar'
          ? 'تعذر تحميل سجل المدفوعات.'
          : 'Impossible de charger l’historique des paiements.');
      })
      .finally(() => {
        if (active) setIsPaymentsLoading(false);
      });

    return () => {
      active = false;
    };
  }, [locale, t]);

  const openDocumentDetail = async (documentId: string) => {
    setIsDocumentDetailLoading(true);
    setDocumentsError(null);
    try {
      const data = await getMerchantBillingDocument(documentId);
      setDocumentDetail(data);
    } catch {
      setDocumentsError(locale === 'ar'
        ? 'تعذر تحميل تفاصيل المستند.'
        : 'Impossible de charger le détail du document.');
    } finally {
      setIsDocumentDetailLoading(false);
    }
  };

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

      {hasPaymentDelay(subscription, documents) && (
        <Card as="section" className="border-status-cancel-bg bg-status-cancel-bg/30">
          <h2 className="text-lg font-black text-ink">
            {locale === 'ar' ? 'الدفع مطلوب للاشتراك' : 'Paiement abonnement attendu'}
          </h2>
          <p className="mt-2 text-sm text-muted">
            {locale === 'ar'
              ? 'يرجى التواصل مع فريق Kadhia بعد الدفع اليدوي لتأكيد الاشتراك وتجنب التعليق التدريجي.'
              : 'Contactez l’équipe Kadhia après règlement manuel pour confirmer votre abonnement et éviter la suspension douce.'}
          </p>
        </Card>
      )}

      <Card as="section" className="space-y-4">
        <div>
          <h2 className="text-lg font-black text-ink">
            {locale === 'ar' ? 'المستندات الشهرية' : 'Documents mensuels'}
          </h2>
          <p className="mt-1 text-sm text-muted">
            {locale === 'ar'
              ? 'هذه مستندات داخلية غير ضريبية تخص اشتراك التاجر.'
              : 'Documents internes non fiscaux liés à votre abonnement marchand.'}
          </p>
        </div>

        {isDocumentsLoading && (
          <p className="text-sm text-muted">
            {locale === 'ar' ? 'جار تحميل المستندات…' : 'Chargement des documents…'}
          </p>
        )}

        {documentsError && (
          <div role="alert" className="rounded-md bg-status-cancel-bg px-4 py-3 text-sm text-status-cancel">
            {documentsError}
          </div>
        )}

        {!isDocumentsLoading && !documentsError && documents.length === 0 && (
          <p className="text-sm text-muted">
            {locale === 'ar' ? 'لا يوجد مستند شهري لعرضه.' : 'Aucun document mensuel à afficher.'}
          </p>
        )}

        {!isDocumentsLoading && documents.length > 0 && (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[620px] text-sm">
              <thead className="border-b border-line text-left text-xs font-semibold uppercase text-muted">
                <tr>
                  <th className="py-2 pr-3">{locale === 'ar' ? 'المستند' : 'Document'}</th>
                  <th className="py-2 pr-3">{locale === 'ar' ? 'الحالة' : 'Statut'}</th>
                  <th className="py-2 pr-3">{locale === 'ar' ? 'الفترة' : 'Période'}</th>
                  <th className="py-2 pr-3">{locale === 'ar' ? 'المبلغ' : 'Montant'}</th>
                  <th className="py-2 pr-3">{locale === 'ar' ? 'الأجل' : 'Échéance'}</th>
                  <th className="py-2 pr-3" />
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {documents.map((document) => (
                  <tr key={document.id}>
                    <td className="py-3 pr-3">
                      <div className="font-bold text-ink">{document.document_number}</div>
                      <div className="text-xs text-muted">{document.document_nature_label}</div>
                    </td>
                    <td className="py-3 pr-3">
                      <span className="rounded-full bg-soft px-2 py-0.5 text-xs font-semibold text-muted">
                        {DOCUMENT_STATUS_LABELS[locale][document.status]}
                      </span>
                    </td>
                    <td className="py-3 pr-3">
                      {formatDate(document.billing_period_start, locale)} - {formatDate(document.billing_period_end, locale)}
                    </td>
                    <td className="py-3 pr-3 tabular-nums">
                      {formatMoney(document.amount_tnd, document.currency, locale)}
                    </td>
                    <td className="py-3 pr-3">{formatDate(document.due_at, locale)}</td>
                    <td className="py-3 pr-3">
                      <button
                        type="button"
                        onClick={() => void openDocumentDetail(document.id)}
                        disabled={isDocumentDetailLoading}
                        className="text-xs font-semibold text-primary hover:underline disabled:text-muted"
                      >
                        {locale === 'ar' ? 'تفاصيل' : 'Détail'}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      {documentDetail && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <section
            role="dialog"
            aria-label={locale === 'ar' ? 'تفاصيل المستند الشهري' : 'Détail document mensuel'}
            className="w-full max-w-xl rounded-md bg-card p-5 shadow-xl"
          >
            <div className="flex items-start justify-between gap-3">
              <div>
                <h2 className="text-lg font-black text-ink">{documentDetail.document_number}</h2>
                <p className="mt-1 text-sm text-muted">{documentDetail.document_nature_label}</p>
              </div>
              <button type="button" className="text-sm font-bold text-muted" onClick={() => setDocumentDetail(null)}>
                {locale === 'ar' ? 'إغلاق' : 'Fermer'}
              </button>
            </div>

            <dl className="mt-5 grid gap-3 sm:grid-cols-2">
              <Info label={locale === 'ar' ? 'الحالة' : 'Statut'} value={DOCUMENT_STATUS_LABELS[locale][documentDetail.status]} />
              <Info label={locale === 'ar' ? 'الفترة' : 'Période'} value={`${formatDate(documentDetail.billing_period_start, locale)} - ${formatDate(documentDetail.billing_period_end, locale)}`} />
              <Info label={locale === 'ar' ? 'المبلغ' : 'Montant'} value={formatMoney(documentDetail.amount_tnd, documentDetail.currency, locale)} />
              <Info label={locale === 'ar' ? 'المتبقي' : 'Reste à payer'} value={formatMoney(documentDetail.amount_due_tnd, documentDetail.currency, locale)} />
            </dl>
          </section>
        </div>
      )}

      <Card as="section" className="space-y-4">
        <div>
          <h2 className="text-lg font-black text-ink">
            {locale === 'ar' ? 'سجل المدفوعات' : 'Historique paiements'}
          </h2>
          <p className="mt-1 text-sm text-muted">
            {locale === 'ar'
              ? 'قراءة فقط للمدفوعات اليدوية التي أكدها المسؤول.'
              : 'Lecture seule des paiements manuels confirmés par l’admin.'}
          </p>
        </div>

        {isPaymentsLoading && (
          <p className="text-sm text-muted">
            {locale === 'ar' ? 'جار تحميل المدفوعات…' : 'Chargement des paiements…'}
          </p>
        )}

        {paymentsError && (
          <div role="alert" className="rounded-md bg-status-cancel-bg px-4 py-3 text-sm text-status-cancel">
            {paymentsError}
          </div>
        )}

        {!isPaymentsLoading && !paymentsError && payments.length === 0 && (
          <p className="text-sm text-muted">
            {locale === 'ar' ? 'لا يوجد دفع يدوي مؤكد.' : 'Aucun paiement manuel confirmé.'}
          </p>
        )}

        {!isPaymentsLoading && payments.length > 0 && (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[560px] text-sm">
              <thead className="border-b border-line text-left text-xs font-semibold uppercase text-muted">
                <tr>
                  <th className="py-2 pr-3">{locale === 'ar' ? 'التاريخ' : 'Date'}</th>
                  <th className="py-2 pr-3">{locale === 'ar' ? 'الوسيلة' : 'Moyen'}</th>
                  <th className="py-2 pr-3">{locale === 'ar' ? 'المبلغ' : 'Montant'}</th>
                  <th className="py-2 pr-3">{locale === 'ar' ? 'المرجع' : 'Référence'}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {payments.map((payment) => (
                  <tr key={payment.id}>
                    <td className="py-3 pr-3">{formatDate(payment.paid_at, locale)}</td>
                    <td className="py-3 pr-3">
                      {payment.method === 'cash'
                        ? (locale === 'ar' ? 'نقدا' : 'Espèces')
                        : (locale === 'ar' ? 'تحويل بنكي' : 'Virement')}
                    </td>
                    <td className="py-3 pr-3 tabular-nums">
                      {formatMoney(payment.amount_tnd, payment.currency, locale)}
                    </td>
                    <td className="py-3 pr-3">{payment.reference ?? '-'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
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
