'use client';
import { Button } from '@/components/ui/Button';

interface AdminConfirmDialogProps {
  open: boolean;
  onClose: () => void;
  onConfirm: () => void;
  title: string;
  message: string;
  confirmLabel?: string;
  variant?: 'danger' | 'warning';
}

export function AdminConfirmDialog({
  open,
  onClose,
  onConfirm,
  title,
  message,
  confirmLabel = 'Confirmer',
  variant = 'danger',
}: AdminConfirmDialogProps) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      <div className="absolute inset-0 bg-black/40" onClick={onClose} />
      <div className="relative w-full max-w-sm rounded-xl bg-card p-6 shadow-floating">
        <h3 className="mb-2 font-black text-ink">{title}</h3>
        <p className="mb-6 text-sm text-muted">{message}</p>
        <div className="flex gap-3">
          <Button variant={variant === 'danger' ? 'danger' : 'primary'} onClick={onConfirm} className="flex-1">
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
