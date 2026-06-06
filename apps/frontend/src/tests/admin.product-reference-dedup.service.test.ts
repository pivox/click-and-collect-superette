import { describe, expect, it, vi, beforeEach } from 'vitest';
import {
  compareProductReferences,
  listProductReferences,
  listProductReferenceDuplicateCandidates,
  mergeProductReference,
} from '@/lib/services/admin/product-references.service';
import { apiClient } from '@/lib/api';
import type {
  ProductReferenceDuplicateCandidateListResponse,
  ProductReferenceMergeResult,
  ProductReferenceComparison,
  ProductReferenceListResponse,
} from '@/lib/types/admin/referentiel.types';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
    patch: vi.fn(),
  },
}));

const mockGet = vi.mocked(apiClient.get);
const mockPatch = vi.mocked(apiClient.patch);

beforeEach(() => {
  vi.clearAllMocks();
});

describe('product reference dedup admin service', () => {
  it('lists product references with quality filters and server sort params', async () => {
    const response: ProductReferenceListResponse = {
      id: 'admin-product-references',
      items: [],
      page: 1,
      limit: 20,
      total: 0,
    };
    mockGet.mockResolvedValue({ data: response });

    const result = await listProductReferences({
      qualityScoreMin: 60,
      qualityScoreMax: 79,
      sort: 'quality_score',
      direction: 'desc',
      page: 2,
      limit: 10,
    });

    expect(mockGet).toHaveBeenCalledWith('/api/admin/product-references', {
      params: {
        page: 2,
        limit: 10,
        quality_min: 60,
        quality_max: 79,
        sort: 'quality_score',
        direction: 'desc',
      },
    });
    expect(result).toEqual(response);
  });

  it('lists duplicate candidates with pagination params', async () => {
    const response: ProductReferenceDuplicateCandidateListResponse = {
      id: 'admin-product-reference-duplicates',
      items: [],
      page: 1,
      limit: 20,
      total: 0,
    };
    mockGet.mockResolvedValue({ data: response });

    const result = await listProductReferenceDuplicateCandidates({ page: 2, limit: 10 });

    expect(mockGet).toHaveBeenCalledWith('/api/admin/product-references/duplicates', {
      params: { page: 2, limit: 10 },
    });
    expect(result).toEqual(response);
  });

  it('loads the comparison for two product references', async () => {
    const response = {
      left: { id: 'left-id' },
      right: { id: 'right-id' },
      left_offer_count: 0,
      right_offer_count: 2,
      reason: 'identity',
    } as ProductReferenceComparison;
    mockGet.mockResolvedValue({ data: response });

    const result = await compareProductReferences('left-id', 'right-id');

    expect(mockGet).toHaveBeenCalledWith('/api/admin/product-references/compare', {
      params: { left: 'left-id', right: 'right-id' },
    });
    expect(result).toEqual(response);
  });

  it('merges an absorbed reference into the kept one', async () => {
    const response = {
      kept: { id: 'kept-id' },
      absorbed: { id: 'absorbed-id' },
      moved_offer_count: 3,
      removed_conflicting_offer_count: 0,
    } as ProductReferenceMergeResult;
    mockPatch.mockResolvedValue({ data: response });

    const result = await mergeProductReference('absorbed-id', 'kept-id');

    expect(mockPatch).toHaveBeenCalledWith('/api/admin/product-references/absorbed-id/merge', {
      keptProductReferenceId: 'kept-id',
    });
    expect(result).toEqual(response);
  });
});
