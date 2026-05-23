'use client';
import { Button } from '@/components/ui/Button';

interface ExtraField {
  label: string;
  value: string;
  onChange: (v: string) => void;
  required?: boolean;
}

interface AdminConfirmDialogProps {
  open: boolean;
  onClose: () => void;
  onConfirm: () => void;
  title: string;
  message: string;
  confirmLabel?: string;
  confirmDisabled?: boolean;
  variant?: 'danger' | 'warning';
  extraField?: ExtraField;
}

export function AdminConfirmDialog({
  open,
  onClose,
  onConfirm,
  title,
  message,
  confirmLabel = 'Confirmer',
  confirmDisabled: confirmDisabledProp = false,
  variant = 'danger',
  extraField,
}: AdminConfirmDialogProps) {
  if (!open) return null;

  const confirmDisabled = confirmDisabledProp || !!(extraField?.required && !extraField.value.trim());

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      <div className="absolute inset-0 bg-black/40" onClick={onClose} />
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="confirm-dialog-title"
        className="relative w-full max-w-sm rounded-xl bg-card p-6 shadow-floating"
      >
        <h3 id="confirm-dialog-title" className="mb-2 font-black text-ink">{title}</h3>
        <p className="mb-4 text-sm text-muted">{message}</p>
        {extraField && (
          <div className="mb-5">
            <label className="mb-1 block text-sm font-semibold">
              {extraField.label}
              {extraField.required && <span className="ml-1 text-danger">*</span>}
            </label>
            <textarea
              value={extraField.value}
              onChange={(e) => extraField.onChange(e.target.value)}
              rows={3}
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>
        )}
        <div className="flex gap-3">
          <Button
            variant={variant === 'danger' ? 'danger' : 'primary'}
            onClick={onConfirm}
            disabled={confirmDisabled}
            className="flex-1"
          >
            {confirmLabel}
          </Button>
          <Button variant="ghost" onClick={onClose} className="flex-1">
            Annuler
          </Button>
        </div>
      </div>
    </div>
  );
}
