import { apiClient } from '@/lib/api';
import type {
  Incident,
  IncidentListResponse,
  IncidentStatus,
} from '@/lib/types/admin/incidents.types';

interface ListIncidentsOptions {
  page?: number;
  limit?: number;
  merchant?: string;
  client?: string;
  status?: IncidentStatus | '';
}

export async function listAdminIncidents(
  options: ListIncidentsOptions = {},
): Promise<IncidentListResponse> {
  const { data } = await apiClient.get<IncidentListResponse>('/api/admin/incidents', {
    params: {
      page: options.page ?? 1,
      limit: options.limit ?? 20,
      ...(options.merchant ? { merchant: options.merchant } : {}),
      ...(options.client ? { client: options.client } : {}),
      ...(options.status ? { status: options.status } : {}),
    },
  });

  return data;
}

export async function getAdminIncident(id: string): Promise<Incident> {
  const { data } = await apiClient.get<Incident>(`/api/admin/incidents/${id}`);

  return data;
}

export async function addAdminIncidentNote(id: string, note: string): Promise<Incident> {
  const { data } = await apiClient.post<Incident>(`/api/admin/incidents/${id}/notes`, { note });

  return data;
}

export async function startAdminIncidentProcessing(id: string): Promise<Incident> {
  const { data } = await apiClient.patch<Incident>(`/api/admin/incidents/${id}/start-processing`, {});

  return data;
}

export async function closeAdminIncident(id: string): Promise<Incident> {
  const { data } = await apiClient.patch<Incident>(`/api/admin/incidents/${id}/close`, {});

  return data;
}
