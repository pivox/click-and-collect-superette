export type BillingDocumentStatus = 'draft' | 'issued' | 'paid' | 'overdue' | 'cancelled';

export type BillingDocumentType = 'monthly_statement';

export type BillingDocumentPricingPhase = 'trial' | 'promo' | 'standard';

export type BillingDocumentReminderEmailStatus = 'planned' | 'sent' | 'failed' | 'not_configured' | 'not_applicable';

export interface BillingDocumentReminderScheduleItem {
  stage: string;
  label: string;
  scheduled_at: string;
  email_status: BillingDocumentReminderEmailStatus;
  email_sent_at: string | null;
  email_failed_at: string | null;
  whatsapp_contacted_at: string | null;
}

export interface BillingDocument {
  id: string;
  subscription_id: string;
  merchant_id: string;
  merchant_email: string;
  document_number: string;
  document_type: BillingDocumentType;
  document_nature_label: string;
  status: BillingDocumentStatus;
  pricing_phase: BillingDocumentPricingPhase;
  currency: string;
  billing_period_start: string;
  billing_period_end: string;
  issued_at: string | null;
  due_at: string | null;
  paid_at: string | null;
  cancelled_at: string | null;
  cancellation_reason: string | null;
  amount_tnd: string;
  amount_paid_tnd: string;
  amount_due_tnd: string;
  reminder_schedule: BillingDocumentReminderScheduleItem[];
  whatsapp_manual_contacted_at: string | null;
  created_at: string;
}

export interface BillingDocumentListResponse {
  id: string;
  items: BillingDocument[];
  page: number;
  limit: number;
  total: number;
}

export interface AdminBillingDocumentWhatsappContact {
  id: string;
  billing_document_id: string;
  phone: string;
  message: string;
  whatsapp_url: string;
}
