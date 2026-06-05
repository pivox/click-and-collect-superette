'use client';

import { AlertTriangle, CheckCircle, X } from 'lucide-react';
import type { StoreActivationChecklist } from '@/lib/types/admin/stores.types';

interface ActivationChecklistDialogProps {
  open: boolean;
  storeName: string;
  checklist: StoreActivationChecklist | null;
  isLoading: boolean;
  error: string | null;
  onClose: () => void;
}

export function ActivationChecklistDialog({
  open,
  storeName,
  checklist,
  isLoading,
  error,
  onClose,
}: ActivationChecklistDialogProps) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex justify-end">
      <div className="absolute inset-0 bg-black/40" onClick={onClose} />
      <aside
        role="dialog"
        aria-modal="true"
        aria-labelledby="activation-checklist-title"
        className="relative flex h-full w-full max-w-xl flex-col bg-card shadow-floating"
      >
        <div className="flex items-start justify-between gap-4 border-b border-line px-6 py-4">
          <div>
            <h2 id="activation-checklist-title" className="font-black text-ink">
              {"Checklist d'activation"}
            </h2>
            {storeName && <p className="mt-1 text-sm text-muted">{storeName}</p>}
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Fermer"
            className="rounded p-1 text-muted hover:bg-soft hover:text-ink"
          >
            <X className="h-4 w-4" aria-hidden="true" />
          </button>
        </div>
        <div className="flex-1 overflow-y-auto px-6 py-5">
          {isLoading && (
            <div className="rounded-md border border-line bg-soft px-4 py-3 text-sm text-muted">
              Chargement de la checklist...
            </div>
          )}
          {error && (
            <div className="rounded-md bg-status-cancel-bg px-4 py-3 text-sm font-medium text-status-cancel">
              {error}
            </div>
          )}
          {!isLoading && !error && checklist && (
            <div className="space-y-5">
              <div className="rounded-md border border-line bg-soft px-4 py-3">
                <div className="flex flex-wrap items-center gap-2">
                  <span
                    className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ${
                      checklist.ready ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700'
                    }`}
                  >
                    {checklist.ready ? (
                      <CheckCircle className="h-3.5 w-3.5" aria-hidden="true" />
                    ) : (
                      <AlertTriangle className="h-3.5 w-3.5" aria-hidden="true" />
                    )}
                    Statut global : {checklist.ready ? 'Prête' : 'Incomplète'}
                  </span>
                  <span className="text-sm font-semibold text-ink">
                    {checklist.required_completed_count}/{checklist.required_total_count} critères obligatoires
                  </span>
                </div>
                <p className="mt-3 text-sm text-muted">
                  {"Cette checklist aide à décider l'activation bêta ; elle ne bloque pas automatiquement l'activation."}
                </p>
              </div>
              <div className="space-y-3">
                {checklist.steps.map((step) => (
                  <div key={step.key} className="rounded-md border border-line px-4 py-3">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <div className="font-semibold text-ink">{step.label}</div>
                        <div className="mt-1 flex flex-wrap gap-1.5">
                          <span
                            className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                              step.required ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-gray-600'
                            }`}
                          >
                            {step.required ? 'Obligatoire' : 'Optionnel'}
                          </span>
                          <span
                            className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                              step.completed ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700'
                            }`}
                          >
                            {step.completed ? 'Complété' : 'Manquant'}
                          </span>
                        </div>
                      </div>
                      {hasStepValue(step) && (
                        <div className="rounded-md bg-soft px-2 py-1 text-sm font-semibold text-ink">
                          {formatStepValue(step.current_value)} / {formatStepValue(step.target_value)}
                        </div>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </aside>
    </div>
  );
}

function hasStepValue(step: StoreActivationChecklist['steps'][number]) {
  return step.current_value !== undefined || step.target_value !== undefined;
}

function formatStepValue(value: number | null | undefined) {
  return value ?? '-';
}
