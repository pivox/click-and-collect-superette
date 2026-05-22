/**
 * Service layer — single source of truth for "get me data".
 *
 * Today: returns mock data with a small artificial delay so loading states
 * are exercised. Tomorrow: the body of each function becomes an
 * `apiClient.get(...)` call. The signature does not change, so callers
 * (pages, hooks, components) never need to be touched.
 *
 * The toggle is the env var NEXT_PUBLIC_USE_MOCKS:
 *   - "1" (default in dev): use the in-memory mocks
 *   - "0": call the real API via apiClient
 */
export const USE_MOCKS =
  (process.env.NEXT_PUBLIC_USE_MOCKS ?? "1") !== "0";

/** Simulate network latency for mock responses (ms). */
export function mockDelay<T>(value: T, ms = 200): Promise<T> {
  return new Promise((resolve) => setTimeout(() => resolve(value), ms));
}

export * from "./stores.service";
export * from "./catalog.service";
export * from "./kadhia.service";
export * from "./slots.service";
export * from "./orders.service";
export * from "./auth.service";
