import type { Shop, StoreSearchResult } from "@/types";
import { MOCK_SHOPS } from "@/lib/mock/shops.mock";
import { apiClient } from "@/lib/api";
import { USE_MOCKS, mockDelay } from "./index";

export async function listShops(): Promise<Shop[]> {
  if (USE_MOCKS) return mockDelay(MOCK_SHOPS);
  const { data } = await apiClient.get<StoreSearchResult>("/api/stores/search");
  return (data.items ?? []).map((item) => ({
    id: item.store_id,
    name: item.name,
    slug: item.slug,
    city: item.city,
    isActive: item.is_active,
    address: null,
    phone: null,
  }));
}

export async function getShop(shopId: string): Promise<Shop | null> {
  if (USE_MOCKS) {
    const mock = MOCK_SHOPS.find((s) => s.id === shopId);
    if (mock) return mockDelay(mock);
    // Real UUID from search: fall through to real API
  }
  try {
    const { data } = await apiClient.get<Shop>(`/api/stores/${shopId}`);
    return data;
  } catch {
    return null;
  }
}

/** For the "shop reconnu après scan" flow — qrToken is the store's qrCodeToken. */
export async function getShopBySlug(qrToken: string): Promise<Shop | null> {
  if (USE_MOCKS) {
    return mockDelay(MOCK_SHOPS.find((s) => s.slug === qrToken) ?? null);
  }
  const { data } = await apiClient.get<Shop>(`/api/stores/by-qr/${qrToken}`);
  return data;
}
