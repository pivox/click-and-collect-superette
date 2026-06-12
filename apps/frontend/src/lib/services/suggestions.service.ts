import type { ProductOffer } from "@/types";
import { apiClient } from "@/lib/api";
import { getMockSuggestions } from "@/lib/mock/suggestions.mock";
import { USE_MOCKS, mockDelay } from "./index";
import { mapCatalogApiItem, type CatalogApiItem } from "./catalog.service";

export interface StoreSuggestions {
  frequentlyBoughtTogether: ProductOffer[];
  recentlyOrdered: ProductOffer[];
}

interface SuggestionsApiResponse {
  store_id: string;
  frequently_bought_together: CatalogApiItem[];
  recently_ordered: CatalogApiItem[];
}

/**
 * Suggestions for the store catalog page. kadhiaId seeds the
 * "frequently bought together" section with the current Kadhia lines.
 */
export async function getStoreSuggestions(
  shopId: string,
  kadhiaId?: string,
): Promise<StoreSuggestions> {
  if (USE_MOCKS) {
    return mockDelay(getMockSuggestions());
  }
  // The catalog page is public: a stale token must not redirect to login.
  const { data } = await apiClient.get<SuggestionsApiResponse>(
    `/api/me/stores/${shopId}/suggestions`,
    {
      params: { kadhia_id: kadhiaId || undefined },
      skipAuthRedirect: true,
    },
  );
  return {
    frequentlyBoughtTogether: (data.frequently_bought_together ?? []).map(mapCatalogApiItem),
    recentlyOrdered: (data.recently_ordered ?? []).map(mapCatalogApiItem),
  };
}
