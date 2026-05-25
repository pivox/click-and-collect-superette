'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import type { CreateSlotRulePayload } from '@/lib/types/merchant-slots.types';

const WEEKDAYS = [
  { value: 1, label: 'Lundi' },
  { value: 2, label: 'Mardi' },
  { value: 3, label: 'Mercredi' },
  { value: 4, label: 'Jeudi' },
  { value: 5, label: 'Vendredi' },
  { value: 6, label: 'Samedi' },
  { value: 7, label: 'Dimanche' },
];

export interface RuleFormProps {
  onSubmit: (payload: CreateSlotRulePayload) => Promise<void>;
  onCancel: () => void;
}

export function RuleForm({ onSubmit, onCancel }: RuleFormProps) {
  const [weekday, setWeekday] = useState('1');
  const [startTime, setStartTime] = useState('17:00');
  const [endTime, setEndTime] = useState('19:00');
  const [capacity, setCapacity] = useState('6');
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (startTime >= endTime) {
      setError("L'heure de fin doit être après l'heure de début.");
      return;
    }
    const cap = parseInt(capacity, 10);
    if (!cap || cap <= 0) {
      setError('La capacité doit être un nombre positif.');
      return;
    }
    setError(null);
    setSaving(true);
    try {
      await onSubmit({
        weekday: parseInt(weekday, 10),
        start_time: startTime,
        end_time: endTime,
        capacity: cap,
      });
    } catch {
      setError("Impossible de créer la règle. Vérifiez les données et réessayez.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="mt-3 space-y-3 rounded-lg border border-line bg-soft p-3">
      <div>
        <label className="mb-1 block text-xs font-bold text-muted" htmlFor="rule-weekday">
          Jour de la semaine
        </label>
        <select
          id="rule-weekday"
          value={weekday}
          onChange={(e) => setWeekday(e.target.value)}
          className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none"
        >
          {WEEKDAYS.map((d) => (
            <option key={d.value} value={d.value}>
              {d.label}
            </option>
          ))}
        </select>
      </div>
      <div className="flex gap-3">
        <div className="flex-1">
          <label className="mb-1 block text-xs font-bold text-muted" htmlFor="rule-start">
            Heure début
          </label>
          <input
            id="rule-start"
            type="time"
            value={startTime}
            onChange={(e) => setStartTime(e.target.value)}
            required
            className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none"
          />
        </div>
        <div className="flex-1">
          <label className="mb-1 block text-xs font-bold text-muted" htmlFor="rule-end">
            Heure fin
          </label>
          <input
            id="rule-end"
            type="time"
            value={endTime}
            onChange={(e) => setEndTime(e.target.value)}
            required
            className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none"
          />
        </div>
      </div>
      <div>
        <label className="mb-1 block text-xs font-bold text-muted" htmlFor="rule-cap">
          Capacité (nb. commandes max)
        </label>
        <input
          id="rule-cap"
          type="number"
          min={1}
          value={capacity}
          onChange={(e) => setCapacity(e.target.value)}
          required
          className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none"
        />
      </div>
      {error && <p role="alert" className="text-xs text-danger">{error}</p>}
      <div className="flex gap-2">
        <Button type="submit" disabled={saving}>
          {saving ? 'Création…' : 'Ajouter la règle'}
        </Button>
        <Button type="button" variant="ghost" onClick={onCancel}>
          Annuler
        </Button>
      </div>
    </form>
  );
}
