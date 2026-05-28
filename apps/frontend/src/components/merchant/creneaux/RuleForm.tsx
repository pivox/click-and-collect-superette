'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import type { CreateSlotRulePayload } from '@/lib/types/merchant-slots.types';

const WEEKDAYS = [
  { value: 1, label: 'Lun' },
  { value: 2, label: 'Mar' },
  { value: 3, label: 'Mer' },
  { value: 4, label: 'Jeu' },
  { value: 5, label: 'Ven' },
  { value: 6, label: 'Sam' },
  { value: 7, label: 'Dim' },
];

const ALL_WEEKDAYS = new Set<number>(WEEKDAYS.map((d) => d.value));

export interface RuleFormProps {
  onSubmit: (payload: CreateSlotRulePayload) => Promise<void>;
  onCancel: () => void;
}

export function RuleForm({ onSubmit, onCancel }: RuleFormProps) {
  const [selectedDays, setSelectedDays] = useState<Set<number>>(new Set(ALL_WEEKDAYS));
  const [startTime, setStartTime] = useState('17:00');
  const [endTime, setEndTime] = useState('19:00');
  const [capacity, setCapacity] = useState('6');
  const [errors, setErrors] = useState<string[]>([]);
  const [saving, setSaving] = useState(false);

  function toggleDay(value: number) {
    setSelectedDays((prev) => {
      const next = new Set(prev);
      if (next.has(value)) {
        next.delete(value);
      } else {
        next.add(value);
      }
      return next;
    });
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setErrors([]);

    if (selectedDays.size === 0) {
      setErrors(['Sélectionnez au moins un jour.']);
      return;
    }
    if (startTime >= endTime) {
      setErrors(["L'heure de fin doit être après l'heure de début."]);
      return;
    }
    const [sh, sm] = startTime.split(':').map(Number);
    const [eh, em] = endTime.split(':').map(Number);
    if (eh * 60 + em - (sh * 60 + sm) > 60) {
      setErrors(['Un créneau ne peut pas dépasser 1 heure.']);
      return;
    }
    const cap = parseInt(capacity, 10);
    if (!cap || cap <= 0) {
      setErrors(['La capacité doit être un nombre positif.']);
      return;
    }

    setSaving(true);
    const dayErrors: string[] = [];
    const sorted = Array.from(selectedDays).sort((a, b) => a - b);

    for (const weekday of sorted) {
      try {
        await onSubmit({ weekday, start_time: startTime, end_time: endTime, capacity: cap });
      } catch {
        const label = WEEKDAYS.find((d) => d.value === weekday)?.label ?? String(weekday);
        dayErrors.push(`${label} : doublon ou erreur serveur.`);
      }
    }

    setSaving(false);
    if (dayErrors.length > 0) {
      setErrors(dayErrors);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="mt-3 space-y-3 rounded-lg border border-line bg-soft p-3">
      <div>
        <span className="mb-2 block text-xs font-bold text-muted">Jours de la semaine</span>
        <div className="flex flex-wrap gap-1.5">
          {WEEKDAYS.map((d) => {
            const selected = selectedDays.has(d.value);
            return (
              <button
                key={d.value}
                type="button"
                onClick={() => toggleDay(d.value)}
                className={[
                  'rounded-full px-3 py-1 text-xs font-semibold transition-colors',
                  selected
                    ? 'bg-primary text-white'
                    : 'bg-white text-muted border border-line',
                ].join(' ')}
              >
                {d.label}
              </button>
            );
          })}
        </div>
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
      {errors.length > 0 && (
        <ul role="alert" className="space-y-0.5">
          {errors.map((err) => (
            <li key={err} className="text-xs text-danger">
              {err}
            </li>
          ))}
        </ul>
      )}
      <div className="flex gap-2">
        <Button type="submit" disabled={saving || selectedDays.size === 0}>
          {saving
            ? 'Création…'
            : `Ajouter ${selectedDays.size > 1 ? `${selectedDays.size} règles` : 'la règle'}`}
        </Button>
        <Button type="button" variant="ghost" onClick={onCancel}>
          Annuler
        </Button>
      </div>
    </form>
  );
}
