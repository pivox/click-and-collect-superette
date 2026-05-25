'use client';

import { useState } from 'react';
import { ChevronDown, ChevronUp, Plus, Trash2 } from 'lucide-react';
import { ClosureForm } from './ClosureForm';
import type { CreateClosurePayload, MerchantExceptionalClosure } from '@/lib/types/merchant-slots.types';

function formatClosureRange(closure: MerchantExceptionalClosure): string {
  const fmt = (iso: string) =>
    new Date(iso).toLocaleString('fr-FR', {
      day: 'numeric',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    });
  return `${fmt(closure.starts_at)} → ${fmt(closure.ends_at)}`;
}

export interface ClosureAccordionProps {
  closures: MerchantExceptionalClosure[];
  onCreateClosure: (payload: CreateClosurePayload) => Promise<void>;
  onDeleteClosure: (closureId: string) => Promise<void>;
}

export function ClosureAccordion({
  closures,
  onCreateClosure,
  onDeleteClosure,
}: ClosureAccordionProps) {
  const [open, setOpen] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [deletingId, setDeletingId] = useState<string | null>(null);
  const [confirmId, setConfirmId] = useState<string | null>(null);

  async function handleCreate(payload: CreateClosurePayload) {
    await onCreateClosure(payload);
    setShowForm(false);
  }

  async function handleDelete(closureId: string) {
    setDeletingId(closureId);
    try {
      await onDeleteClosure(closureId);
    } finally {
      setDeletingId(null);
      setConfirmId(null);
    }
  }

  return (
    <section className="rounded-lg border border-line bg-card">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="flex w-full items-center justify-between px-4 py-3 text-left"
        aria-expanded={open}
      >
        <span className="font-bold">
          Fermetures exceptionnelles
          {closures.length > 0 && (
            <span className="ml-2 rounded-full bg-danger/10 px-2 py-0.5 text-xs font-black text-danger">
              {closures.length}
            </span>
          )}
        </span>
        {open ? <ChevronUp className="h-4 w-4 text-muted" /> : <ChevronDown className="h-4 w-4 text-muted" />}
      </button>

      {open && (
        <div className="border-t border-line px-4 pb-4 pt-3">
          {closures.length === 0 && !showForm && (
            <p className="mb-3 text-sm text-muted">
              Aucune fermeture exceptionnelle planifiée.
            </p>
          )}

          <ul className="space-y-2">
            {closures.map((closure) => (
              <li key={closure.id} className="flex items-start justify-between rounded-lg bg-soft px-3 py-2 text-sm">
                <div>
                  <p className="font-bold">{formatClosureRange(closure)}</p>
                  {closure.reason && (
                    <p className="text-xs text-muted">{closure.reason}</p>
                  )}
                </div>
                {confirmId === closure.id ? (
                  <span className="flex items-center gap-2 text-xs">
                    Supprimer ?
                    <button
                      type="button"
                      onClick={() => handleDelete(closure.id)}
                      disabled={deletingId === closure.id}
                      className="font-bold text-danger hover:underline"
                    >
                      {deletingId === closure.id ? '…' : 'Oui'}
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
                    aria-label="Supprimer cette fermeture"
                    onClick={() => setConfirmId(closure.id)}
                    className="ml-2 shrink-0 rounded p-1 text-muted hover:bg-soft hover:text-danger"
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                  </button>
                )}
              </li>
            ))}
          </ul>

          {showForm ? (
            <ClosureForm
              onSubmit={handleCreate}
              onCancel={() => setShowForm(false)}
            />
          ) : (
            <button
              type="button"
              onClick={() => setShowForm(true)}
              className="mt-3 flex items-center gap-1.5 text-sm font-bold text-primary hover:underline"
            >
              <Plus className="h-4 w-4" />
              Ajouter une fermeture
            </button>
          )}
        </div>
      )}
    </section>
  );
}
