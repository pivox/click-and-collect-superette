'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { getMerchantDashboardToday } from '@/lib/services/merchant-dashboard.service';
import type { MerchantDashboardToday } from '@/lib/types/merchant.types';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import { formatTime } from '@/lib/format';

const COUNTERS = [
  ['En attente', 'submitted_count'],
  ['Acceptées', 'accepted_count'],
  ['En préparation', 'preparing_count'],
  ['Prêtes', 'ready_count'],
] as const;

export default function MerchantDashboardPage() {
  const { merchant } = useMerchantAuth();
  const [dashboard, setDashboard] = useState<MerchantDashboardToday | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadDashboard = useCallback(async () => {
    if (!merchant) return;
    setIsLoading(true);
    setError(null);
    try {
      setDashboard(await getMerchantDashboardToday(merchant.store.id));
    } catch {
      setError('Impossible de charger le dashboard marchand.');
    } finally {
      setIsLoading(false);
    }
  }, [merchant]);

  useEffect(() => {
    void loadDashboard();
  }, [loadDashboard]);

  return (
    <div>
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 className="text-h1 font-black">Dashboard marchand</h1>
          <p className="mt-1 text-sm text-muted">
            Vue opérationnelle du jour pour {merchant?.store.name}.
          </p>
        </div>
        <Button variant="ghost" size="md" onClick={() => void loadDashboard()}>
          {error ? 'Réessayer' : 'Actualiser'}
        </Button>
      </div>

      {error && (
        <div className="mt-4 rounded-md bg-status-cancel-bg px-4 py-3 text-sm text-status-cancel">
          {error}
        </div>
      )}

      {/* À faire maintenant */}
      {!isLoading && dashboard && (
        <section className="mt-6">
          <h2 className="mb-3 text-lg font-black">À faire maintenant</h2>
          {dashboard.submitted_count === 0 &&
          dashboard.urgent_submitted_count === 0 &&
          dashboard.preparing_count === 0 &&
          dashboard.ready_count === 0 ? (
            <div className="rounded-md bg-card p-5 shadow-card text-sm text-muted">
              Aucune action urgente pour le moment.
            </div>
          ) : (
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              {dashboard.urgent_submitted_count > 0 && (
                <Link href="/merchant/commandes">
                  <div className="rounded-md border-2 border-status-cancel bg-status-cancel-bg p-4 hover:opacity-90 transition-opacity cursor-pointer">
                    <p className="text-2xl font-black text-status-cancel">
                      {dashboard.urgent_submitted_count}
                    </p>
                    <p className="mt-1 text-sm font-bold text-status-cancel">Urgentes à accepter</p>
                    <p className="mt-2 text-xs font-extrabold text-status-cancel underline">
                      Traiter maintenant →
                    </p>
                  </div>
                </Link>
              )}
              {dashboard.submitted_count > 0 && (
                <Link href="/merchant/commandes">
                  <div className="rounded-md border border-line bg-card p-4 hover:bg-soft transition-colors cursor-pointer">
                    <p className="text-2xl font-black">{dashboard.submitted_count}</p>
                    <p className="mt-1 text-sm font-bold text-muted">En attente d&apos;acceptation</p>
                    <p className="mt-2 text-xs font-extrabold text-primary underline">
                      Voir les commandes →
                    </p>
                  </div>
                </Link>
              )}
              {dashboard.preparing_count > 0 && (
                <Link href="/merchant/commandes">
                  <div className="rounded-md border border-line bg-card p-4 hover:bg-soft transition-colors cursor-pointer">
                    <p className="text-2xl font-black">{dashboard.preparing_count}</p>
                    <p className="mt-1 text-sm font-bold text-muted">À préparer</p>
                    <p className="mt-2 text-xs font-extrabold text-primary underline">
                      Aller aux commandes →
                    </p>
                  </div>
                </Link>
              )}
              {dashboard.ready_count > 0 && (
                <Link href="/merchant/retrait">
                  <div className="rounded-md border border-line bg-card p-4 hover:bg-soft transition-colors cursor-pointer">
                    <p className="text-2xl font-black">{dashboard.ready_count}</p>
                    <p className="mt-1 text-sm font-bold text-muted">Prêtes à remettre</p>
                    <p className="mt-2 text-xs font-extrabold text-primary underline">
                      Ouvrir le retrait →
                    </p>
                  </div>
                </Link>
              )}
            </div>
          )}
        </section>
      )}

      <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        {COUNTERS.map(([label, key]) => (
          <div key={key} className="rounded-md bg-card p-5 shadow-card">
            <span className="text-sm text-muted">{label}</span>
            <strong className="mt-1 block text-h2 font-black">
              {isLoading ? '…' : dashboard?.[key] ?? 0}
            </strong>
          </div>
        ))}
        <div className="rounded-md bg-card p-5 shadow-card">
          <span className="text-sm text-muted">Urgentes</span>
          <strong className="mt-1 block text-h2 font-black">
            {isLoading ? '…' : dashboard?.urgent_submitted_count ?? 0}
          </strong>
        </div>
      </div>

      <section className="mt-6 rounded-md bg-card p-5 shadow-card">
        <h2 className="text-lg font-black">Prochains rendez-vous de retrait</h2>
        {isLoading ? (
          <p className="mt-3 text-sm text-muted">Chargement des rendez-vous…</p>
        ) : dashboard && dashboard.pickup_slots_today.length > 0 ? (
          <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            {dashboard.pickup_slots_today.map((slot) => (
              <div key={slot.pickup_slot_id} className="rounded-md border border-line p-4">
                <strong className="text-sm">
                  {formatTime(slot.starts_at)}–{formatTime(slot.ends_at)}
                </strong>
                <p className="mt-1 text-sm text-muted">
                  {slot.booked_count}/{slot.capacity} rendez-vous de retrait
                </p>
              </div>
            ))}
          </div>
        ) : (
          <p className="mt-3 text-sm text-muted">
            Aucun rendez-vous de retrait prévu aujourd&apos;hui.
          </p>
        )}
      </section>
    </div>
  );
}
