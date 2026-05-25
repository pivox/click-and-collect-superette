'use client';

import { Button } from '@/components/ui/Button';

interface MerchantCatalogBulkActionsProps {
  isSelectionMode: boolean;
  selectedCount: number;
  selectionError: string | null;
  bulkError: string | null;
  isSubmitting: boolean;
  onEnterSelectionMode: () => void;
  onCancelSelectionMode: () => void;
  onMarkAvailable: () => void;
  onMarkUnavailable: () => void;
}

export function MerchantCatalogBulkActions({
  bulkError,
  isSelectionMode,
  isSubmitting,
  onCancelSelectionMode,
  onEnterSelectionMode,
  onMarkAvailable,
  onMarkUnavailable,
  selectedCount,
  selectionError,
}: MerchantCatalogBulkActionsProps) {
  return (
    <div className="mt-5 rounded-md bg-card p-4 shadow-card">
      <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <p className="text-sm font-black">Rupture en masse</p>
          <p className="mt-1 text-sm text-muted">
            {isSelectionMode
              ? `${selectedCount} produit${selectedCount > 1 ? 's' : ''} sélectionné${
                  selectedCount > 1 ? 's' : ''
                }`
              : 'Sélectionne des produits pour mettre à jour leur disponibilité.'}
          </p>
        </div>

        {isSelectionMode ? (
          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              size="md"
              onClick={onMarkUnavailable}
              disabled={selectedCount === 0 || isSubmitting}
            >
              Marquer indisponible
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="md"
              onClick={onMarkAvailable}
              disabled={selectedCount === 0 || isSubmitting}
            >
              Remettre disponible
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="md"
              onClick={onCancelSelectionMode}
              disabled={isSubmitting}
            >
              Annuler
            </Button>
          </div>
        ) : (
          <Button type="button" variant="ghost" size="md" onClick={onEnterSelectionMode}>
            Mode sélection
          </Button>
        )}
      </div>

      {selectionError && (
        <div className="mt-3 rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
          {selectionError}
        </div>
      )}
      {bulkError && (
        <div className="mt-3 rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
          {bulkError}
        </div>
      )}
    </div>
  );
}
