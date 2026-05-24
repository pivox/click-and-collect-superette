import type { MerchantOrderStatus } from '@/lib/types/merchant.types';

const STATUS_LABELS: Record<string, string> = {
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

export function OrderStatusBadge({ status }: { status: MerchantOrderStatus | string }) {
  const label = STATUS_LABELS[status] ?? status;

  return (
    <span className="rounded-full bg-soft px-2 py-1 text-xs font-bold text-muted">
      {label}
    </span>
  );
}
