export type SubscriptionPaymentMethod = 'cash' | 'bank_transfer';
export type SubscriptionPaymentStatus = 'confirmed';

export interface SubscriptionPayment {
  id: string;
  billing_document_id: string;
  subscription_id: string;
  merchant_id: string;
  merchant_email: string;
  amount_tnd: string;
  currency: string;
  method: SubscriptionPaymentMethod;
  reference: string | null;
  status: SubscriptionPaymentStatus;
  paid_at: string;
  period_start: string;
  period_end: string;
  validated_by_admin_id: string;
  validated_at: string;
}

export interface SubscriptionPaymentListResponse {
  id: string;
  items: SubscriptionPayment[];
  page: number;
  limit: number;
  total: number;
}

export interface CreateSubscriptionPaymentPayload {
  billing_document_id: string;
  amount_tnd: string;
  method: SubscriptionPaymentMethod;
  paid_at: string;
  reference?: string;
}
