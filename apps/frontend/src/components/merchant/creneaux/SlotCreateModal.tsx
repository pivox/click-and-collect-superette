'use client';

import { useState } from 'react';
import { X } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import type { CreateSlotPayload } from '@/lib/types/merchant-slots.types';

const TUNIS_UTC_OFFSET = '+01:00';

export interface SlotCreateModalProps {
  initialDate: Date;
  onSubmit: (payload: CreateSlotPayload) => Promise<void>;
  onClose: () => void;
}

function toTunisDatetime(dateString: string, time: string): string {
  return `${dateString}T${time}:00${TUNIS_UTC_OFFSET}`;
}

function toMinutes(time: string): number {
  const [hours, minutes] = time.split(':').map(Number);
  return hours * 60 + minutes;
}

export function SlotCreateModal({
  initialDate,
  onSubmit,
  onClose,
}: SlotCreateModalProps) {
  const pad = (n: number) => String(n).padStart(2, '0');
  const defaultDate = `${initialDate.getFullYear()}-${pad(initialDate.getMonth() + 1)}-${pad(initialDate.getDate())}`;

  const [date, setDate] = useState(defaultDate);
  const [startTime, setStartTime] = useState('17:00');
  const [endTime, setEndTime] = useState('18:00');
  const [capacity, setCapacity] = useState('6');
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (startTime >= endTime) {
      setError("L'heure de fin doit être après l'heure de début.");
      return;
    }
    if (toMinutes(endTime) - toMinutes(startTime) !== 60) {
      setError('Le créneau doit durer exactement 1 heure.');
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
        starts_at: toTunisDatetime(date, startTime),
        ends_at: toTunisDatetime(date, endTime),
        capacity: cap,
      });
      onClose();
    } catch (err: unknown) {
      const message =
        err && typeof err === 'object' && 'response' in err
          ? (err as { response?: { data?: { detail?: string } } }).response?.data?.detail
          : undefined;
      setError(message ?? "Impossible de créer le créneau. Vérifiez les données et réessayez.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label="Créer un créneau ponctuel"
      className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center"
      onKeyDown={(e) => { if (e.key === 'Escape') onClose(); }}
    >
      <div className="w-full max-w-md rounded-t-2xl bg-card p-5 shadow-xl sm:rounded-2xl">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-h3 font-black">Nouveau créneau</h2>
          <button
            type="button"
            aria-label="Fermer"
            onClick={onClose}
            className="rounded p-1 hover:bg-soft"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-3">
          <div>
            <label className="mb-1 block text-xs font-bold text-muted" htmlFor="slot-date">
              Date
            </label>
            <input
              id="slot-date"
              type="date"
              value={date}
              onChange={(e) => setDate(e.target.value)}
              required
              autoFocus
              className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none"
            />
          </div>
          <div className="flex gap-3">
            <div className="flex-1">
              <label className="mb-1 block text-xs font-bold text-muted" htmlFor="slot-start">
                Heure début
              </label>
              <input
                id="slot-start"
                type="time"
                value={startTime}
                onChange={(e) => setStartTime(e.target.value)}
                required
                className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none"
              />
            </div>
            <div className="flex-1">
              <label className="mb-1 block text-xs font-bold text-muted" htmlFor="slot-end">
                Heure fin
              </label>
              <input
                id="slot-end"
                type="time"
                value={endTime}
                onChange={(e) => setEndTime(e.target.value)}
                required
                className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none"
              />
            </div>
          </div>
          <div>
            <label className="mb-1 block text-xs font-bold text-muted" htmlFor="slot-cap">
              Capacité (nb. commandes max)
            </label>
            <input
              id="slot-cap"
              type="number"
              min={1}
              value={capacity}
              onChange={(e) => setCapacity(e.target.value)}
              required
              className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none"
            />
          </div>

          {error && <p role="alert" className="text-xs text-danger">{error}</p>}

          <Button full type="submit" disabled={saving}>
            {saving ? 'Création…' : 'Créer le créneau'}
          </Button>
        </form>
      </div>
    </div>
  );
}
