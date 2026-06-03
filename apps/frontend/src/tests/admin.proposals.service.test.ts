import { describe, it, expect, vi, beforeEach } from 'vitest';
import { listProposals, approveProposal, rejectProposal } from '@/lib/services/admin/proposals.service';
import { apiClient } from '@/lib/api';
import type { ProposalListResponse } from '@/lib/types/admin/referentiel.types';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
    patch: vi.fn(),
  },
}));

const mockGet = vi.mocked(apiClient.get);
const mockPatch = vi.mocked(apiClient.patch);

const PROPOSAL_ID = 'proposal-uuid-1234';

const MOCK_LIST_RESPONSE: ProposalListResponse = {
  id: 'admin-product-proposals',
  items: [
    {
      id: PROPOSAL_ID,
      name_fr: 'Lait frais',
      name_ar: null,
      brand_name: 'Canel',
      category: 'Laits',
      status: 'pending',
      rejection_reason: null,
      created_at: '2026-06-01T10:00:00+00:00',
      proposed_by: 'merchant@example.com',
      created_product_reference_id: null,
    },
  ],
  page: 1,
  limit: 20,
  total: 1,
};

beforeEach(() => {
  vi.clearAllMocks();
});

describe('listProposals', () => {
  it('retourne la réponse paginée depuis l\'API', async () => {
    mockGet.mockResolvedValue({ data: MOCK_LIST_RESPONSE });

    const result = await listProposals();

    expect(mockGet).toHaveBeenCalledWith('/api/admin/product-proposals', {
      params: { page: 1, limit: 20 },
    });
    expect(result.items).toHaveLength(1);
    expect(result.total).toBe(1);
    expect(result.page).toBe(1);
    expect(result.limit).toBe(20);
  });

  it('passe le filtre de statut quand fourni', async () => {
    mockGet.mockResolvedValue({ data: MOCK_LIST_RESPONSE });

    await listProposals('pending');

    expect(mockGet).toHaveBeenCalledWith('/api/admin/product-proposals', {
      params: { page: 1, limit: 20, status: 'pending' },
    });
  });

  it('n\'inclut pas le param status quand non fourni', async () => {
    mockGet.mockResolvedValue({ data: MOCK_LIST_RESPONSE });

    await listProposals(undefined, 1, 20);

    const call = mockGet.mock.calls[0];
    expect(call[1]).toEqual({ params: { page: 1, limit: 20 } });
    expect((call[1] as { params: Record<string, unknown> }).params.status).toBeUndefined();
  });

  it('passe la page et la limite correctement', async () => {
    const page2Response: ProposalListResponse = { ...MOCK_LIST_RESPONSE, page: 2, limit: 10 };
    mockGet.mockResolvedValue({ data: page2Response });

    const result = await listProposals('approved', 2, 10);

    expect(mockGet).toHaveBeenCalledWith('/api/admin/product-proposals', {
      params: { page: 2, limit: 10, status: 'approved' },
    });
    expect(result.page).toBe(2);
    expect(result.limit).toBe(10);
  });
});

describe('approveProposal', () => {
  it('appelle l\'endpoint approve avec le productReferenceId', async () => {
    mockPatch.mockResolvedValue({ data: undefined });

    await approveProposal(PROPOSAL_ID, { productReferenceId: 'ref-uuid' });

    expect(mockPatch).toHaveBeenCalledWith(
      `/api/admin/product-proposals/${PROPOSAL_ID}/approve`,
      { productReferenceId: 'ref-uuid' },
    );
  });

  it('appelle l\'endpoint approve avec les canonicalData', async () => {
    mockPatch.mockResolvedValue({ data: undefined });

    await approveProposal(PROPOSAL_ID, {
      canonicalData: { nameFr: 'Lait UHT', brandId: 'brand-id', categoryId: 'cat-id' },
    });

    expect(mockPatch).toHaveBeenCalledWith(
      `/api/admin/product-proposals/${PROPOSAL_ID}/approve`,
      { canonicalData: { nameFr: 'Lait UHT', brandId: 'brand-id', categoryId: 'cat-id' } },
    );
  });
});

describe('rejectProposal', () => {
  it('appelle l\'endpoint reject avec la raison', async () => {
    mockPatch.mockResolvedValue({ data: undefined });

    await rejectProposal(PROPOSAL_ID, 'Produit déjà référencé');

    expect(mockPatch).toHaveBeenCalledWith(
      `/api/admin/product-proposals/${PROPOSAL_ID}/reject`,
      { reason: 'Produit déjà référencé' },
    );
  });
});
