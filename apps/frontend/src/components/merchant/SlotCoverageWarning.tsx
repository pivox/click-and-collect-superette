'use client';

import Link from 'next/link';
import { useState } from 'react';
import type { MerchantPickupSlot } from '@/lib/types/merchant-slots.types';

const SIX_DAYS_MS = 6 * 24 * 60 * 60 * 1000;

function hasSlotWithinSixDays(slots: MerchantPickupSlot[]): boolean {
  const cutoff = Date.now() + SIX_DAYS_MS;
  return slots.some((s) => new Date(s.starts_at).getTime() <= cutoff);
}

export interface SlotCoverageWarningProps {
  slots: MerchantPickupSlot[];
}

export function SlotCoverageWarning({ slots }: SlotCoverageWarningProps) {
  const [dismissed, setDismissed] = useState(false);

  if (dismissed || hasSlotWithinSixDays(slots)) {
    return null;
  }

  return (
    <div
      role="alert"
      className="rounded-lg border border-warning/40 bg-warning/10 px-4 py-3 text-sm text-warning-dark"
    >
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="font-semibold">
            ⚠ Aucun créneau disponible dans les 6 prochains jours.
          </p>
          <p className="mt-0.5 text-xs">
            Vos clients ne pourront pas passer de commande.{' '}
            <Link href="/merchant/creneaux" className="font-bold underline hover:no-underline">
              Aller dans Créneaux
            </Link>{' '}
            pour générer 1 ou 3 mois de créneaux.
          </p>
        </div>
        <button
          type="button"
          aria-label="Fermer l'alerte"
          onClick={() => setDismissed(true)}
          className="shrink-0 text-warning-dark/60 hover:text-warning-dark"
        >
          ×
        </button>
      </div>
    </div>
  );
}
