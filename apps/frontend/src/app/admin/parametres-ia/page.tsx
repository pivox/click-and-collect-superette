'use client';

import { useCallback, useEffect, useState } from 'react';
import { Save } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import {
  getAdminAiBudgetPolicy,
  updateAdminAiBudgetPolicy,
} from '@/lib/services/admin/aiBudgetPolicy.service';
import type { AdminAiBudgetPolicy } from '@/lib/types/admin/aiBudgetPolicy.types';

const EMPTY_POLICY: AdminAiBudgetPolicy = {
  enabled: false,
  openai_enabled: false,
  gemini_enabled: false,
  mistral_enabled: false,
  qwen_enabled: false,
  currency: 'USD',
  global_period_days: 30,
  alert_threshold_percent: 80,
  max_cost_per_call_amount: null,
  max_cost_per_photo_amount: null,
  max_cost_per_shop_amount: null,
  global_period_cap_amount: null,
  max_output_tokens_per_call: null,
  max_crops_per_photo: null,
  max_attempts_per_photo: null,
  max_call_duration_seconds: null,
  max_concurrent_calls: null,
  updated_at: '',
};

type ToggleKey = 'enabled' | 'openai_enabled' | 'gemini_enabled' | 'mistral_enabled' | 'qwen_enabled';
type AmountKey =
  | 'max_cost_per_call_amount'
  | 'max_cost_per_photo_amount'
  | 'max_cost_per_shop_amount'
  | 'global_period_cap_amount';
type CountKey =
  | 'max_output_tokens_per_call'
  | 'max_crops_per_photo'
  | 'max_attempts_per_photo'
  | 'max_call_duration_seconds'
  | 'max_concurrent_calls';

export default function AdminAiBudgetPolicyPage() {
  const [policy, setPolicy] = useState<AdminAiBudgetPolicy>(EMPTY_POLICY);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [hasLoaded, setHasLoaded] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  const load = useCallback(() => {
    setIsLoading(true);
    setError(null);
    setSaved(false);
    setHasLoaded(false);
    void getAdminAiBudgetPolicy()
      .then((data) => {
        setPolicy(data);
        setHasLoaded(true);
      })
      .catch((err: unknown) => {
        console.error('[admin-ai-budget-policy] load failed', err);
        setError('Impossible de charger les paramètres IA.');
      })
      .finally(() => setIsLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const toggle = (key: ToggleKey) => {
    setSaved(false);
    setPolicy((current) => ({ ...current, [key]: !current[key] }));
  };

  const setAmount = (key: AmountKey, value: string) => {
    setSaved(false);
    setPolicy((current) => ({ ...current, [key]: value === '' ? null : value }));
  };

  const setCount = (key: CountKey, value: string) => {
    setSaved(false);
    setPolicy((current) => ({ ...current, [key]: value === '' ? null : Number(value) }));
  };

  const save = async () => {
    if (!hasLoaded) return;

    setIsSaving(true);
    setError(null);
    setSaved(false);
    try {
      const updated = await updateAdminAiBudgetPolicy({
        enabled: policy.enabled,
        openai_enabled: policy.openai_enabled,
        gemini_enabled: policy.gemini_enabled,
        mistral_enabled: policy.mistral_enabled,
        qwen_enabled: policy.qwen_enabled,
        currency: policy.currency,
        global_period_days: policy.global_period_days,
        alert_threshold_percent: policy.alert_threshold_percent,
        max_cost_per_call_amount: policy.max_cost_per_call_amount,
        max_cost_per_photo_amount: policy.max_cost_per_photo_amount,
        max_cost_per_shop_amount: policy.max_cost_per_shop_amount,
        global_period_cap_amount: policy.global_period_cap_amount,
        max_output_tokens_per_call: policy.max_output_tokens_per_call,
        max_crops_per_photo: policy.max_crops_per_photo,
        max_attempts_per_photo: policy.max_attempts_per_photo,
        max_call_duration_seconds: policy.max_call_duration_seconds,
        max_concurrent_calls: policy.max_concurrent_calls,
      });
      setPolicy(updated);
      setSaved(true);
    } catch (err) {
      console.error('[admin-ai-budget-policy] save failed', err);
      setError('Impossible d’enregistrer les paramètres IA.');
    } finally {
      setIsSaving(false);
    }
  };

  const canEdit = hasLoaded && !isLoading && !isSaving;

  return (
    <div className="max-w-4xl">
      <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-h1 font-black">Paramètres IA</h1>
          <p className="mt-1 text-sm text-muted">
            Garde-fous budgétaires du pilote d’import catalogue par photo (CATALOG-AI). Tant
            qu’aucune limite n’est configurée et activée ici, aucun appel fournisseur payant ne
            peut démarrer.
          </p>
        </div>
        <Button variant="primary" size="md" disabled={!canEdit} onClick={() => void save()}>
          <Save className="h-4 w-4" aria-hidden="true" />
          Enregistrer
        </Button>
      </div>

      {error && (
        <div role="alert" className="mb-4 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          {error}
        </div>
      )}
      {isLoading && (
        <div className="mb-4 rounded-md border border-line bg-soft px-4 py-2 text-sm font-semibold text-muted">
          Chargement des paramètres IA...
        </div>
      )}
      {saved && (
        <div className="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm font-semibold text-green-800">
          Paramètres IA enregistrés.
        </div>
      )}

      <section className="rounded-md border border-line bg-card p-4">
        <Toggle
          label="Autoriser les appels IA payants"
          description="Coupe-circuit global. Désactivé par défaut : aucun fournisseur ne peut être sollicité."
          checked={policy.enabled}
          disabled={!canEdit}
          onChange={() => toggle('enabled')}
        />

        <div className="mt-4 border-t border-line pt-4">
          <h2 className="text-sm font-black text-ink">Fournisseurs autorisés</h2>
          <div className="mt-3 grid gap-3 md:grid-cols-4">
            <Toggle label="OpenAI" checked={policy.openai_enabled} disabled={!canEdit} onChange={() => toggle('openai_enabled')} />
            <Toggle label="Gemini" checked={policy.gemini_enabled} disabled={!canEdit} onChange={() => toggle('gemini_enabled')} />
            <Toggle label="Mistral" checked={policy.mistral_enabled} disabled={!canEdit} onChange={() => toggle('mistral_enabled')} />
            <Toggle label="Qwen" checked={policy.qwen_enabled} disabled={!canEdit} onChange={() => toggle('qwen_enabled')} />
          </div>
        </div>

        <div className="mt-4 border-t border-line pt-4">
          <h2 className="text-sm font-black text-ink">Devise et plafonds de coût</h2>
          <p className="mt-1 text-xs text-muted">
            Devise d’origine des fournisseurs (jamais convertie automatiquement en TND). Un
            plafond laissé vide signifie « non borné » : le combiner avec le coupe-circuit
            ci-dessus avant toute activation réelle.
          </p>
          <div className="mt-3 grid gap-3 md:grid-cols-3">
            <TextField label="Devise (ISO 4217)" value={policy.currency} disabled={!canEdit} maxLength={3}
              onChange={(value) => setPolicy((current) => ({ ...current, currency: value.toUpperCase() }))} />
            <AmountField label="Plafond par appel" value={policy.max_cost_per_call_amount} disabled={!canEdit}
              onChange={(value) => setAmount('max_cost_per_call_amount', value)} />
            <AmountField label="Plafond par photo" value={policy.max_cost_per_photo_amount} disabled={!canEdit}
              onChange={(value) => setAmount('max_cost_per_photo_amount', value)} />
            <AmountField label="Plafond par supérette" value={policy.max_cost_per_shop_amount} disabled={!canEdit}
              onChange={(value) => setAmount('max_cost_per_shop_amount', value)} />
            <AmountField label="Plafond global périodique" value={policy.global_period_cap_amount} disabled={!canEdit}
              onChange={(value) => setAmount('global_period_cap_amount', value)} />
            <CountField label="Période du plafond global (jours)" value={policy.global_period_days} disabled={!canEdit}
              onChange={(value) => setPolicy((current) => ({ ...current, global_period_days: Number(value || 1) }))} />
          </div>
        </div>

        <div className="mt-4 border-t border-line pt-4">
          <h2 className="text-sm font-black text-ink">Bornes techniques</h2>
          <p className="mt-1 text-xs text-muted">Vide = non borné pour ce champ.</p>
          <div className="mt-3 grid gap-3 md:grid-cols-3">
            <CountField label="Tokens de sortie max / appel" value={policy.max_output_tokens_per_call} disabled={!canEdit}
              onChange={(value) => setCount('max_output_tokens_per_call', value)} />
            <CountField label="Recadrages max / photo" value={policy.max_crops_per_photo} disabled={!canEdit}
              onChange={(value) => setCount('max_crops_per_photo', value)} />
            <CountField label="Tentatives max / photo" value={policy.max_attempts_per_photo} disabled={!canEdit}
              onChange={(value) => setCount('max_attempts_per_photo', value)} />
            <CountField label="Durée max / appel (s)" value={policy.max_call_duration_seconds} disabled={!canEdit}
              onChange={(value) => setCount('max_call_duration_seconds', value)} />
            <CountField label="Appels concurrents max" value={policy.max_concurrent_calls} disabled={!canEdit}
              onChange={(value) => setCount('max_concurrent_calls', value)} />
          </div>
        </div>

        <div className="mt-4 border-t border-line pt-4">
          <h2 className="text-sm font-black text-ink">Alerte</h2>
          <div className="mt-3 grid gap-3 md:grid-cols-3">
            <CountField label="Seuil d’alerte (% du plafond)" value={policy.alert_threshold_percent} disabled={!canEdit}
              onChange={(value) => setPolicy((current) => ({ ...current, alert_threshold_percent: Number(value || 1) }))} />
          </div>
        </div>
      </section>

      <p className="mt-4 text-xs text-muted">
        Cet écran configure uniquement les garde-fous (issue CATALOG-AI-007). Le journal de
        consommation réel, la réservation par appel et le tableau de coûts par fournisseur
        nécessitent les fondations CATALOG-AI-001/003/004, pas encore livrées.
      </p>
    </div>
  );
}

function Toggle({
  label,
  description,
  checked,
  disabled = false,
  onChange,
}: {
  label: string;
  description?: string;
  checked: boolean;
  disabled?: boolean;
  onChange: () => void;
}) {
  return (
    <label className="flex min-h-16 items-start justify-between gap-3 rounded-md border border-line bg-soft px-3 py-3">
      <span>
        <span className="block text-sm font-black text-ink">{label}</span>
        {description && <span className="mt-1 block text-xs text-muted">{description}</span>}
      </span>
      <input
        aria-label={label}
        type="checkbox"
        checked={checked}
        disabled={disabled}
        onChange={onChange}
        className="mt-1 h-5 w-5 rounded border-line text-primary"
      />
    </label>
  );
}

function TextField({
  label,
  value,
  disabled,
  maxLength,
  onChange,
}: {
  label: string;
  value: string;
  disabled: boolean;
  maxLength?: number;
  onChange: (value: string) => void;
}) {
  return (
    <label className="block text-sm">
      <span className="mb-1 block font-semibold text-ink">{label}</span>
      <input
        type="text"
        value={value}
        disabled={disabled}
        maxLength={maxLength}
        onChange={(event) => onChange(event.target.value)}
        className="w-full rounded-md border border-line px-3 py-2 text-sm"
      />
    </label>
  );
}

function AmountField({
  label,
  value,
  disabled,
  onChange,
}: {
  label: string;
  value: string | null;
  disabled: boolean;
  onChange: (value: string) => void;
}) {
  return (
    <label className="block text-sm">
      <span className="mb-1 block font-semibold text-ink">{label}</span>
      <input
        type="text"
        inputMode="decimal"
        placeholder="non borné"
        value={value ?? ''}
        disabled={disabled}
        onChange={(event) => onChange(event.target.value)}
        className="w-full rounded-md border border-line px-3 py-2 text-sm"
      />
    </label>
  );
}

function CountField({
  label,
  value,
  disabled,
  onChange,
}: {
  label: string;
  value: number | null;
  disabled: boolean;
  onChange: (value: string) => void;
}) {
  return (
    <label className="block text-sm">
      <span className="mb-1 block font-semibold text-ink">{label}</span>
      <input
        type="number"
        min={0}
        placeholder="non borné"
        value={value ?? ''}
        disabled={disabled}
        onChange={(event) => onChange(event.target.value)}
        className="w-full rounded-md border border-line px-3 py-2 text-sm"
      />
    </label>
  );
}
