import type { ProductOffer } from "@/types";
import { MOCK_PRODUCTS } from "@/lib/mock/products.mock";
import { apiClient } from "@/lib/api";
import { USE_MOCKS, mockDelay } from "./index";

export interface CatalogQuery {
  shopId: string;
  category?: ProductOffer["category"] | "all";
  search?: string;
}

export async function listCatalog(q: CatalogQuery): Promise<ProductOffer[]> {
  if (USE_MOCKS) {
    let items = MOCK_PRODUCTS;
    if (q.category && q.category !== "all") {
      items = items.filter((p) => p.category === q.category);
    }
    if (q.search) {
      const needle = q.search.toLowerCase();
      items = items.filter(
        (p) =>
          p.nameFr.toLowerCase().includes(needle) ||
          p.brand.toLowerCase().includes(needle),
      );
    }
    return mockDelay(items);
  }
  const { data } = await apiClient.get<ProductOffer[]>(
    `/api/stores/${q.shopId}/products`,
    { params: { category: q.category, search: q.search } },
  );
  return data;
}
