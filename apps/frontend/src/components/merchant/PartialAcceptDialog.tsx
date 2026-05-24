import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/Button';
import type { MerchantOrderLine } from '@/lib/types/merchant.types';

interface PartialAcceptDialogProps {
  isOpen: boolean;
  isSubmitting: boolean;
  lines: MerchantOrderLine[];
  onCancel: () => void;
  onConfirm: (payload: { rejected_merchant_product_ids: string[]; notes: string | null }) => void;
}

export function PartialAcceptDialog({
  isOpen,
  isSubmitting,
  lines,
  onCancel,
  onConfirm,
}: PartialAcceptDialogProps) {
  const [availableIds, setAvailableIds] = useState<string[]>(
    lines.map((line) => line.merchant_product_id),
  );
  const [notes, setNotes] = useState('');

  const rejectedIds = useMemo(
    () =>
      lines
        .map((line) => line.merchant_product_id)
        .filter((lineId) => !availableIds.includes(lineId)),
    [availableIds, lines],
  );
  const canSubmit = availableIds.length > 0 && rejectedIds.length > 0;

  if (!isOpen) return null;

  const toggle = (lineId: string, checked: boolean) => {
    setAvailableIds((current) =>
      checked ? [...current, lineId] : current.filter((id) => id !== lineId),
    );
  };

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/30 p-4">
      <div className="w-full max-w-2xl rounded-md bg-card p-5 shadow-card">
        <h2 className="text-lg font-black">Accepter partiellement la Kadhia</h2>
        <div className="mt-4 divide-y divide-line rounded-md border border-line">
          {lines.map((line) => (
            <label key={line.merchant_product_id} className="flex items-center gap-3 p-4 text-sm">
              <input
                type="checkbox"
                checked={availableIds.includes(line.merchant_product_id)}
                aria-label={`${line.product_name ?? line.merchant_product_id} disponible`}
                onChange={(event) => toggle(line.merchant_product_id, event.currentTarget.checked)}
              />
              <span className="font-bold">{line.product_name ?? line.merchant_product_id}</span>
              <span className="text-muted">x{line.quantity}</span>
            </label>
          ))}
        </div>
        <label className="mt-4 block text-sm font-bold" htmlFor="partial-notes">
          Note pour le client
        </label>
        <textarea
          id="partial-notes"
          className="mt-2 min-h-20 w-full rounded-md border border-line p-3 text-sm"
          maxLength={500}
          value={notes}
          onChange={(event) => setNotes(event.target.value)}
        />
        {!canSubmit && (
          <p className="mt-3 text-sm text-muted">
            Garde au moins une ligne acceptée et marque au moins une ligne indisponible.
          </p>
        )}
        <div className="mt-5 flex justify-end gap-3">
          <Button variant="ghost" size="md" disabled={isSubmitting} onClick={onCancel}>
            Annuler
          </Button>
          <Button
            size="md"
            disabled={isSubmitting || !canSubmit}
            onClick={() =>
              onConfirm({
                rejected_merchant_product_ids: rejectedIds,
                notes: notes.trim() === '' ? null : notes.trim(),
              })
            }
          >
            Confirmer l’acceptation partielle
          </Button>
        </div>
      </div>
    </div>
  );
}
