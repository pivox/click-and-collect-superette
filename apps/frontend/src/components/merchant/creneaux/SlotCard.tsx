'use client';

import { useState } from 'react';
import { Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { cn } from '@/lib/cn';
import { formatTime } from '@/lib/format';
import type { MerchantPickupSlot } from '@/lib/types/merchant-slots.types';

export interface SlotCardProps {
  slot: MerchantPickupSlot;
  onPatch: (slotId: string, payload: { capacity?: number; is_active?: boolean }) => Promise<void>;
  onDelete: (slotId: string) => Promise<void>;
}

export function SlotCard({ slot, onPatch, onDelete }: SlotCardProps) {
  const [editingCapacity, setEditingCapacity] = useState(false);
  const [capacity, setCapacity] = useState(String(slot.capacity));
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [togglingActive, setTogglingActive] = useState(false);
  const [toggleError, setToggleError] = useState<string | null>(null);

  const remaining = Math.max(0, slot.capacity - slot.booked_count);
  const isFull = remaining <= 0;

  async function handleSaveCapacity() {
    const val = parseInt(capacity, 10);
    if (!val || val <= 0) return;
    setSaving(true);
    try {
      await onPatch(slot.id, { capacity: val });
      setEditingCapacity(false);
    } catch {
      setSaveError('Impossible de modifier la capacité.');
    } finally {
      setSaving(false);
    }
  }

  async function handleToggleActive() {
    setTogglingActive(true);
    setToggleError(null);
    try {
      await onPatch(slot.id, { is_active: !slot.is_active });
    } catch {
      setToggleError('Impossible de modifier le statut.');
    } finally {
      setTogglingActive(false);
    }
  }

  async function handleDelete() {
    if (slot.booked_count > 0) {
      setDeleteError('Ce créneau a des réservations, impossible de le supprimer.');
      return;
    }
    await onDelete(slot.id);
  }

  return (
    <div
      className={cn(
        'rounded-lg border bg-card p-3 shadow-card',
        (!slot.is_active || isFull) && 'opacity-60',
      )}
    >
      <div className="flex items-start justify-between gap-2">
        <div>
          <p className="text-sm font-bold">
            {formatTime(slot.starts_at)}–{formatTime(slot.ends_at)}
          </p>
          <p className="mt-0.5 text-xs text-muted">
            {slot.booked_count}/{slot.capacity} réservé
            {slot.booked_count > 1 ? 's' : ''} · {remaining} place
            {remaining > 1 ? 's' : ''} restante
            {remaining > 1 ? 's' : ''}
          </p>
        </div>
        <div className="flex items-center gap-1.5">
          {isFull && (
            <span className="rounded-full bg-danger/10 px-2 py-0.5 text-[11px] font-bold text-danger">
              Complet
            </span>
          )}
          {!slot.is_active && (
            <span className="rounded-full bg-soft px-2 py-0.5 text-[11px] font-bold text-muted">
              Inactif
            </span>
          )}
          <button
            type="button"
            aria-label="Supprimer ce créneau"
            onClick={handleDelete}
            className="rounded p-1 text-muted hover:bg-soft hover:text-danger"
          >
            <Trash2 className="h-3.5 w-3.5" />
          </button>
        </div>
      </div>

      {deleteError && (
        <p role="alert" className="mt-2 text-xs text-danger">
          {deleteError}
        </p>
      )}
      {toggleError && (
        <p role="alert" className="mt-1 text-xs text-danger">
          {toggleError}
        </p>
      )}

      <div className="mt-2">
        <div className="flex items-center gap-2">
        {editingCapacity ? (
          <>
            <input
              type="number"
              min={1}
              value={capacity}
              onChange={(e) => setCapacity(e.target.value)}
              className="w-16 rounded border border-line px-2 py-1 text-sm"
              aria-label="Capacité"
            />
            <Button size="md" onClick={handleSaveCapacity} disabled={saving}>
              {saving ? '…' : 'OK'}
            </Button>
            <Button
              size="md"
              variant="ghost"
              onClick={() => {
                setCapacity(String(slot.capacity));
                setEditingCapacity(false);
                setSaveError(null);
              }}
            >
              Annuler
            </Button>
          </>
        ) : (
          <>
            <button
              type="button"
              onClick={() => setEditingCapacity(true)}
              className="text-xs text-primary hover:underline"
            >
              Modifier capacité
            </button>
            <button
              type="button"
              onClick={handleToggleActive}
              disabled={togglingActive}
              className="text-xs text-primary hover:underline"
            >
              {slot.is_active ? 'Désactiver' : 'Activer'}
            </button>
          </>
        )}
        </div>
        {editingCapacity && saveError && (
          <p role="alert" className="mt-1 text-xs text-danger">
            {saveError}
          </p>
        )}
      </div>
    </div>
  );
}
