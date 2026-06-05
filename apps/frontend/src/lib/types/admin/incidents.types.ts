export type IncidentType =
  | 'customer_absent'
  | 'merchant_late'
  | 'missing_product'
  | 'price_error'
  | 'order_not_prepared'
  | 'qr_issue'
  | 'late_cancellation'
  | 'other';

export type IncidentStatus = 'open' | 'in_progress' | 'closed';

export interface IncidentNote {
  id: string;
  note: string;
  created_by_admin_id: string;
  created_by_admin_email: string;
  created_at: string;
}

export interface IncidentHistoryEntry {
  event: string;
  occurred_at: string;
  summary: string | null;
}

export interface Incident {
  id: string;
  order_id: string;
  merchant_id: string;
  merchant_email: string;
  customer_id: string;
  customer_email: string;
  type: IncidentType;
  status: IncidentStatus;
  occurred_at: string;
  created_at: string;
  updated_at: string;
  closed_at: string | null;
  notes?: IncidentNote[];
  history?: IncidentHistoryEntry[];
}

export interface IncidentListResponse {
  id: string;
  items: Incident[];
  page: number;
  limit: number;
  total: number;
}
