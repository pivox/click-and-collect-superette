import type { Shop, StoreSearchResult } from "@/types";
import {
  MOCK_SHOPS,
  getMockMyShops,
  removeMockShop,
  toggleMockFavorite,
} from "@/lib/mock/shops.mock";
import { apiClient } from "@/lib/api";
import { USE_MOCKS, mockDelay } from "./index";

export interface StoreListResult {
  items: Shop[];
  total: number;
  page: number;
  limit: number;
}

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

export async function recordStoreVisit(
  shopId: string,
  source: "qr_code" | "search" | "manual" | "order" = "qr_code",
): Promise<void> {
  if (USE_MOCKS) return mockDelay(undefined);
  if (typeof window !== "undefined" && !localStorage.getItem("jwt_token")) return;

  await apiClient.post(
    `/api/me/stores/${shopId}/visit`,
    { source },
    { skipAuthRedirect: true },
  );
}

export async function listMyStores(page = 1, limit = 20): Promise<StoreListResult> {
  if (USE_MOCKS) {
    const all = getMockMyShops();
    const start = (page - 1) * limit;
    return mockDelay({
      items: all.slice(start, start + limit),
      total: all.length,
      page,
      limit,
    });
  }
  const params = new URLSearchParams({ page: String(page), limit: String(limit) });
  const { data } = await apiClient.get<{
    id: string;
    items: {
      store_id: string;
      name: string;
      slug: string;
      city: string | null;
      is_active: boolean;
      is_favorite: boolean;
    }[];
    total: number;
    page: number;
    limit: number;
  }>(`/api/me/stores?${params.toString()}`);
  return {
    items: data.items.map((item) => ({
      id: item.store_id,
      name: item.name,
      slug: item.slug,
      city: item.city,
      isActive: item.is_active,
      address: null,
      phone: null,
      isFavorite: item.is_favorite,
    })),
    total: data.total,
    page: data.page,
    limit: data.limit,
  };
}

export async function removeStore(shopId: string): Promise<void> {
  if (USE_MOCKS) {
    removeMockShop(shopId);
    return mockDelay(undefined);
  }
  await apiClient.delete(`/api/me/stores/${shopId}`);
}

export async function toggleFavorite(shopId: string, isFavorite: boolean): Promise<void> {
  if (USE_MOCKS) {
    toggleMockFavorite(shopId, isFavorite);
    return mockDelay(undefined);
  }
  await apiClient.patch(`/api/me/stores/${shopId}/favorite`, { is_favorite: isFavorite });
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
