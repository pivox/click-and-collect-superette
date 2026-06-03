import { apiClient } from '@/lib/api';
import type { ProposalListResponse, ApproveProposalPayload } from '@/lib/types/admin/referentiel.types';

export async function listProposals(status?: string, page = 1, limit = 20): Promise<ProposalListResponse> {
  const { data } = await apiClient.get<ProposalListResponse>('/api/admin/product-proposals', {
    params: {
      page,
      limit,
      ...(status ? { status } : {}),
    },
  });
  return data;
}

export async function approveProposal(
  id: string,
  payload: ApproveProposalPayload,
): Promise<void> {
  await apiClient.patch(`/api/admin/product-proposals/${id}/approve`, payload);
}

export async function rejectProposal(id: string, reason: string): Promise<void> {
  await apiClient.patch(`/api/admin/product-proposals/${id}/reject`, { reason });
}
