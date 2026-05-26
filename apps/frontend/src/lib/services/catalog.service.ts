import type { ProductCategory, ProductOffer } from "@/types";
import { MOCK_PRODUCTS } from "@/lib/mock/products.mock";
import { apiClient } from "@/lib/api";
import { USE_MOCKS, mockDelay } from "./index";

export interface CatalogQuery {
  shopId: string;
  category?: ProductOffer["category"] | "all";
  search?: string;
}

interface CatalogApiItem {
  id: string;
  product_reference_id: string | null;
  name_fr: string;
  name_ar: string | null;
  brand: string | null;
  category_slug: string;
  volume: string | null;
  unit: string;
  price_tnd: string;
  is_available: boolean;
}

const VALID_CATEGORIES: ProductCategory[] = [
  "dairy",
  "drinks",
  "grocery",
  "hygiene",
  "snacks",
  "other",
];

function toProductCategory(slug: string): ProductCategory {
  return VALID_CATEGORIES.includes(slug as ProductCategory)
    ? (slug as ProductCategory)
    : "other";
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
    { params: { category: q.category, search: q.search } },
  );
  return (data.items ?? []).map((item) => ({
    id: item.id,
    productReferenceId: item.product_reference_id ?? item.id,
    nameFr: item.name_fr,
    nameAr: item.name_ar,
    brand: item.brand ?? "",
    volume: item.volume !== null ? parseFloat(item.volume) : null,
    unit: item.unit,
    priceTnd: item.price_tnd,
    isAvailable: item.is_available,
    photoUrl: null,
    category: toProductCategory(item.category_slug),
  }));
}
