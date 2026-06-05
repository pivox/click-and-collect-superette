export interface AdminBetaMetricsStore {
  store_id: string;
  store_name: string;
  submitted: number;
  accepted: number;
  partially_accepted: number;
  rejected: number;
  cancelled: number;
  completed: number;
  acceptance_rate: number;
  cancellation_rate: number;
  last_activity_at: string | null;
}

export interface AdminBetaMetrics {
  id: string;
  date_from: string;
  date_to: string;
  store_id: string | null;
  submitted: number;
  accepted: number;
  partially_accepted: number;
  rejected: number;
  cancelled: number;
  completed: number;
  acceptance_rate: number;
  cancellation_rate: number;
  active_stores: number;
  activated_stores: number;
  store_activation_rate: number;
  stores: AdminBetaMetricsStore[];
}

export interface AdminBetaMetricsFilters {
  dateFrom?: string;
  dateTo?: string;
  storeId?: string;
}
