// API responses use snake_case (backend @SerializedName annotations)

export interface AuditLog {
  id: string;
  action: string;
  resource_type: string;
  resource_id: string | null;
  summary: string;
  ip_address: string | null;
  created_at: string;
  admin_email: string;
}

export interface AuditLogListResponse {
  items: AuditLog[];
  page: number;
  limit: number;
  total: number;
}

export interface AuditLogFilters {
  admin?: string;
  page?: number;
  limit?: number;
}
