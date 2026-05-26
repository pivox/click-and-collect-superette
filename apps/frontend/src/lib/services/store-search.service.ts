import { apiClient } from "@/lib/api";
import type { StoreSearchResult } from "@/types";

export async function searchStores(query: string): Promise<StoreSearchResult> {
  const { data } = await apiClient.get<StoreSearchResult>("/api/stores/search", {
    params: { query },
  });
  return data;
}
