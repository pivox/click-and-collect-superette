'use client';

import { isAxiosError } from 'axios';
import { useRef, useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/Button';
import { SubscriptionPricingSummary, subscriptionDate } from '@/components/subscriptions/SubscriptionPricingSummary';
import { createAdminSubscription, previewAdminSubscriptionPricing, previewNewAdminSubscription, updateAdminSubscriptionPricing } from '@/lib/services/subscriptions.service';
import type { MerchantSubscription, SubscriptionDiscount, SubscriptionPricingInput } from '@/lib/types/subscriptions.types';

const control = 'mt-1 w-full rounded-md border border-line bg-card px-3 py-2 text-sm';
const newDiscount = (): SubscriptionDiscount => ({ from_month: 1, to_month: 1, type: 'percentage', value: '50' });

function validatePricing(input: SubscriptionPricingInput): string | null {
  if (!/^\d{1,7}(\.\d{1,3})?$/.test(input.base_price_tnd)) return 'Saisis un tarif en TND avec au maximum trois décimales.';
  if (!input.reason.trim()) return 'Renseigne le motif de la modification.';
  if (input.free_months !== null && (!Number.isInteger(input.free_months) || input.free_months < 1 || input.free_months > 120)) return 'La gratuité doit durer entre 1 et 120 mois.';
  if (input.pricing_mode === 'discounted' && !input.discounts.length) return 'Ajoute au moins une remise.';
  const sorted = [...input.discounts].sort((a, b) => a.from_month - b.from_month);
  for (let i = 0; i < sorted.length; i++) {
    const row = sorted[i];
    if (!Number.isInteger(row.from_month) || !Number.isInteger(row.to_month) || row.from_month < 1 || row.to_month > 120 || row.to_month < row.from_month) return 'Chaque plage doit aller du premier au dernier mois, entre 1 et 120.';
    if (!/^\d{1,7}(\.\d{1,3})?$/.test(row.value) || Number(row.value) <= 0 || (row.type === 'percentage' && Number(row.value) > 100)) return 'Saisis une remise positive, limitée à 100 % pour un pourcentage.';
    if (i > 0 && row.from_month <= sorted[i - 1].to_month) return 'Les plages de remise se chevauchent. Choisis une seule remise par mois.';
  }
  return null;
}

function requestError(error: unknown): string {
  if (isAxiosError(error)) {
    if (error.response?.status === 409) return 'Cet abonnement existe déjà, est annulé ou a changé. Actualise la liste avant de réessayer.';
    if (error.response?.status === 422 || error.response?.status === 400) return 'Les conditions ne sont pas valides. Vérifie les montants, les mois et le marchand sélectionné.';
    if (error.response?.status === 403) return 'Cette action est réservée aux administrateurs.';
  }
  return 'Impossible d’enregistrer ou de calculer les conditions. Réessaie.';
}

interface Props {
  subscription?: MerchantSubscription;
  merchantId?: string;
  onSaved: (subscription: MerchantSubscription) => void;
  onCancel: () => void;
  onBusyChange?: (busy: boolean) => void;
}

export function SubscriptionPricingForm({ subscription, merchantId, onSaved, onCancel, onBusyChange }: Props) {
  const terms = subscription?.scheduled_pricing ?? subscription?.pricing_terms;
  const [input, setInput] = useState<SubscriptionPricingInput>({
    billing_interval: terms?.billing_interval ?? 'monthly',
    pricing_mode: terms?.pricing_mode ?? 'paid',
    base_price_tnd: terms?.base_price_tnd ?? '50.000',
    free_months: terms?.free_months ?? null,
    discounts: terms?.discounts.map((row) => ({ ...row })) ?? [], reason: '',
  });
  const [preview, setPreview] = useState<MerchantSubscription | null>(null);
  const [previewKey, setPreviewKey] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const lock = useRef(false);
  const formKey = JSON.stringify(input);
  const latestKey = useRef(formKey);
  latestKey.current = formKey;

  const change = (patch: Partial<SubscriptionPricingInput>) => {
    setInput((current) => ({ ...current, ...patch }));
    setPreview(null);
    setPreviewKey(null);
    setError(null);
  };
  const changeDiscount = (index: number, patch: Partial<SubscriptionDiscount>) => change({
    discounts: input.discounts.map((row, i) => i === index ? { ...row, ...patch } : row),
  });

  const calculate = async (event: FormEvent) => {
    event.preventDefault();
    if (lock.current) return;
    const invalid = validatePricing(input);
    if (invalid) { setError(invalid); return; }
    lock.current = true;
    setBusy(true);
    onBusyChange?.(true);
    setError(null);
    setPreview(null);
    setPreviewKey(null);
    const requestedKey = formKey;
    try {
      const result = subscription
        ? await previewAdminSubscriptionPricing(subscription.id, input)
        : await previewNewAdminSubscription({ ...input, merchant_id: merchantId ?? '' });
      if (latestKey.current !== requestedKey) return;
      setPreview(result);
      setPreviewKey(requestedKey);
    } catch (err) { setError(requestError(err)); }
    finally { lock.current = false; setBusy(false); onBusyChange?.(false); }
  };

  const save = async () => {
    if (lock.current || !preview || previewKey !== formKey) return;
    lock.current = true;
    setBusy(true);
    onBusyChange?.(true);
    setError(null);
    try {
      const result = subscription
        ? await updateAdminSubscriptionPricing(subscription.id, input)
        : await createAdminSubscription({ ...input, merchant_id: merchantId ?? '' });
      onSaved(result);
    } catch (err) { setError(requestError(err)); setPreviewKey(null); }
    finally { lock.current = false; setBusy(false); onBusyChange?.(false); }
  };

  return <form onSubmit={calculate} className="space-y-5">
    <p className="text-sm text-muted">{subscription
      ? `Ces conditions prendront effet au prochain renouvellement, le ${subscriptionDate(subscription.current_period_ends_at)}. Les documents déjà émis restent inchangés.`
      : 'L’abonnement démarre dès sa création. Toutes les formules donnent accès aux mêmes fonctionnalités.'}</p>
    {subscription?.scheduled_pricing && <p className="text-sm font-semibold text-primary">Cette modification remplacera les conditions déjà programmées.</p>}
    <fieldset disabled={busy} className="space-y-4 disabled:opacity-70">
      <div className="grid gap-4 sm:grid-cols-2">
        <label className="text-sm font-semibold">Périodicité
          <select className={control} value={input.billing_interval} onChange={(e) => change({ billing_interval: e.target.value as SubscriptionPricingInput['billing_interval'] })}>
            <option value="monthly">Mensuel</option><option value="annual">Annuel</option>
          </select>
        </label>
        <label className="text-sm font-semibold">Conditions tarifaires
          <select className={control} value={input.pricing_mode} onChange={(e) => {
            const mode = e.target.value as SubscriptionPricingInput['pricing_mode'];
            change({ pricing_mode: mode, discounts: mode === 'discounted' ? [newDiscount()] : [], free_months: null });
          }}>
            <option value="paid">Payant</option><option value="discounted">Avec remise</option><option value="free">Gratuit</option>
          </select>
        </label>
        <label className="text-sm font-semibold">{input.billing_interval === 'annual' ? 'Tarif annuel de base (TND)' : 'Tarif mensuel de base (TND)'}
          <input className={control} required type="number" min="0" max="9999999.999" step="0.001" value={input.base_price_tnd} onChange={(e) => change({ base_price_tnd: e.target.value })} />
        </label>
        {input.pricing_mode === 'free' && <label className="text-sm font-semibold">Durée de gratuité (mois)
          <input className={control} type="number" min="1" max="120" step="1" placeholder="Sans limite" value={input.free_months ?? ''} onChange={(e) => change({ free_months: e.target.value === '' ? null : Number(e.target.value) })} />
          <span className="mt-1 block text-xs font-normal text-muted">Vide : gratuité permanente. Sinon, retour au tarif de base à la fin de cette durée.</span>
        </label>}
      </div>
      {input.billing_interval === 'annual' && <p className="text-sm text-muted">Le tarif annuel est réparti sur 12 mois pour calculer les remises. Le total net couvre une année ; il est réglé manuellement.</p>}
      {input.pricing_mode === 'discounted' && <section className="space-y-3" aria-label="Remises par mois">
        <h3 className="font-bold">Remises par mois</h3>
        <p className="text-xs text-muted">Le mois 1 commence à la date d’effet. Les plages ne se cumulent pas et ne se répètent pas chaque année.</p>
        {input.discounts.map((discount, index) => <div key={index} className="grid gap-3 rounded-md border border-line p-3 sm:grid-cols-4">
          <label className="text-xs font-semibold">Du mois
            <input aria-label={`Du mois — remise ${index + 1}`} className={control} type="number" required min="1" max="120" value={discount.from_month} onChange={(e) => changeDiscount(index, { from_month: Number(e.target.value) })} />
          </label>
          <label className="text-xs font-semibold">Au mois
            <input aria-label={`Au mois — remise ${index + 1}`} className={control} type="number" required min="1" max="120" value={discount.to_month} onChange={(e) => changeDiscount(index, { to_month: Number(e.target.value) })} />
          </label>
          <label className="text-xs font-semibold">Type
            <select aria-label={`Type — remise ${index + 1}`} className={control} value={discount.type} onChange={(e) => changeDiscount(index, { type: e.target.value as SubscriptionDiscount['type'] })}>
              <option value="percentage">Pourcentage (%)</option><option value="fixed">Montant (TND/mois)</option>
            </select>
          </label>
          <label className="text-xs font-semibold">Valeur
            <input aria-label={`Valeur — remise ${index + 1}`} className={control} type="number" required min="0.001" max={discount.type === 'percentage' ? '100' : '9999999.999'} step="0.001" value={discount.value} onChange={(e) => changeDiscount(index, { value: e.target.value })} />
          </label>
          <button type="button" onClick={() => change({ discounts: input.discounts.filter((_, i) => i !== index) })} className="min-h-[44px] text-start text-sm text-status-cancel sm:col-span-4">Supprimer la remise {index + 1}</button>
        </div>)}
        <Button type="button" variant="ghost" size="md" disabled={input.discounts.length >= 120} onClick={() => {
          const month = Math.min(120, Math.max(0, ...input.discounts.map((row) => row.to_month)) + 1);
          change({ discounts: [...input.discounts, { ...newDiscount(), from_month: month, to_month: month }] });
        }}>Ajouter une remise</Button>
      </section>}
      <label className="block text-sm font-semibold">Motif de la modification
        <input className={control} required maxLength={500} value={input.reason} onChange={(e) => change({ reason: e.target.value })} placeholder="Ex. offre de lancement, geste commercial…" />
      </label>
    </fieldset>
    {error && <p role="alert" className="rounded-md bg-status-cancel-bg p-3 text-sm text-status-cancel">{error}</p>}
    <Button type="submit" variant="ghost" size="md" disabled={busy}>Calculer l’aperçu</Button>
    {preview && previewKey === formKey && <section aria-label="Aperçu des conditions" className="space-y-3 rounded-md border border-line p-4">
      <h3 className="font-bold">Aperçu de la première période</h3>
      <SubscriptionPricingSummary subscription={preview} />
    </section>}
    <div className="flex flex-wrap gap-3 border-t border-line pt-4">
      <Button type="button" size="md" disabled={busy || !preview || previewKey !== formKey} onClick={() => void save()}>{subscription ? 'Programmer les conditions' : 'Créer l’abonnement'}</Button>
      <Button type="button" variant="ghost" size="md" disabled={busy} onClick={onCancel}>Annuler</Button>
      {busy && <p role="status" className="self-center text-sm text-muted">Traitement en cours…</p>}
    </div>
  </form>;
}
