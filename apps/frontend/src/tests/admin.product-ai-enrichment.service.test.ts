import { describe, expect, it, vi, beforeEach } from 'vitest';
import { runProductAiEnrichment } from '@/lib/services/admin/product-ai-enrichment.service';
import { apiClient } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  apiClient: {
    post: vi.fn(),
  },
}));

const mockPost = vi.mocked(apiClient.post);

beforeEach(() => {
  vi.clearAllMocks();
});

describe('runProductAiEnrichment', () => {
  it('posts the requested limit and returns the run result', async () => {
    const response = {
      jobs_created: 10,
      jobs_submitted: 10,
      jobs_applied_total: 0,
      jobs_failed_total: 0,
      active_batches_checked: 1,
      openai_skipped: false,
    };
    mockPost.mockResolvedValue({ data: response });

    const result = await runProductAiEnrichment({ limit: 10 });

    expect(mockPost).toHaveBeenCalledWith('/api/admin/product-ai-enrichment/run', { limit: 10 });
    expect(result).toEqual(response);
  });
});
