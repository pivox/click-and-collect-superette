'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import type { CreateClosurePayload } from '@/lib/types/merchant-slots.types';

export interface ClosureFormProps {
  onSubmit: (payload: CreateClosurePayload) => Promise<void>;
  onCancel: () => void;
}

export function ClosureForm({ onSubmit, onCancel }: ClosureFormProps) {
  const [startsAt, setStartsAt] = useState('');
  const [endsAt, setEndsAt] = useState('');
  const [reason, setReason] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!startsAt || !endsAt) {
      setError('Les dates de début et de fin sont obligatoires.');
      return;
    }
    if (startsAt >= endsAt) {
      setError('La date de fin doit être après la date de début.');
      return;
    }
    setError(null);
    setSaving(true);
    try {
      await onSubmit({
        starts_at: new Date(startsAt).toISOString(),
        ends_at: new Date(endsAt).toISOString(),
        ...(reason.trim() ? { reason: reason.trim() } : {}),
      });
    } catch {
      setError("Impossible de créer la fermeture. Réessayez.");
      setSaving(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="mt-3 space-y-3 rounded-lg border border-line bg-soft p-3">
      <div className="flex gap-3">
        <div className="flex-1">
          <label className="mb-1 block text-xs font-bold text-muted" htmlFor="closure-start">
            Début
          </label>
          <input
            id="closure-start"
            type="datetime-local"
            value={startsAt}
            onChange={(e) => setStartsAt(e.target.value)}
            required
            className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none"
          />
        </div>
        <div className="flex-1">
          <label className="mb-1 block text-xs font-bold text-muted" htmlFor="closure-end">
            Fin
          </label>
          <input
            id="closure-end"
            type="datetime-local"
            value={endsAt}
            onChange={(e) => setEndsAt(e.target.value)}
            required
            className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none"
          />
        </div>
      </div>
      <div>
        <label className="mb-1 block text-xs font-bold text-muted" htmlFor="closure-reason">
          Raison (optionnelle)
        </label>
        <input
          id="closure-reason"
          type="text"
          maxLength={255}
          placeholder="ex. Aïd el-Fitr, congé annuel…"
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none placeholder:text-muted"
        />
      </div>
      {error && <p role="alert" className="text-xs text-danger">{error}</p>}
      <div className="flex gap-2">
        <Button type="submit" disabled={saving}>
          {saving ? 'Enregistrement…' : 'Ajouter la fermeture'}
        </Button>
        <Button type="button" variant="ghost" onClick={onCancel}>
          Annuler
        </Button>
      </div>
    </form>
  );
}
