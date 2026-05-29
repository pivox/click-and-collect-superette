import { apiClient } from '@/lib/api';
import type { AuditLogListResponse, AuditLogFilters } from '@/lib/types/admin/audit-logs.types';

export async function listAuditLogs(filters: AuditLogFilters = {}): Promise<AuditLogListResponse> {
  const { data } = await apiClient.get<AuditLogListResponse>('/api/admin/audit-logs', {
    params: {
      page: filters.page ?? 1,
      limit: filters.limit ?? 20,
      ...(filters.admin ? { admin: filters.admin } : {}),
      ...(filters.action ? { action: filters.action } : {}),
      ...(filters.resource_type ? { resource_type: filters.resource_type } : {}),
    },
  });
  return data;
}
