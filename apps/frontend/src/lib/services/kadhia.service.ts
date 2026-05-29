import type { Kadhia, KadhiaLine, ProductOffer } from "@/types";
import { apiClient } from "@/lib/api";
import { USE_MOCKS, mockDelay } from "./index";

// ─────────────────────────────────────────────────────────────────────────────
// Storage keys
//   mock mode : "kadhia:current"          → full Kadhia (unchanged)
//   real mode : "kadhia:active:{shopId}"  → kadhia id string
//               "kadhia:context"          → { shopId, kadhiaId } for cross-page nav
// ─────────────────────────────────────────────────────────────────────────────
const MOCK_KEY = "kadhia:current";
const ACTIVE_PREFIX = "kadhia:active:";
const CONTEXT_KEY = "kadhia:context";

// ─── mock-mode helpers (unchanged behaviour) ─────────────────────────────────

function readMock(): Kadhia | null {
  if (typeof window === "undefined") return null;
  try { return JSON.parse(window.localStorage.getItem(MOCK_KEY) ?? "null") as Kadhia | null; }
  catch { return null; }
}
function writeMock(k: Kadhia | null): void {
  if (typeof window === "undefined") return;
  if (!k) window.localStorage.removeItem(MOCK_KEY);
  else window.localStorage.setItem(MOCK_KEY, JSON.stringify(k));
}
function readMockForShop(shopId: string): Kadhia {
  const stored = readMock();
  if (stored?.shopId === shopId) return stored;
  return { id: "", shopId, status: "draft", lines: [], totalTnd: "0.000" };
}

// ─── real-mode helpers ───────────────────────────────────────────────────────

function readActiveId(shopId: string): string | null {
  if (typeof window === "undefined") return null;
  return window.localStorage.getItem(`${ACTIVE_PREFIX}${shopId}`);
}
function writeActiveId(shopId: string, id: string | null): void {
  if (typeof window === "undefined") return;
  if (!id) window.localStorage.removeItem(`${ACTIVE_PREFIX}${shopId}`);
  else window.localStorage.setItem(`${ACTIVE_PREFIX}${shopId}`, id);
}

interface KadhiaContext { shopId: string; kadhiaId: string }
function readContext(): KadhiaContext | null {
  if (typeof window === "undefined") return null;
  try { return JSON.parse(window.localStorage.getItem(CONTEXT_KEY) ?? "null") as KadhiaContext | null; }
  catch { return null; }
}
function writeContext(ctx: KadhiaContext | null): void {
  if (typeof window === "undefined") return;
  if (!ctx) window.localStorage.removeItem(CONTEXT_KEY);
  else window.localStorage.setItem(CONTEXT_KEY, JSON.stringify(ctx));
}

// ─── API response types ───────────────────────────────────────────────────────

type ApiLine = {
  id: string;
  merchant_product_id: string;
  product_name: string;
  unit_price_tnd: string;
  quantity: number;
  subtotal_tnd: string;
};
type ApiKadhia = {
  id: string;
  store_id: string;
  status: string;
  order_id: string | null;
  notes: string | null;
  lines: ApiLine[];
  total_tnd: string;
};
type ApiListItem = {
  id: string;
  store_id: string;
  store_name: string;
  status: string;
  lines_count: number;
  total_tnd: string;
  updated_at: string;
};

// ─── Exported types ───────────────────────────────────────────────────────────

export interface KadhiaListItem {
  id: string;
  storeId: string;
  storeName: string;
  status: string;
  linesCount: number;
  totalTnd: string;
  updatedAt: string;
}

export type KadhiaResult =
  | { type: "active"; kadhia: Kadhia }
  | { type: "none" }
  | { type: "multiple"; drafts: KadhiaListItem[] };

// ─── mappers ─────────────────────────────────────────────────────────────────

function mapLine(l: ApiLine): KadhiaLine {
  return {
    id: l.merchant_product_id,
    productOffer: {
      id: l.merchant_product_id,
      productReferenceId: "",
      nameFr: l.product_name,
      nameAr: null,
      brand: "",
      volume: null,
      unit: null,
      priceTnd: l.unit_price_tnd,
      isAvailable: true,
      photoUrl: null,
      category: "other",
    } satisfies ProductOffer,
    quantity: l.quantity,
    unitPriceTnd: l.unit_price_tnd,
    lineTotalTnd: l.subtotal_tnd,
  };
}

function mapKadhia(data: ApiKadhia): Kadhia {
  return {
    id: data.id,
    shopId: data.store_id,
    status: data.status as Kadhia["status"],
    lines: data.lines.map(mapLine),
    totalTnd: data.total_tnd,
    orderId: data.order_id,
  };
}

function recompute(lines: KadhiaLine[]): string {
  return lines.reduce((acc, l) => acc + parseFloat(l.lineTotalTnd || "0"), 0).toFixed(3);
}

// ─────────────────────────────────────────────────────────────────────────────
// Public API
// ─────────────────────────────────────────────────────────────────────────────

/** Returns the current kadhia context (used by /kadhia and /kadhia/slot for cross-page nav). */
export function readLocalKadhia(): Kadhia | null {
  if (USE_MOCKS) return readMock();
  const ctx = readContext();
  if (!ctx) return null;
  return { id: ctx.kadhiaId, shopId: ctx.shopId, status: "draft", lines: [], totalTnd: "0.000" };
}

/** Creates a new Kadhia for the given store. */
export async function createKadhia(shopId: string): Promise<Kadhia> {
  if (USE_MOCKS) {
    const fresh: Kadhia = { id: `kadhia-${shopId}`, shopId, status: "draft", lines: [], totalTnd: "0.000" };
    writeMock(fresh);
    writeContext({ shopId, kadhiaId: fresh.id });
    return mockDelay(fresh);
  }
  const { data } = await apiClient.post<ApiKadhia>(`/api/me/stores/${shopId}/kadhias`, {});
  const kadhia = mapKadhia(data);
  writeActiveId(shopId, kadhia.id);
  writeContext({ shopId, kadhiaId: kadhia.id });
  return kadhia;
}

/** Sets the active Kadhia for a store (selector choice) and fetches its full state. */
export async function activateKadhia(shopId: string, kadhiaId: string): Promise<Kadhia> {
  if (USE_MOCKS) {
    const existing = readMock();
    const kadhia = existing ?? { id: kadhiaId, shopId, status: "draft" as const, lines: [], totalTnd: "0.000" };
    writeMock(kadhia);
    writeContext({ shopId, kadhiaId });
    return mockDelay(kadhia);
  }
  writeActiveId(shopId, kadhiaId);
  writeContext({ shopId, kadhiaId });
  const { data } = await apiClient.get<ApiKadhia>(`/api/me/kadhias/${kadhiaId}`);
  return mapKadhia(data);
}

/**
 * Resolves the active Kadhia for a store:
 *   "active"   → a single active Kadhia was found
 *   "none"     → no draft Kadhia exists (show "Commencer une Kadhia")
 *   "multiple" → several drafts exist (show selector dialog)
 *
 * Mock mode always returns "active" (auto-creates a Kadhia).
 */
export async function getCurrentKadhia(shopId: string): Promise<KadhiaResult> {
  if (USE_MOCKS) {
    const existing = readMock();
    if (existing?.shopId === shopId) return mockDelay({ type: "active", kadhia: existing });
    const fresh: Kadhia = { id: `kadhia-${shopId}`, shopId, status: "draft", lines: [], totalTnd: "0.000" };
    writeMock(fresh);
    writeContext({ shopId, kadhiaId: fresh.id });
    return mockDelay({ type: "active", kadhia: fresh });
  }

  // 1. Check localStorage hint
  const activeId = readActiveId(shopId);
  if (activeId) {
    try {
      const { data } = await apiClient.get<ApiKadhia>(`/api/me/kadhias/${activeId}`);
      const kadhia = mapKadhia(data);
      writeContext({ shopId, kadhiaId: kadhia.id });
      return { type: "active", kadhia };
    } catch (err: unknown) {
      if ((err as { response?: { status?: number } }).response?.status === 404) {
        writeActiveId(shopId, null);
        writeContext(null);
      } else {
        throw err;
      }
    }
  }

  // 2. Fetch draft list
  const { data: list } = await apiClient.get<{ items: ApiListItem[]; total: number }>(
    `/api/me/stores/${shopId}/kadhias`,
  );
  const drafts = list.items.filter((k) => k.status === "draft");

  if (drafts.length === 0) return { type: "none" };

  if (drafts.length === 1) {
    const id = drafts[0].id;
    writeActiveId(shopId, id);
    writeContext({ shopId, kadhiaId: id });
    const { data } = await apiClient.get<ApiKadhia>(`/api/me/kadhias/${id}`);
    return { type: "active", kadhia: mapKadhia(data) };
  }

  return {
    type: "multiple",
    drafts: drafts.map((k) => ({
      id: k.id,
      storeId: k.store_id,
      storeName: k.store_name,
      status: k.status,
      linesCount: k.lines_count,
      totalTnd: k.total_tnd,
      updatedAt: k.updated_at,
    })),
  };
}

/**
 * Adds or updates a line in the active Kadhia.
 * `absoluteQty` is the desired final quantity (caller computes existing + 1 for increments).
 * The PUT endpoint returns the full updated Kadhia — no extra GET needed.
 */
export async function addLine(
  shopId: string,
  kadhiaId: string,
  product: ProductOffer,
  absoluteQty: number,
): Promise<Kadhia> {
  if (USE_MOCKS) {
    const current = readMockForShop(shopId);
    const lines = current.lines.map((l) =>
      l.productOffer.id === product.id
        ? { ...l, quantity: absoluteQty, lineTotalTnd: (parseFloat(l.unitPriceTnd) * absoluteQty).toFixed(3) }
        : l,
    );
    if (!lines.some((l) => l.productOffer.id === product.id)) {
      lines.push({
        id: product.id,
        productOffer: product,
        quantity: absoluteQty,
        unitPriceTnd: product.priceTnd,
        lineTotalTnd: (parseFloat(product.priceTnd) * absoluteQty).toFixed(3),
      });
    }
    const next: Kadhia = { ...current, lines, totalTnd: recompute(lines) };
    writeMock(next);
    return mockDelay(next);
  }

  const { data } = await apiClient.put<ApiKadhia>(
    `/api/me/kadhias/${kadhiaId}/lines/${product.id}`,
    { quantity: absoluteQty },
  );
  return mapKadhia(data);
}

/**
 * Updates a line quantity. `absoluteQty = 0` deletes the line.
 * PUT returns the full Kadhia; DELETE requires a subsequent GET.
 */
export async function updateLineQuantity(
  shopId: string,
  kadhiaId: string,
  lineId: string,
  absoluteQty: number,
): Promise<Kadhia> {
  if (USE_MOCKS) {
    const current = readMockForShop(shopId);
    const lines = current.lines
      .map((l) =>
        l.id === lineId
          ? { ...l, quantity: absoluteQty, lineTotalTnd: (parseFloat(l.unitPriceTnd) * absoluteQty).toFixed(3) }
          : l,
      )
      .filter((l) => l.quantity > 0);
    const next: Kadhia = { ...current, lines, totalTnd: recompute(lines) };
    writeMock(next);
    return mockDelay(next);
  }

  if (absoluteQty <= 0) {
    await apiClient.delete(`/api/me/kadhias/${kadhiaId}/lines/${lineId}`);
    const { data } = await apiClient.get<ApiKadhia>(`/api/me/kadhias/${kadhiaId}`);
    return mapKadhia(data);
  }

  const { data } = await apiClient.put<ApiKadhia>(
    `/api/me/kadhias/${kadhiaId}/lines/${lineId}`,
    { quantity: absoluteQty },
  );
  return mapKadhia(data);
}

export interface KadhiaListResult {
  items: KadhiaListItem[];
  total: number;
  page: number;
  pages: number;
}

/** Lists the authenticated customer's Kadhias, filtered by status. */
export async function listMyKadhias(status?: string, page = 1): Promise<KadhiaListResult> {
  if (USE_MOCKS) {
    const mock = readMock();
    if (!mock || (status && mock.status !== status)) {
      return mockDelay({ items: [], total: 0, page: 1, pages: 1 });
    }
    const item: KadhiaListItem = {
      id: mock.id,
      storeId: mock.shopId,
      storeName: "Ma Supérette",
      status: mock.status,
      linesCount: mock.lines.length,
      totalTnd: mock.totalTnd,
      updatedAt: new Date().toISOString(),
    };
    return mockDelay({ items: [item], total: 1, page: 1, pages: 1 });
  }

  const params = new URLSearchParams();
  if (status) params.set("status", status);
  if (page > 1) params.set("page", String(page));
  const query = params.toString() ? `?${params.toString()}` : "";
  const { data } = await apiClient.get<{ items: ApiListItem[]; total: number; page: number; pages: number }>(
    `/api/me/kadhias${query}`,
  );
  return {
    items: data.items.map((k) => ({
      id: k.id,
      storeId: k.store_id,
      storeName: k.store_name,
      status: k.status,
      linesCount: k.lines_count,
      totalTnd: k.total_tnd,
      updatedAt: k.updated_at,
    })),
    total: data.total,
    page: data.page,
    pages: data.pages,
  };
}

/** Fetches a single Kadhia by ID and sets it as the active context for the slot page. */
export async function fetchKadhia(kadhiaId: string): Promise<Kadhia> {
  if (USE_MOCKS) {
    const mock = readMock();
    if (mock) {
      writeContext({ shopId: mock.shopId, kadhiaId: mock.id });
      return mockDelay(mock);
    }
    throw new Error("KADHIA_NOT_FOUND");
  }
  const { data } = await apiClient.get<ApiKadhia>(`/api/me/kadhias/${kadhiaId}`);
  const kadhia = mapKadhia(data);
  writeActiveId(kadhia.shopId, kadhia.id);
  writeContext({ shopId: kadhia.shopId, kadhiaId: kadhia.id });
  return kadhia;
}

export async function clearKadhia(): Promise<void> {
  writeMock(null);
}

export async function discardKadhia(shopId: string): Promise<void> {
  if (USE_MOCKS) {
    writeMock(null);
    return mockDelay(undefined);
  }
  const kadhiaId = readActiveId(shopId);
  if (kadhiaId) {
    await apiClient.delete(`/api/me/kadhias/${kadhiaId}`);
    writeActiveId(shopId, null);
  }
  const ctx = readContext();
  if (ctx?.shopId === shopId) writeContext(null);
}

export interface SubmitKadhiaParams {
  shopId: string;
  pickupSlotId: string;
  customerNote?: string;
}
export interface SubmittedOrder {
  orderId: string;
  orderCode: string;
}

const MOCK_ORDER_ID = "order-demo-4821";

export async function submitKadhia(params: SubmitKadhiaParams): Promise<SubmittedOrder> {
  const { shopId, pickupSlotId, customerNote } = params;

  if (USE_MOCKS) {
    writeMock(null);
    return mockDelay({ orderId: MOCK_ORDER_ID, orderCode: "CMD-4821" });
  }

  const kadhiaId = readActiveId(shopId);
  if (!kadhiaId) throw new Error("KADHIA_NOT_FOUND");

  const { data: order } = await apiClient.post<{ id: string; code: string }>(
    `/api/me/kadhias/${kadhiaId}/submit`,
    { pickup_slot_id: pickupSlotId, notes: customerNote },
  );

  writeActiveId(shopId, null);
  writeContext(null);

  return { orderId: order.id, orderCode: order.code };
}
