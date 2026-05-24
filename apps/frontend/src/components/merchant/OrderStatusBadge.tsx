import type { MerchantOrderStatus } from '@/lib/types/merchant.types';
import { cn } from '@/lib/cn';

const STATUS_LABELS: Record<string, string> = {
  draft: 'Brouillon',
  submitted: 'Soumise',
  accepted: 'Acceptée',
  partially_accepted: 'Acceptée partiellement',
  rejected: 'Refusée',
  preparing: 'En préparation',
  ready: 'Prête',
  pickup_pending: 'Retrait en cours',
  completed: 'Finalisée',
  cancelled: 'Annulée',
};

const STATUS_CLASSES: Record<string, string> = {
  draft: 'bg-soft text-muted',
  submitted: 'bg-blue-100 text-blue-700',
  accepted: 'bg-green-100 text-green-700',
  partially_accepted: 'bg-yellow-100 text-yellow-800',
  rejected: 'bg-status-cancel-bg text-status-cancel',
  preparing: 'bg-orange-100 text-orange-700',
  ready: 'bg-green-200 text-green-800',
  pickup_pending: 'bg-purple-100 text-purple-700',
  completed: 'bg-soft text-muted',
  cancelled: 'bg-status-cancel-bg text-status-cancel',
};

export function OrderStatusBadge({ status }: { status: MerchantOrderStatus | string }) {
  const label = STATUS_LABELS[status] ?? status;

  return (
    <span
      className={cn(
        'rounded-full px-2 py-1 text-xs font-bold',
        STATUS_CLASSES[status] ?? 'bg-soft text-muted',
      )}
    >
      {label}
    </span>
  );
}
