'use client';
import { Check, Archive, X } from 'lucide-react';
import { Button } from '@/components/ui/Button';

export interface BulkActionBarProps {
  selectedCount: number;
  onClear: () => void;
  onApprove: () => void;
  onArchive: () => void;
  onReject: () => void;
  isBusy: boolean;
  eligibleApprove: number;
  eligibleArchive: number;
  eligibleReject: number;
}

export function BulkActionBar({
  selectedCount,
  onClear,
  onApprove,
  onArchive,
  onReject,
  isBusy,
  eligibleApprove,
  eligibleArchive,
  eligibleReject,
}: BulkActionBarProps) {
  return (
    <div className="mb-4 flex flex-col gap-3 rounded-xl border border-line bg-blue-50 p-4 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex items-center gap-3">
        <span className="text-sm font-semibold text-ink">
          {selectedCount} référence{selectedCount !== 1 ? 's' : ''} sélectionnée{selectedCount !== 1 ? 's' : ''}
        </span>
      </div>
      <div className="flex flex-wrap gap-2">
        <Button
          size="md"
          variant={eligibleApprove === 0 ? 'ghost' : 'secondary'}
          disabled={isBusy || eligibleApprove === 0}
          onClick={onApprove}
          className="flex items-center gap-1.5"
        >
          <Check size={16} />
          Approuver{eligibleApprove > 0 && ` (${eligibleApprove})`}
        </Button>
        <Button
          size="md"
          variant={eligibleArchive === 0 ? 'ghost' : 'secondary'}
          disabled={isBusy || eligibleArchive === 0}
          onClick={onArchive}
          className="flex items-center gap-1.5"
        >
          <Archive size={16} />
          Archiver{eligibleArchive > 0 && ` (${eligibleArchive})`}
        </Button>
        <Button
          size="md"
          variant={eligibleReject === 0 ? 'ghost' : 'secondary'}
          disabled={isBusy || eligibleReject === 0}
          onClick={onReject}
          className="flex items-center gap-1.5"
        >
          <X size={16} />
          Rejeter{eligibleReject > 0 && ` (${eligibleReject})`}
        </Button>
        <Button
          size="md"
          variant="ghost"
          disabled={isBusy}
          onClick={onClear}
          className="ml-auto"
        >
          Désélectionner tout
        </Button>
      </div>
    </div>
  );
}
