import { apiClient } from '@/lib/api';
import type {
  ProductAiEnrichmentRunPayload,
  ProductAiEnrichmentRunResult,
} from '@/lib/types/admin/referentiel.types';

export async function runProductAiEnrichment(
  payload: ProductAiEnrichmentRunPayload,
): Promise<ProductAiEnrichmentRunResult> {
  const { data } = await apiClient.post<ProductAiEnrichmentRunResult>(
    '/api/admin/product-ai-enrichment/run',
    payload,
  );

  return data;
}
