'use client';

import { useCallback, useEffect, useState } from 'react';
import { getAdminMessengerMonitoring } from '@/lib/services/admin/ops.service';
import type { AdminMessengerMonitoring } from '@/lib/types/admin/ops.types';

export default function AdminOpsPage() {
  const [snapshot, setSnapshot] = useState<AdminMessengerMonitoring | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setIsLoading(true);
    setError(null);
    void getAdminMessengerMonitoring()
      .then(setSnapshot)
      .catch((err: unknown) => {
        console.error('[admin-ops] messenger monitoring failed', err);
        setError('Impossible de charger la santé plateforme.');
      })
      .finally(() => setIsLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const status = snapshot?.status ?? 'ok';
  const isDegraded = status === 'degraded';

  return (
    <div>
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h1 className="text-h1 font-black">Santé plateforme</h1>
          <p className="mt-1 text-muted">
            Suivi ops des jobs asynchrones qui protègent les rappels, expirations et relances.
          </p>
        </div>
        <button
          type="button"
          onClick={load}
          className="inline-flex min-h-[44px] items-center justify-center rounded-md border border-line bg-card px-4 text-sm font-black text-ink hover:bg-soft"
        >
          Rafraîchir
        </button>
      </div>

      {error && (
        <div className="mt-4 flex items-center gap-3 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          <span className="flex-1">{error}</span>
          <button type="button" onClick={load} className="shrink-0 font-semibold underline">
            Réessayer
          </button>
        </div>
      )}

      {isLoading && !snapshot ? (
        <div className="mt-6 rounded-md border border-line bg-card px-4 py-6 text-sm text-muted">
          Chargement de la santé plateforme…
        </div>
      ) : snapshot ? (
        <section className="mt-6 rounded-md border border-line bg-card p-5">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p className="text-xs font-extrabold uppercase text-muted">Symfony Messenger</p>
              <h2 className="mt-1 text-h2 font-black text-ink">Jobs asynchrones</h2>
              <p className="mt-1 text-sm text-muted">
                Dernière vérification le {formatDateTime(snapshot.checked_at)}
              </p>
            </div>
            <span
              className={`w-fit rounded-full px-3 py-1 text-xs font-black ${
                isDegraded
                  ? 'bg-status-cancel-bg text-status-cancel'
                  : 'bg-green-100 text-green-700'
              }`}
            >
              {isDegraded ? 'Messenger dégradé' : 'Messenger OK'}
            </span>
          </div>

          <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Metric label="File en attente" value={snapshot.pending.toString()} danger={snapshot.pending >= snapshot.thresholds.pending} />
            <Metric label="Messages en échec" value={snapshot.failed.toString()} danger={snapshot.failed > 0} />
            <Metric label="Âge plus vieux message" value={formatAge(snapshot.oldest_age_s)} danger={snapshot.oldest_age_s !== null && snapshot.oldest_age_s >= snapshot.thresholds.oldest_age_s} />
            <Metric label="Dernière activité worker" value={formatDateTime(snapshot.last_consumed_at)} />
          </div>

          <div className="mt-5 grid gap-4 border-t border-line pt-4 sm:grid-cols-2">
            <Metric label="Seuil attente" value={`${snapshot.thresholds.pending} messages`} />
            <Metric label="Seuil ancienneté" value={formatAge(snapshot.thresholds.oldest_age_s)} />
          </div>

          <div className="mt-5 rounded-md bg-soft px-4 py-3 text-sm text-muted">
            {snapshot.failed > 0 && (
              <p className="font-semibold text-status-cancel">
                Inspecter les messages en échec avec le runbook worker Messenger.
              </p>
            )}
            {snapshot.oldest_age_s !== null && snapshot.oldest_age_s >= snapshot.thresholds.oldest_age_s && (
              <p className="mt-1 font-semibold text-status-cancel">
                Vérifier que le worker consomme la file async.
              </p>
            )}
            {snapshot.failed === 0 && snapshot.oldest_age_s === null && (
              <p>Aucun message en attente. Aucun diagnostic manuel requis.</p>
            )}
            {snapshot.failed === 0 && snapshot.oldest_age_s !== null && snapshot.oldest_age_s < snapshot.thresholds.oldest_age_s && (
              <p>File active sous les seuils retournés par l’API.</p>
            )}
          </div>
        </section>
      ) : null}
    </div>
  );
}

function Metric({
  label,
  value,
  danger = false,
}: {
  label: string;
  value: string;
  danger?: boolean;
}) {
  return (
    <div>
      <div className="text-xs font-semibold uppercase text-muted">{label}</div>
      <div className={`mt-1 text-2xl font-black ${danger ? 'text-status-cancel' : 'text-ink'}`}>
        {value}
      </div>
    </div>
  );
}

function formatAge(value: number | null): string {
  if (value === null) {
    return 'Aucun message en attente';
  }
  if (value < 60) {
    return `${value} s`;
  }

  return `${Math.round(value / 60)} min`;
}

function formatDateTime(value: string | null): string {
  if (!value) {
    return '-';
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
