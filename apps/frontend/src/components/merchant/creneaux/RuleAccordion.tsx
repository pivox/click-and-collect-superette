'use client';

import { useRef, useState } from 'react';
import { ChevronDown, ChevronUp, Plus, Trash2 } from 'lucide-react';
import { RuleForm } from './RuleForm';
import type {
  CreateSlotRulePayload,
  GenerateSlotsResult,
  MerchantPickupSlotRule,
} from '@/lib/types/merchant-slots.types';

const formatGenerationDate = (iso: string) => new Date(iso).toLocaleDateString('fr-FR', {
  day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'Africa/Tunis',
});

const WEEKDAY_LABELS: Record<number, string> = {
  1: 'Lundi', 2: 'Mardi', 3: 'Mercredi', 4: 'Jeudi',
  5: 'Vendredi', 6: 'Samedi', 7: 'Dimanche',
};

export interface RuleAccordionProps {
  rules: MerchantPickupSlotRule[];
  onCreateRule: (payload: CreateSlotRulePayload) => Promise<void>;
  onDeleteRule: (ruleId: string) => Promise<void>;
  onGenerate: (horizonMonths: 1 | 3) => Promise<GenerateSlotsResult>;
}

export function RuleAccordion({ rules, onCreateRule, onDeleteRule, onGenerate }: RuleAccordionProps) {
  const [open, setOpen] = useState(rules.length === 0);
  const [showForm, setShowForm] = useState(false);
  const [deletingId, setDeletingId] = useState<string | null>(null);
  const [confirmId, setConfirmId] = useState<string | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [generating, setGenerating] = useState<1 | 3 | null>(null);
  const [generateResult, setGenerateResult] = useState<GenerateSlotsResult | null>(null);
  const [generateError, setGenerateError] = useState<string | null>(null);
  const generationLock = useRef(false);
  const deletionLock = useRef(false);
  const hasActiveRules = rules.some((rule) => rule.is_active);

  async function handleCreate(payload: CreateSlotRulePayload) {
    await onCreateRule(payload);
  }

  async function handleDelete(ruleId: string) {
    if (deletionLock.current) return;
    deletionLock.current = true;
    setDeletingId(ruleId);
    setDeleteError(null);
    try {
      await onDeleteRule(ruleId);
    } catch {
      setDeleteError('Impossible de désactiver cette règle.');
    } finally {
      deletionLock.current = false;
      setDeletingId(null);
      setConfirmId(null);
    }
  }

  async function handleGenerate(horizonMonths: 1 | 3) {
    if (generationLock.current || !hasActiveRules) return;
    generationLock.current = true;
    setGenerating(horizonMonths);
    setGenerateResult(null);
    setGenerateError(null);
    try {
      const result = await onGenerate(horizonMonths);
      setGenerateResult(result);
    } catch {
      setGenerateError('Impossible de générer les créneaux. Réessayez.');
    } finally {
      generationLock.current = false;
      setGenerating(null);
    }
  }

  return (
    <section className="rounded-lg border border-line bg-card">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="flex w-full items-center justify-between px-4 py-3 text-left"
        aria-expanded={open}
        aria-controls="rule-accordion-panel"
      >
        <span className="font-bold">Règles récurrentes</span>
        {open ? <ChevronUp className="h-4 w-4 text-muted" /> : <ChevronDown className="h-4 w-4 text-muted" />}
      </button>

      {open && (
        <div id="rule-accordion-panel" className="border-t border-line px-4 pb-4 pt-3">
          {rules.length === 0 && !showForm && (
            <p className="mb-3 text-sm text-muted">
              Aucune règle — les règles définissent les créneaux récurrents de votre supérette.
            </p>
          )}

          <ul className="space-y-2">
            {rules.map((rule) => (
              <li key={rule.id} className="flex items-center justify-between rounded-lg bg-soft px-3 py-2 text-sm">
                <span>
                  <strong>{WEEKDAY_LABELS[rule.weekday]}</strong>{' '}
                  {rule.start_time}–{rule.end_time} · capacité {rule.capacity}
                  {!rule.is_active && (
                    <span className="ml-2 text-xs text-muted">(inactive)</span>
                  )}
                </span>
                {confirmId === rule.id ? (
                  <span className="flex items-center gap-2 text-xs">
                    Désactiver ?
                    <button
                      type="button"
                      onClick={() => handleDelete(rule.id)}
                      disabled={deletingId === rule.id}
                      className="font-bold text-danger hover:underline"
                    >
                      {deletingId === rule.id ? '…' : 'Oui'}
                    </button>
                    <button
                      type="button"
                      onClick={() => setConfirmId(null)}
                      className="text-muted hover:underline"
                    >
                      Non
                    </button>
                  </span>
                ) : (
                  <button
                    type="button"
                    disabled={!rule.is_active || deletingId !== null}
                    aria-label={`Désactiver la règle ${WEEKDAY_LABELS[rule.weekday]} ${rule.start_time}`}
                    onClick={() => setConfirmId(rule.id)}
                    className="rounded p-1 text-muted hover:bg-soft hover:text-danger"
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                  </button>
                )}
              </li>
            ))}
          </ul>

          {confirmId && (
            <p className="mt-2 text-xs text-muted">
              La règle ne participera plus aux prochaines générations. Les rendez-vous existants sont conservés.
            </p>
          )}

          {deleteError && (
            <p role="alert" aria-atomic="true" className="mt-2 text-xs text-danger">{deleteError}</p>
          )}

          {showForm ? (
            <RuleForm
              onSubmit={handleCreate}
              onCancel={() => setShowForm(false)}
              onComplete={() => setShowForm(false)}
            />
          ) : (
            <button
              type="button"
              onClick={() => setShowForm(true)}
              className="mt-3 flex items-center gap-1.5 text-sm font-bold text-primary hover:underline"
            >
              <Plus className="h-4 w-4" />
              Nouvelle règle
            </button>
          )}

          {rules.length > 0 && (
            <div className="mt-4 border-t border-line pt-4">
              <p className="mb-2 text-xs font-bold text-muted">Générer les créneaux</p>
              {!hasActiveRules && <p className="mb-2 text-xs text-muted">Ajoutez une règle active pour générer des créneaux.</p>}
              <div className="flex flex-wrap gap-2">
                <button
                  type="button"
                  onClick={() => handleGenerate(1)}
                  disabled={generating !== null || !hasActiveRules}
                  className="rounded-md border border-line bg-white px-3 py-1.5 text-xs font-semibold hover:bg-soft disabled:opacity-50"
                >
                  {generating === 1 ? 'Génération…' : 'Générer 1 mois'}
                </button>
                <button
                  type="button"
                  onClick={() => handleGenerate(3)}
                  disabled={generating !== null || !hasActiveRules}
                  className="rounded-md border border-line bg-white px-3 py-1.5 text-xs font-semibold hover:bg-soft disabled:opacity-50"
                >
                  {generating === 3 ? 'Génération…' : 'Générer 3 mois'}
                </button>
              </div>
              {generateResult && (
                <div role="status" className="mt-2 text-xs text-success">
                  <p>✓ {generateResult.generated_count} créneau{generateResult.generated_count !== 1 ? 'x' : ''} généré{generateResult.generated_count !== 1 ? 's' : ''}.</p>
                  <p>{generateResult.skipped_existing_count} existants ignorés · {generateResult.skipped_closure_count} fermetures ignorées.</p>
                  <p>Du {formatGenerationDate(generateResult.horizon_start)} au {formatGenerationDate(generateResult.horizon_end)} exclu, heure de Tunis.</p>
                  {generateResult.generated_count === 0 && <p>Aucun nouveau créneau : les plages sont déjà couvertes, passées ou exclues par une fermeture.</p>}
                </div>
              )}
              {generateError && (
                <p role="alert" aria-atomic="true" className="mt-2 text-xs text-danger">{generateError}</p>
              )}
            </div>
          )}
        </div>
      )}
    </section>
  );
}
