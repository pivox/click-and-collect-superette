import type { ProductOffer } from "@/types";
import { MOCK_PRODUCTS } from "./products.mock";

/** Mock product favorites — a Set of product ids persisted in localStorage. */
const STORAGE_KEY = "mock:product-favorites";

function readIds(): Set<string> {
  if (typeof window === "undefined") return new Set();
  try {
    return new Set(
      JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? "[]") as string[],
    );
  } catch {
    return new Set();
  }
}

function writeIds(ids: Set<string>): void {
  if (typeof window === "undefined") return;
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(Array.from(ids)));
}

export function toggleMockProductFavorite(productId: string, isFavorite: boolean): void {
  const ids = readIds();
  if (isFavorite) ids.add(productId);
  else ids.delete(productId);
  writeIds(ids);
}

export function getMockFavoriteProducts(): ProductOffer[] {
  const ids = readIds();
  return MOCK_PRODUCTS.filter((p) => ids.has(p.id));
}
