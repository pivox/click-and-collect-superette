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
    const { data } = await apiClient.get<{
      id: string;
      name: string;
      slug: string;
      city: string | null;
      country?: string;
      is_active: boolean;
      next_pickup_at?: string | null;
      opens_at?: string | null;
      closes_at?: string | null;
    }>(`/api/stores/${shopId}`);
    return {
      id: data.id,
      name: data.name,
      slug: data.slug,
      city: data.city,
      isActive: data.is_active,
      address: null,
      phone: null,
      nextPickupAt: data.next_pickup_at ?? null,
      opensAt: data.opens_at ?? undefined,
      closesAt: data.closes_at ?? undefined,
    };
  } catch (err) {
    const status = (err as { response?: { status?: number } }).response?.status;
    if (status === 404) return null;
    throw err;
  }
}

/** For the "shop reconnu après scan" flow — qrToken is the store's qrCodeToken. */
export async function getShopBySlug(qrToken: string): Promise<Shop | null> {
  if (USE_MOCKS) {
    return mockDelay(MOCK_SHOPS.find((s) => s.slug === qrToken) ?? null);
  }
  try {
    const { data } = await apiClient.get<{
      store_id: string;
      name: string;
      slug: string;
      city: string | null;
      is_active: boolean;
    }>(`/api/stores/by-qr/${qrToken}`);
    return {
      id: data.store_id,
      name: data.name,
      slug: data.slug,
      city: data.city,
      isActive: data.is_active,
      address: null,
      phone: null,
    };
  } catch (err) {
    const status = (err as { response?: { status?: number } }).response?.status;
    if (status === 404) return null;
    throw err;
  }
}
