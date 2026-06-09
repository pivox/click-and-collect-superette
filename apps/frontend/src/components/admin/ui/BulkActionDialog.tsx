'use client';
import { useState } from 'react';

export type BulkAction = 'approve' | 'archive' | 'reject';

export interface BulkResult {
  succeeded: number;
  failed: number;
  ignored: number;
}

interface BulkActionDialogProps {
  open: boolean;
  action: BulkAction | null;
  eligibleCount: number;
  ignoredCount: number;
  onClose: () => void;
  onConfirm: (reason?: string) => void;
  result: BulkResult | null;
  isBusy: boolean;
}

const ACTION_LABELS: Record<BulkAction, { singular: string; plural: string; description: string }> = {
  approve: {
    singular: 'approuver',
    plural: 'approuver',
    description: 'approuver',
  },
  archive: {
    singular: 'archiver',
    plural: 'archiver',
    description: 'archiver',
  },
  reject: {
    singular: 'rejeter',
    plural: 'rejeter',
    description: 'rejeter',
  },
};

const ACTION_IGNORED: Record<BulkAction, string> = {
  approve: 'déjà approuvée(s) ou archivée(s)',
  archive: 'déjà archivée(s)',
  reject: 'déjà rejetée(s) ou archivée(s)',
};

export function BulkActionDialog({
  open,
  action,
  eligibleCount,
  ignoredCount,
  onClose,
  onConfirm,
  result,
  isBusy,
}: BulkActionDialogProps) {
  const [rejectionReason, setRejectionReason] = useState('');

  if (!open || !action) return null;

  const actionLabel = ACTION_LABELS[action];
  const ignoredLabel = ACTION_IGNORED[action];

  if (result) {
    return (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" role="dialog" aria-modal="true" aria-labelledby="bulk-result-title">
        <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
          <h2 id="bulk-result-title" className="mb-4 text-lg font-bold text-ink">Résultat de l&apos;action</h2>
          <div className="space-y-3 text-sm">
            {result.succeeded > 0 && (
              <div className="flex items-center gap-2 rounded-md bg-green-50 px-3 py-2">
                <span className="flex-1">
                  {result.succeeded} référence{result.succeeded !== 1 ? 's' : ''} traitée{result.succeeded !== 1 ? 's' : ''} avec succès
                </span>
                <span className="text-lg font-bold text-green-600">✓</span>
              </div>
            )}
            {result.failed > 0 && (
              <div className="flex items-center gap-2 rounded-md bg-red-50 px-3 py-2">
                <span className="flex-1">
                  {result.failed} erreur{result.failed !== 1 ? 's' : ''}
                </span>
                <span className="text-lg font-bold text-red-600">✕</span>
              </div>
            )}
            {result.ignored > 0 && (
              <div className="flex items-center gap-2 rounded-md bg-gray-50 px-3 py-2">
                <span className="flex-1">
                  {result.ignored} référence{result.ignored !== 1 ? 's' : ''} ignorée{result.ignored !== 1 ? 's' : ''}
                </span>
                <span className="text-lg font-bold text-gray-400">—</span>
              </div>
            )}
          </div>
          <button
            onClick={() => { onClose(); setRejectionReason(''); }}
            className="mt-4 w-full rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark"
          >
            Fermer
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" role="dialog" aria-modal="true" aria-labelledby="bulk-action-title">
      <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
        <h2 id="bulk-action-title" className="mb-2 text-lg font-bold text-ink">
          {action === 'approve' && 'Approuver en masse'}
          {action === 'archive' && 'Archiver en masse'}
          {action === 'reject' && 'Rejeter en masse'}
        </h2>
        <p className="mb-4 text-sm text-muted">
          Vous êtes sur le point de {actionLabel.description} {eligibleCount} référence{eligibleCount !== 1 ? 's' : ''}.
          {ignoredCount > 0 && (
            <>
              {' '}
              {ignoredCount} référence{ignoredCount !== 1 ? 's' : ''} {ignoredLabel} seront ignorées.
            </>
          )}
        </p>
        {action === 'reject' && (
          <textarea
            autoFocus
            value={rejectionReason}
            onChange={(e) => setRejectionReason(e.target.value)}
            placeholder="Raison du rejet (obligatoire)…"
            maxLength={500}
            className="mb-4 w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            rows={4}
          />
        )}
        <div className="flex gap-3">
          <button
            onClick={() => { onClose(); setRejectionReason(''); }}
            disabled={isBusy}
            className="flex-1 rounded-md border border-line px-4 py-2 text-sm font-semibold hover:bg-soft disabled:opacity-50"
          >
            Annuler
          </button>
          <button
            onClick={() => { onConfirm(action === 'reject' ? rejectionReason : undefined); setRejectionReason(''); }}
            disabled={isBusy || (action === 'reject' && !rejectionReason.trim()) || eligibleCount === 0}
            className="flex-1 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-50"
          >
            {isBusy ? 'Traitement…' : 'Confirmer'}
          </button>
        </div>
      </div>
    </div>
  );
}
