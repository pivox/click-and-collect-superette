'use client';

import { Suspense } from 'react';
import { useSearchParams } from 'next/navigation';
import { SubscriptionPaymentsManager } from '@/components/admin/subscriptions/SubscriptionPaymentsManager';

function PaymentsPage() {
  const params = useSearchParams();
  return <SubscriptionPaymentsManager key={params.toString()} subscriptionId={params.get('subscription_id') ?? undefined} documentId={params.get('billing_document_id') ?? undefined} />;
}

export default function AdminPaymentsPage() {
  return <Suspense fallback={<p role="status">Chargement des règlements…</p>}><PaymentsPage /></Suspense>;
}
