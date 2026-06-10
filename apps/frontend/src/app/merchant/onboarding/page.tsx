'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { CheckCircle2, Circle } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import {
  getMerchantOnboarding,
  completeMerchantOnboarding,
  type OnboardingStatus,
} from '@/lib/services/merchant-onboarding.service';

const STEP_LINKS: Record<string, string> = {
  store_profile: '/merchant/',
  qr_code:       '/merchant/qr-code',
  catalog:       '/merchant/catalogue',
  opening_hours: '/merchant/creneaux',
  pickup_slots:  '/merchant/creneaux',
};

export default function MerchantOnboardingPage() {
  const [status, setStatus] = useState<OnboardingStatus | null>(null);
  const [completing, setCompleting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      setStatus(await getMerchantOnboarding());
    } catch {
      setError('Impossible de charger le guide de démarrage.');
    }
  }, []);

  useEffect(() => { void load(); }, [load]);

  const handleComplete = async () => {
    setCompleting(true);
    setError(null);
    try {
      await completeMerchantOnboarding();
      setStatus((prev) => prev ? { ...prev, is_complete: true, completion_percentage: 100 } : prev);
    } catch {
      setError("Impossible de marquer l'onboarding comme terminé. Réessaie.");
    } finally {
      setCompleting(false);
    }
  };

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <div>
        <p className="text-sm font-bold text-primary">Supérette</p>
        <h1 className="text-2xl font-black text-ink">Guide de démarrage</h1>
        <p className="mt-1 text-sm text-muted">
          Suivez ces étapes pour être opérationnel et recevoir vos premières commandes.
        </p>
      </div>

      {error && (
        <div role="alert" className="rounded px-4 py-3 text-sm text-red-600">{error}</div>
      )}

      {status && (
        <>
          <div>
            <div className="mb-1 flex items-center justify-between text-sm">
              <span className="font-extrabold">Progression</span>
              <span className="font-extrabold text-primary">{status.completion_percentage}%</span>
            </div>
            <div className="h-2 w-full overflow-hidden rounded-full bg-soft">
              <div
                className="h-full rounded-full bg-primary transition-all"
                style={{ width: `${status.completion_percentage}%` }}
              />
            </div>
          </div>

          <div className="space-y-2">
            {status.steps.map((step) => {
              const href = STEP_LINKS[step.key];
              return (
                <div
                  key={step.key}
                  className="flex items-center justify-between gap-3 rounded-lg border border-line bg-card px-4 py-3"
                >
                  <div className="flex items-center gap-3">
                    {step.done ? (
                      <CheckCircle2 className="h-5 w-5 shrink-0 text-green-600" aria-hidden="true" />
                    ) : (
                      <Circle className="h-5 w-5 shrink-0 text-muted" aria-hidden="true" />
                    )}
                    <span className={step.done ? 'text-sm text-muted line-through' : 'text-sm font-extrabold'}>
                      {step.label}
                    </span>
                  </div>
                  {!step.done && href && (
                    <Link
                      href={href}
                      className="shrink-0 rounded-md bg-soft px-3 py-1.5 text-xs font-extrabold hover:bg-primary/10"
                    >
                      Aller →
                    </Link>
                  )}
                </div>
              );
            })}
          </div>

          {!status.is_complete && (
            <Button
              type="button"
              variant="ghost"
              size="md"
              disabled={completing}
              onClick={() => void handleComplete()}
            >
              {completing ? 'En cours…' : "Marquer comme terminé"}
            </Button>
          )}

          {status.is_complete && (
            <div className="rounded-md bg-green-50 px-4 py-3 text-sm font-bold text-green-700">
              Onboarding complété — votre supérette est prête à recevoir des commandes.
            </div>
          )}
        </>
      )}
    </div>
  );
}
