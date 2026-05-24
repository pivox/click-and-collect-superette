import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/Button';

interface RejectOrderDialogProps {
  isOpen: boolean;
  isSubmitting: boolean;
  onCancel: () => void;
  onConfirm: (reason: string | null) => void;
}

export function RejectOrderDialog({
  isOpen,
  isSubmitting,
  onCancel,
  onConfirm,
}: RejectOrderDialogProps) {
  const [reason, setReason] = useState('');

  useEffect(() => {
    if (!isOpen) return;

    setReason('');
  }, [isOpen]);

  if (!isOpen) return null;

  const trimmedReason = reason.trim();

  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-black/30 p-4"
      role="presentation"
      onClick={onCancel}
    >
      <div
        className="w-full max-w-md rounded-md bg-card p-5 shadow-card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="reject-order-title"
        onClick={(event) => event.stopPropagation()}
      >
        <h2 id="reject-order-title" className="text-lg font-black">
          Refuser la commande
        </h2>
        <label className="mt-4 block text-sm font-bold" htmlFor="reject-reason">
          Motif de refus
        </label>
        <textarea
          id="reject-reason"
          className="mt-2 min-h-24 w-full rounded-md border border-line p-3 text-sm"
          maxLength={500}
          value={reason}
          onChange={(event) => setReason(event.target.value)}
        />
        <div className="mt-5 flex justify-end gap-3">
          <Button variant="ghost" size="md" disabled={isSubmitting} onClick={onCancel}>
            Annuler
          </Button>
          <Button
            variant="danger"
            size="md"
            disabled={isSubmitting}
            onClick={() => onConfirm(trimmedReason === '' ? null : trimmedReason)}
          >
            Confirmer le refus
          </Button>
        </div>
      </div>
    </div>
  );
}
