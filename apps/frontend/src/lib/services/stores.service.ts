import type { Shop } from "@/types";
import { MOCK_SHOPS } from "@/lib/mock/shops.mock";
import { apiClient } from "@/lib/api";
import { USE_MOCKS, mockDelay } from "./index";

export async function listShops(): Promise<Shop[]> {
  if (USE_MOCKS) return mockDelay(MOCK_SHOPS);
  const { data } = await apiClient.get<Shop[]>("/api/stores");
  return data;
}

export async function getShop(shopId: string): Promise<Shop | null> {
  if (USE_MOCKS) {
    return mockDelay(MOCK_SHOPS.find((s) => s.id === shopId) ?? null);
  }
  const { data } = await apiClient.get<Shop>(`/api/stores/${shopId}`);
  return data;
}

/** For the "shop reconnu après scan" flow — qrToken is the store's qrCodeToken. */
export async function getShopBySlug(qrToken: string): Promise<Shop | null> {
  if (USE_MOCKS) {
    return mockDelay(MOCK_SHOPS.find((s) => s.slug === qrToken) ?? null);
  }
  const { data } = await apiClient.get<Shop>(`/api/stores/by-qr/${qrToken}`);
  return data;
}
