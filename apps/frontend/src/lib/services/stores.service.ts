import type { Shop } from "@/types";
import { MOCK_SHOPS } from "@/lib/mock/shops.mock";
import { apiClient } from "@/lib/api";
import { USE_MOCKS, mockDelay } from "./index";

export async function listShops(): Promise<Shop[]> {
  if (USE_MOCKS) return mockDelay(MOCK_SHOPS);
  const { data } = await apiClient.get<Shop[]>("/shops");
  return data;
}

export async function getShop(shopId: string): Promise<Shop | null> {
  if (USE_MOCKS) {
    return mockDelay(MOCK_SHOPS.find((s) => s.id === shopId) ?? null);
  }
  const { data } = await apiClient.get<Shop>(`/shops/${shopId}`);
  return data;
}

/** For the "shop reconnu après scan" flow. */
export async function getShopBySlug(slug: string): Promise<Shop | null> {
  if (USE_MOCKS) {
    return mockDelay(MOCK_SHOPS.find((s) => s.slug === slug) ?? null);
  }
  const { data } = await apiClient.get<Shop>(`/shops/by-slug/${slug}`);
  return data;
}
