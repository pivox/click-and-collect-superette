'use client';
import { useEffect } from 'react';
import { cn } from '@/lib/cn';
import { Button } from '@/components/ui/Button';

interface AdminDrawerProps {
  open: boolean;
  onClose: () => void;
  title: string;
  onSubmit: () => void;
  isSubmitting?: boolean;
  children: React.ReactNode;
  size?: 'md' | 'lg';
}

export function AdminDrawer({
  open,
  onClose,
  title,
  onSubmit,
  isSubmitting,
  children,
  size = 'md',
}: AdminDrawerProps) {
  useEffect(() => {
    if (!open) return;
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex justify-end">
      <div className="absolute inset-0 bg-black/40" onClick={onClose} />
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="drawer-title"
        className={cn(
          'relative flex h-full flex-col bg-card shadow-floating',
          size === 'md' ? 'w-full max-w-md' : 'w-full max-w-xl',
        )}
      >
        <div className="flex items-center justify-between border-b border-line px-6 py-4">
          <h2 id="drawer-title" className="font-black text-ink">{title}</h2>
          <button
            onClick={onClose}
            aria-label="Fermer"
            className="rounded p-1 text-muted hover:bg-soft hover:text-ink"
          >
            ✕
          </button>
        </div>
        <div className="flex-1 overflow-y-auto px-6 py-5">{children}</div>
        <div className="flex gap-3 border-t border-line px-6 py-4">
          <Button onClick={onSubmit} disabled={isSubmitting} full>
            {isSubmitting ? 'Enregistrement…' : 'Enregistrer'}
          </Button>
          <Button variant="ghost" onClick={onClose} disabled={isSubmitting} full>
            Annuler
          </Button>
        </div>
      </div>
    </div>
  );
}
