'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { AdminTable, type Column } from '@/components/admin/ui/AdminTable';
import { Button } from '@/components/ui/Button';
import {
  getAdminSubscription,
  listAdminSubscriptions,
} from '@/lib/services/subscriptions.service';
import type {
  MerchantSubscription,
  SubscriptionLifecycle,
  SubscriptionPricingPhase,
} from '@/lib/types/subscriptions.types';

const PAGE_SIZE = 20;

const LIFECYCLE_LABELS: Record<SubscriptionLifecycle, string> = {
  active: 'Actif',
  payment_due: 'Paiement attendu',
  grace_period: 'Délai de grâce',
  suspended: 'Suspendu',
  cancelled: 'Annulé',
};

const PHASE_LABELS: Record<SubscriptionPricingPhase, string> = {
  trial: 'Essai gratuit',
  promo: 'Promotion',
  standard: 'Standard',
};

function formatDate(value: string | null): string {
  if (!value) return 'Non planifié';

  const calendarDate = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);
  if (calendarDate) {
    const [, year, month, day] = calendarDate;

    return `${day}/${month}/${year}`;
  }

  return new Date(value).toLocaleDateString('fr-TN');
}

function formatMoney(value: string, currency: string): string {
  return `${Number(value).toLocaleString('fr-TN', {
    minimumFractionDigits: 3,
    maximumFractionDigits: 3,
  })} ${currency}`;
}

function statusClass(lifecycle: SubscriptionLifecycle): string {
  if (lifecycle === 'suspended' || lifecycle === 'cancelled') {
    return 'bg-status-cancel-bg text-status-cancel';
  }
  if (lifecycle === 'payment_due' || lifecycle === 'grace_period') {
    return 'bg-[var(--status-wait-bg)] text-[var(--status-wait)]';
  }

  return 'bg-green-100 text-green-700';
}

export default function AdminSubscriptionsPage() {
  const [items, setItems] = useState<MerchantSubscription[]>([]);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [detail, setDetail] = useState<MerchantSubscription | null>(null);
  const [isDetailLoading, setIsDetailLoading] = useState(false);
  const detailRequestSeq = useRef(0);

  const load = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await listAdminSubscriptions({ page, limit: PAGE_SIZE });
      setItems(data.items);
      setTotal(data.total);
    } catch (err) {
      console.error('[admin-subscriptions] list failed', err);
      setError('Impossible de charger les abonnements marchands.');
    } finally {
      setIsLoading(false);
    }
  }, [page]);

  useEffect(() => {
    void load();
  }, [load]);

  const openDetail = async (subscriptionId: string) => {
    const requestSeq = detailRequestSeq.current + 1;
    detailRequestSeq.current = requestSeq;
    setIsDetailLoading(true);
    setError(null);
    try {
      const data = await getAdminSubscription(subscriptionId);
      if (detailRequestSeq.current !== requestSeq) return;
      setDetail(data);
    } catch (err) {
      if (detailRequestSeq.current !== requestSeq) return;
      console.error('[admin-subscriptions] detail failed', err);
      setError("Impossible de charger le détail de l'abonnement.");
    } finally {
      if (detailRequestSeq.current === requestSeq) {
        setIsDetailLoading(false);
      }
    }
  };

  const closeDetail = () => {
    detailRequestSeq.current += 1;
    setDetail(null);
    setIsDetailLoading(false);
  };

  const columns: Column<MerchantSubscription>[] = [
    {
      key: 'merchant_email',
      label: 'Marchand',
      render: (row) => (
        <div>
          <div className="font-medium">{row.merchant_email}</div>
          <div className="text-xs text-muted">{row.merchant_id}</div>
        </div>
      ),
    },
    {
      key: 'lifecycle',
      label: 'Lifecycle',
      render: (row) => (
        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusClass(row.lifecycle)}`}>
          {LIFECYCLE_LABELS[row.lifecycle]}
        </span>
      ),
    },
    {
      key: 'pricing_phase',
      label: 'Phase tarifaire',
      render: (row) => PHASE_LABELS[row.pricing_phase],
    },
    {
      key: 'monthly_price_tnd',
      label: 'Montant',
      render: (row) => <span className="tabular-nums">{formatMoney(row.monthly_price_tnd, row.currency)}</span>,
    },
    {
      key: 'current_period_ends_at',
      label: 'Prochaine échéance',
      render: (row) => formatDate(row.current_period_ends_at),
    },
    {
      key: 'actions',
      label: '',
      render: (row) => (
        <button
          type="button"
          onClick={() => void openDetail(row.id)}
          disabled={isDetailLoading}
          aria-label="Voir le détail abonnement"
          className="text-xs font-semibold text-primary hover:underline disabled:text-muted"
        >
          {isDetailLoading ? 'Chargement…' : 'Voir détail'}
        </button>
      ),
    },
  ];

  return (
    <div>
      <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-h1 font-black">Abonnements marchands</h1>
          <p className="mt-1 text-sm text-muted">
            Consultation des phases tarifaires et du lifecycle commercial des marchands.
          </p>
        </div>
        <Button variant="ghost" size="md" onClick={() => void load()}>
          {error ? 'Réessayer' : 'Actualiser'}
        </Button>
      </div>

      {error && (
        <div role="alert" className="mb-4 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          {error}
        </div>
      )}

      <AdminTable
        columns={columns}
        data={items}
        isLoading={isLoading}
        emptyMessage="Aucun abonnement marchand à afficher."
        pagination={{ page, total, limit: PAGE_SIZE, onPageChange: setPage }}
      />

      {detail && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <section
            role="dialog"
            aria-label="Détail abonnement"
            className="w-full max-w-xl rounded-md bg-card p-5 shadow-xl"
          >
            <div className="flex items-start justify-between gap-3">
              <div>
                <h2 className="text-lg font-black text-ink">Détail abonnement</h2>
                <p className="mt-1 text-sm text-muted">{detail.merchant_email}</p>
              </div>
              <button type="button" className="text-sm font-bold text-muted" onClick={closeDetail}>
                Fermer
              </button>
            </div>

            <dl className="mt-5 grid gap-3 sm:grid-cols-2">
              <Detail label="Lifecycle" value={LIFECYCLE_LABELS[detail.lifecycle]} />
              <Detail label="Phase tarifaire" value={PHASE_LABELS[detail.pricing_phase]} />
              <Detail label="Montant mensuel" value={formatMoney(detail.monthly_price_tnd, detail.currency)} />
              <Detail label="Début période" value={formatDate(detail.current_period_started_at)} />
              <Detail label="Fin période" value={formatDate(detail.current_period_ends_at)} />
              <Detail label="Prochain changement" value={formatDate(detail.next_phase_change_at)} />
            </dl>
          </section>
        </div>
      )}
    </div>
  );
}

function Detail({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-md border border-line bg-soft px-3 py-2">
      <dt className="text-xs font-semibold uppercase text-muted">{label}</dt>
      <dd className="mt-1 text-sm font-bold text-ink">{value}</dd>
    </div>
  );
}
