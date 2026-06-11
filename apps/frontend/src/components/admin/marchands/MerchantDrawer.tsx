'use client';
import { useState, useEffect } from 'react';
import axios from 'axios';
import { AdminDrawer } from '@/components/admin/ui/AdminDrawer';
import { MerchantCrmSection } from '@/components/admin/marchands/MerchantCrmSection';
import { createMerchant, updateMerchant } from '@/lib/services/admin/merchants.service';
import type { Merchant } from '@/lib/types/admin/merchants.types';

interface MerchantDrawerProps {
  open: boolean;
  onClose: () => void;
  merchant: Merchant | null;
  onSaved: () => void;
}

export function MerchantDrawer({ open, onClose, merchant, onSaved }: MerchantDrawerProps) {
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (merchant) {
      setFirstName(merchant.first_name ?? '');
      setLastName(merchant.last_name ?? '');
      setEmail(merchant.email);
      setPhone(merchant.phone ?? '');
    } else {
      setFirstName('');
      setLastName('');
      setEmail('');
      setPhone('');
    }
    setError(null);
  }, [merchant, open]);

  const handleSubmit = async () => {
    if (!firstName.trim()) { setError('Le prénom est obligatoire.'); return; }
    if (!lastName.trim()) { setError('Le nom est obligatoire.'); return; }
    if (!merchant && !email.trim()) { setError("L'email est obligatoire."); return; }

    setIsSubmitting(true);
    setError(null);
    try {
      if (merchant) {
        // email not updatable (not in AdminUpdateMerchantInput)
        await updateMerchant(merchant.id, {
          first_name: firstName.trim(),
          last_name: lastName.trim(),
          phone: phone.trim() || undefined,
        });
      } else {
        // @SerializedName on backend DTO → snake_case keys in payload
        await createMerchant({
          email: email.trim(),
          first_name: firstName.trim(),
          last_name: lastName.trim(),
          phone: phone.trim() || undefined,
        });
      }
      onSaved();
    } catch (e) {
      setError(
        axios.isAxiosError(e) && e.response?.status === 409
          ? 'Un compte avec cet email existe déjà.'
          : 'Une erreur est survenue. Réessayez.',
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  const inputClass =
    'w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20';
  const opsJournal = merchant?.ops_journal ?? null;
  const isSubscriptionSuspended = merchant?.subscription_lifecycle === 'suspended';

  return (
    <AdminDrawer
      open={open}
      onClose={onClose}
      title={merchant ? 'Modifier le marchand' : 'Nouveau marchand'}
      onSubmit={handleSubmit}
      isSubmitting={isSubmitting}
    >
      <div className="space-y-4">
        {error && (
          <div className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
            {error}
          </div>
        )}
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="mb-1 block text-sm font-semibold">Prénom *</label>
            <input
              type="text"
              value={firstName}
              onChange={(e) => setFirstName(e.target.value)}
              maxLength={100}
              className={inputClass}
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-semibold">Nom *</label>
            <input
              type="text"
              value={lastName}
              onChange={(e) => setLastName(e.target.value)}
              maxLength={100}
              className={inputClass}
            />
          </div>
        </div>
        {!merchant && (
          <div>
            <label className="mb-1 block text-sm font-semibold">Email *</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              maxLength={200}
              className={inputClass}
            />
          </div>
        )}
        {merchant && (
          <div>
            <label className="mb-1 block text-sm font-semibold text-muted">Email</label>
            <p className="rounded-md border border-line bg-soft px-3 py-2 text-sm text-muted">
              {merchant.email}
            </p>
          </div>
        )}
        <div>
          <label className="mb-1 block text-sm font-semibold">Téléphone</label>
          <input
            type="tel"
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            maxLength={30}
            placeholder="+216 XX XXX XXX"
            className={inputClass}
          />
        </div>
        {isSubscriptionSuspended && (
          <section className="rounded-md border border-amber-200 bg-amber-50 px-4 py-3">
            <h3 className="text-sm font-black text-amber-900">Suspension douce abonnement</h3>
            <p className="mt-1 text-sm text-amber-800">
              Les nouvelles Kadhia sont bloquées, le catalogue et l’historique restent conservés.
            </p>
          </section>
        )}
        {merchant && (
          <MerchantCrmSection merchantId={merchant.id} initialCrm={merchant.crm ?? null} />
        )}
        {merchant && opsJournal && (
          <section className="rounded-md border border-line bg-soft px-4 py-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <h3 className="text-sm font-black text-ink">Journal opérationnel</h3>
              <HealthBadge status={opsJournal.health_status} />
            </div>
            <div className="mt-3 grid gap-3 sm:grid-cols-3">
              <Metric
                label="Retards"
                value={opsJournal.overdue_orders_count.toString()}
                tone={opsJournal.overdue_orders_count > 0 ? 'danger' : 'neutral'}
              />
              <Metric
                label="Annulations"
                value={opsJournal.cancelled_orders_count.toString()}
                tone={opsJournal.cancelled_orders_count > 0 ? 'warning' : 'neutral'}
              />
              <Metric
                label="Dernière activité"
                value={formatLastActivity(opsJournal.last_activity_status)}
                detail={formatLastActivityDate(opsJournal.last_activity_at)}
              />
            </div>
            <div className="mt-4 border-t border-line pt-3">
              <h4 className="text-xs font-black uppercase text-muted">Vue santé</h4>
              <div className="mt-3 grid gap-3 sm:grid-cols-3">
                <Metric label="Commandes reçues" value={opsJournal.received_orders_count.toString()} />
                <Metric
                  label="Acceptées"
                  value={opsJournal.accepted_orders_count.toString()}
                  tone={opsJournal.accepted_orders_count > 0 ? 'success' : 'neutral'}
                />
                <Metric
                  label="Refusées"
                  value={opsJournal.rejected_orders_count.toString()}
                  tone={opsJournal.rejected_orders_count > 0 ? 'danger' : 'neutral'}
                />
                <Metric label="Délai moyen" value={formatAverageResponse(opsJournal.average_response_minutes)} />
                <Metric
                  label="Incidents"
                  value={`${opsJournal.open_incidents_count} / ${opsJournal.incidents_count}`}
                  tone={opsJournal.open_incidents_count > 0 ? 'danger' : opsJournal.incidents_count > 0 ? 'warning' : 'neutral'}
                />
                <Metric
                  label="Relances paiement"
                  value={opsJournal.payment_reminders_count.toString()}
                  tone={opsJournal.payment_reminders_count > 0 ? 'warning' : 'neutral'}
                />
                <Metric label="Actions admin" value={opsJournal.admin_actions_count.toString()} />
              </div>
            </div>
          </section>
        )}
      </div>
    </AdminDrawer>
  );
}

function Metric({
  label,
  value,
  detail,
  tone = 'neutral',
}: {
  label: string;
  value: string;
  detail?: string;
  tone?: 'neutral' | 'success' | 'warning' | 'danger';
}) {
  const valueColor = {
    neutral: 'text-ink',
    success: 'text-green-700',
    warning: 'text-amber-700',
    danger: 'text-status-cancel',
  }[tone];

  return (
    <div>
      <div className="text-xs font-semibold uppercase text-muted">{label}</div>
      <div className={`mt-1 text-lg font-black ${valueColor}`}>{value}</div>
      {detail && <div className="mt-1 text-xs text-muted">{detail}</div>}
    </div>
  );
}

function HealthBadge({ status }: { status: 'healthy' | 'watch' | 'risk' }) {
  const config = {
    healthy: { label: 'Sain', className: 'bg-green-50 text-green-700' },
    watch: { label: 'À surveiller', className: 'bg-amber-50 text-amber-800' },
    risk: { label: 'Risque', className: 'bg-status-cancel-bg text-status-cancel' },
  }[status];

  return (
    <span className={`rounded-full px-2 py-0.5 text-xs font-bold ${config.className}`}>
      {config.label}
    </span>
  );
}

function formatAverageResponse(value: number | null): string {
  if (null === value) {
    return '—';
  }

  return `${value} min`;
}

function formatLastActivity(status: string | null): string {
  if (!status) {
    return 'Aucune activité';
  }

  return {
    draft: 'Brouillon',
    submitted: 'Envoyée',
    accepted: 'Acceptée',
    partially_accepted: 'Partielle',
    rejected: 'Refusée',
    preparing: 'Préparation',
    ready: 'Prête',
    pickup_pending: 'Retrait',
    completed: 'Terminée',
    cancelled: 'Annulée',
  }[status] ?? status;
}

function formatLastActivityDate(value: string | null): string | undefined {
  if (!value) {
    return undefined;
  }

  return new Date(value).toLocaleString('fr-TN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  });
}
