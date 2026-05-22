import { apiClient } from '@/lib/api';
import type { Proposal, ApproveProposalPayload } from '@/lib/types/admin/referentiel.types';

export async function listProposals(status?: string, page = 1): Promise<Proposal[]> {
  const { data } = await apiClient.get<Proposal[]>('/api/admin/product-proposals', {
    params: {
      page,
      limit: 20,
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
