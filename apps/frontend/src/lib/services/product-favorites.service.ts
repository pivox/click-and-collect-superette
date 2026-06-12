import type { ProductOffer } from "@/types";
import { apiClient } from "@/lib/api";
import {
  getMockFavoriteProducts,
  toggleMockProductFavorite,
} from "@/lib/mock/product-favorites.mock";
import { USE_MOCKS, mockDelay } from "./index";
import { mapCatalogApiItem, type CatalogApiItem } from "./catalog.service";

interface FavoriteProductsApiResponse {
  store_id: string;
  items: CatalogApiItem[];
  total: number;
}

/** Product favorites of the authenticated customer for one store. */
export async function listFavoriteProducts(shopId: string): Promise<ProductOffer[]> {
  if (USE_MOCKS) {
    return mockDelay(getMockFavoriteProducts());
  }
  // The catalog page is public: a stale token must not redirect to login.
  const { data } = await apiClient.get<FavoriteProductsApiResponse>(
    `/api/me/stores/${shopId}/favorite-products`,
    { skipAuthRedirect: true },
  );
  return (data.items ?? []).map(mapCatalogApiItem);
}

export async function toggleProductFavorite(
  productId: string,
  isFavorite: boolean,
): Promise<void> {
  if (USE_MOCKS) {
    toggleMockProductFavorite(productId, isFavorite);
    return mockDelay(undefined);
  }
  await apiClient.patch(`/api/me/products/${productId}/favorite`, {
    is_favorite: isFavorite,
  });
}
