'use client';

import { useState } from 'react';
import { Zap } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import type { GenerateSlotsResult } from '@/lib/types/merchant-slots.types';

export interface GenerateBannerProps {
  onGenerate: () => Promise<GenerateSlotsResult>;
  onDismiss: () => void;
}

export function GenerateBanner({ onGenerate, onDismiss }: GenerateBannerProps) {
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<GenerateSlotsResult | null>(null);
  const [error, setError] = useState<string | null>(null);

  const now = new Date();
  const horizon = new Date(now);
  horizon.setDate(horizon.getDate() + 28);
  const fmt = (d: Date) =>
    d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' });

  async function handleGenerate() {
    setLoading(true);
    setError(null);
    try {
      const data = await onGenerate();
      setResult(data);
    } catch {
      setError('La génération a échoué. Réessayez.');
    } finally {
      setLoading(false);
    }
  }

  if (result) {
    return (
      <div
        role="status"
        className="flex items-center justify-between rounded-lg border border-primary/30 bg-[#eff8f1] px-4 py-3 text-sm"
      >
        <span className="font-bold text-primary">
          {result.generated_count} créneau{result.generated_count > 1 ? 'x' : ''} généré
          {result.generated_count > 1 ? 's' : ''}.
        </span>
        <button
          type="button"
          onClick={onDismiss}
          className="ml-4 text-xs text-muted hover:underline"
        >
          Fermer
        </button>
      </div>
    );
  }

  return (
    <div className="rounded-lg border border-secondary bg-[#fff8ed] px-4 py-3">
      <div className="flex items-start gap-3">
        <Zap className="mt-0.5 h-4 w-4 shrink-0 text-[#a06000]" aria-hidden="true" />
        <div className="flex-1">
          <p className="text-sm font-bold text-ink">
            Règle créée. Générer les créneaux pour les 4 prochaines semaines ?
          </p>
          <p className="mt-0.5 text-xs text-muted">
            Période : du {fmt(now)} au {fmt(horizon)}
          </p>
          {error && <p role="alert" className="mt-1 text-xs text-danger">{error}</p>}
          <div className="mt-3 flex items-center gap-2">
            <Button size="md" onClick={handleGenerate} disabled={loading}>
              {loading ? 'Génération…' : 'Générer'}
            </Button>
            <Button size="md" variant="ghost" onClick={onDismiss} disabled={loading}>
              Plus tard
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
