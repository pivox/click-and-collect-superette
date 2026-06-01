import type { ProductOffer } from "@/types";
import { MOCK_PRODUCTS } from "@/lib/mock/products.mock";
import { apiClient } from "@/lib/api";
import { USE_MOCKS, mockDelay } from "./index";

export interface CatalogQuery {
  shopId: string;
  category?: string | "all";
  search?: string;
  page?: number;
  itemsPerPage?: number;
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
  volume?: string | null;
  unit: string;
  price_tnd: string;
  is_available: boolean;
}

interface CatalogApiCategory {
  key: string;
  label_fr: string;
  label_ar: string | null;
}

interface CatalogApiResponse {
  items: CatalogApiItem[];
  categories?: CatalogApiCategory[];
  page?: number;
  items_per_page?: number;
  total?: number;
  pages?: number;
}

export interface CatalogCategoryOption {
  key: string;
  labelFr: string;
  labelAr: string | null;
}

export interface CatalogResult {
  items: ProductOffer[];
  categories: CatalogCategoryOption[];
  page: number;
  itemsPerPage: number;
  total: number;
  pages: number;
}

export async function listCatalog(q: CatalogQuery): Promise<CatalogResult> {
  const page = q.page ?? 1;
  const itemsPerPage = q.itemsPerPage ?? 30;
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
    const offset = (page - 1) * itemsPerPage;
    const paginatedItems = items.slice(offset, offset + itemsPerPage);
    const categoriesByKey = new Map<string, CatalogCategoryOption>();
    items.forEach((item) => {
      if (!item.category || categoriesByKey.has(item.category)) return;
      categoriesByKey.set(item.category, {
        key: item.category,
        labelFr: item.categoryNameFr ?? item.category,
        labelAr: item.categoryNameAr ?? null,
      });
    });
    return mockDelay({
      items: paginatedItems,
      categories: Array.from(categoriesByKey.values()).sort((a, b) =>
        a.labelFr.localeCompare(b.labelFr, "fr"),
      ),
      page,
      itemsPerPage,
      total: items.length,
      pages: Math.max(1, Math.ceil(items.length / itemsPerPage)),
    });
  }
  const { data } = await apiClient.get<CatalogApiResponse>(
    `/api/stores/${q.shopId}/catalog`,
    {
      params: {
        category: q.category !== 'all' ? q.category : undefined,
        query: q.search,
        page,
        items_per_page: itemsPerPage,
      },
    },
  );
  const items = (data.items ?? []).map((item) => ({
    id: item.id,
    productReferenceId:
      item.product_reference_id ?? item.local_product_id ?? item.id,
    nameFr: item.name_fr,
    nameAr: item.name_ar,
    brand: item.brand ?? "",
    volume: (() => {
      if (item.volume == null || item.volume === "" || item.volume === "undefined") return null;
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

  return {
    items,
    categories: (data.categories ?? []).map((category) => ({
      key: category.key,
      labelFr: category.label_fr,
      labelAr: category.label_ar,
    })),
    page: data.page ?? page,
    itemsPerPage: data.items_per_page ?? itemsPerPage,
    total: data.total ?? items.length,
    pages: data.pages ?? Math.max(1, Math.ceil(items.length / itemsPerPage)),
  };
}
