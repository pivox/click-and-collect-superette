'use client';

import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { listMerchants } from '@/lib/services/admin/merchants.service';
import type { Merchant } from '@/lib/types/admin/merchants.types';
import type { MerchantSubscription } from '@/lib/types/subscriptions.types';
import { SubscriptionPricingForm } from './SubscriptionPricingForm';

export function NewSubscriptionPanel({ onSaved, onCancel, onBusyChange }: {
  onSaved: (subscription: MerchantSubscription) => void;
  onCancel: () => void;
  onBusyChange?: (busy: boolean) => void;
}) {
  const [search, setSearch] = useState('');
  const [merchants, setMerchants] = useState<Merchant[]>([]);
  const [merchant, setMerchant] = useState<Merchant | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [retry, setRetry] = useState(0);
  const [total, setTotal] = useState(0);

  useEffect(() => {
    let active = true;
    setLoading(true);
    setError(false);
    const timer = setTimeout(() => {
      void listMerchants(1, 20, search.trim() || undefined).then((data) => {
        if (!active) return;
        setMerchants(data.items);
        setTotal(data.total);
      }).catch(() => { if (active) setError(true); }).finally(() => { if (active) setLoading(false); });
    }, 200);
    return () => { active = false; clearTimeout(timer); };
  }, [search, retry]);

  if (merchant) return <div className="space-y-4">
    <p className="rounded-md bg-soft p-3 text-sm"><span className="font-bold">Marchand : </span>{merchant.email}</p>
    <SubscriptionPricingForm merchantId={merchant.id} onSaved={onSaved} onCancel={() => setMerchant(null)} onBusyChange={onBusyChange} />
  </div>;

  return <div className="space-y-4">
    <p className="text-sm text-muted">Sélectionne le marchand. L’abonnement sera partagé par les comptes de son organisation.</p>
    <label className="block text-sm font-semibold">Rechercher un marchand
      <input className="mt-1 w-full rounded-md border border-line bg-card px-3 py-2" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Nom ou email" />
    </label>
    {loading && <p role="status" className="text-sm text-muted">Recherche en cours…</p>}
    {error && <div role="alert" className="space-y-2 text-sm text-status-cancel">
      <p>Impossible de charger les marchands.</p>
      <Button variant="ghost" size="md" onClick={() => setRetry((n) => n + 1)}>Réessayer</Button>
    </div>}
    {!loading && !error && <>
      {merchants.length === 0 && <p className="text-sm text-muted">Aucun marchand trouvé.</p>}
      <ul className="max-h-72 divide-y divide-line overflow-y-auto rounded-md border border-line">
        {merchants.map((item) => <li key={item.id}>
          <button type="button" className="w-full px-3 py-3 text-start text-sm hover:bg-soft disabled:opacity-50" disabled={!item.is_active || item.subscription_lifecycle != null} onClick={() => setMerchant(item)}>
            <span className="block font-semibold">{item.email}</span>
            <span className="text-xs text-muted">{[item.first_name, item.last_name].filter(Boolean).join(' ')}{item.subscription_lifecycle != null ? ' · Abonnement existant' : !item.is_active ? ' · Compte inactif' : ''}</span>
          </button>
        </li>)}
      </ul>
      {total > merchants.length && <p className="text-xs text-muted">{merchants.length} marchands affichés sur {total}. Précise la recherche pour retrouver le marchand souhaité.</p>}
    </>}
    <Button type="button" variant="ghost" size="md" onClick={onCancel}>Annuler</Button>
  </div>;
}
