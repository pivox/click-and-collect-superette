import type { ProductOffer } from "@/types";
import { MOCK_PRODUCTS } from "./products.mock";

/**
 * Mock suggestions — shape matches GET /api/me/stores/{id}/suggestions.
 * Simple deterministic slices of the mock catalog so the sections render.
 */
export function getMockSuggestions(): {
  frequentlyBoughtTogether: ProductOffer[];
  recentlyOrdered: ProductOffer[];
} {
  const available = MOCK_PRODUCTS.filter((p) => p.isAvailable);
  return {
    frequentlyBoughtTogether: available.slice(0, 4),
    recentlyOrdered: available.slice(4, 8),
  };
}
