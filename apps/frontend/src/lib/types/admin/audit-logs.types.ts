// API responses use snake_case (backend @SerializedName annotations)

export interface AuditLog {
  id: string;
  admin_id: string;
  admin_email: string;
  action: string;
  resource_type: string;
  resource_id: string;
  summary: string | null;
  ip_address: string | null;
  user_agent: string | null;
  metadata: Record<string, unknown> | null;
  created_at: string;
}

export interface AuditLogListResponse {
  items: AuditLog[];
  page: number;
  limit: number;
  total: number;
}

export interface AuditLogFilters {
  admin?: string;
  action?: string;
  resource_type?: string;
  page?: number;
  limit?: number;
}
