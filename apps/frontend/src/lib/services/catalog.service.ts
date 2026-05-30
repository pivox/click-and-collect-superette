import type { ProductOffer } from "@/types";
import { MOCK_PRODUCTS } from "@/lib/mock/products.mock";
import { apiClient } from "@/lib/api";
import { USE_MOCKS, mockDelay } from "./index";

export interface CatalogQuery {
  shopId: string;
  category?: string | "all";
  search?: string;
}

interface CatalogApiItem {
  id: string;
  product_reference_id: string | null;
  local_product_id: string | null;
  name_fr: string;
  name_ar: string | null;
  brand: string | null;
  category: string;
  category_ar: string | null;
  category_slug: string;
  volume: string | null;
  unit: string;
  price_tnd: string;
  is_available: boolean;
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
  const { data } = await apiClient.get<{ items: CatalogApiItem[] }>(
    `/api/stores/${q.shopId}/catalog`,
    { params: { category: q.category !== 'all' ? q.category : undefined, query: q.search } },
  );
  return (data.items ?? []).map((item) => ({
    id: item.id,
    productReferenceId:
      item.product_reference_id ?? item.local_product_id ?? item.id,
    nameFr: item.name_fr,
    nameAr: item.name_ar,
    brand: item.brand ?? "",
    volume: (() => {
      if (item.volume === null || item.volume === "" || item.volume === "undefined") return null;
      const parsed = parseFloat(item.volume);
      if (Number.isNaN(parsed)) {
        console.warn(
          `[catalog.service] Non-numeric volume for item ${item.id}: "${item.volume}"`,
        );
        return null;
      }
      return parsed;
    })(),
    unit: item.unit,
    priceTnd: item.price_tnd,
    isAvailable: item.is_available,
    photoUrl: null,
    category: item.category_slug,
    categoryNameFr: item.category,
    categoryNameAr: item.category_ar,
  }));
}
