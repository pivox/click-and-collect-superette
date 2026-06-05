export interface AdminMessengerMonitoring {
  id?: string;
  status: 'ok' | 'degraded';
  pending: number;
  failed: number;
  oldest_age_s: number | null;
  last_consumed_at: string | null;
  thresholds: {
    pending: number;
    oldest_age_s: number;
  };
  checked_at: string;
}
