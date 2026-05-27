import type { Kadhia, KadhiaLine, ProductOffer } from "@/types";
import { apiClient } from "@/lib/api";
import { USE_MOCKS, mockDelay } from "./index";

/**
 * The Kadhia (panier) is kept in localStorage on the client side until the
 * customer submits the order. After submission it becomes an Order on the
 * backend. This mirrors the prototype's behaviour and lets us iterate
 * before the cart endpoint is wired up.
 */
const STORAGE_KEY = "kadhia:current";

function read(): Kadhia | null {
  if (typeof window === "undefined") return null;
  const raw = window.localStorage.getItem(STORAGE_KEY);
  if (!raw) return null;
  try {
    return JSON.parse(raw) as Kadhia;
  } catch {
    return null;
  }
}

function write(k: Kadhia | null): void {
  if (typeof window === "undefined") return;
  if (!k) window.localStorage.removeItem(STORAGE_KEY);
  else window.localStorage.setItem(STORAGE_KEY, JSON.stringify(k));
}

function recompute(lines: KadhiaLine[]): string {
  const total = lines.reduce(
    (acc, l) => acc + parseFloat(l.lineTotalTnd || "0"),
    0,
  );
  return total.toFixed(3);
}

function makeLine(p: ProductOffer, quantity: number): KadhiaLine {
  const unit = parseFloat(p.priceTnd);
  return {
    id: `line-${p.id}`,
    productOffer: p,
    quantity,
    unitPriceTnd: p.priceTnd,
    lineTotalTnd: (unit * quantity).toFixed(3),
  };
}

/** Read the current kadhia's shopId from localStorage (works in both mock and real modes). */
export function readLocalKadhia(): Kadhia | null {
  return read();
}

export async function getCurrentKadhia(shopId: string): Promise<Kadhia> {
  if (USE_MOCKS) {
    const existing = read();
    if (existing && existing.shopId === shopId) return mockDelay(existing);
    const fresh: Kadhia = {
      id: `kadhia-${shopId}`,
      shopId,
      status: "draft",
      lines: [],
      totalTnd: "0.000",
    };
    write(fresh);
    return mockDelay(fresh);
  }

  // Step 1: find the current kadhia for this store
  const { data: list } = await apiClient.get<{
    items: Array<{ id: string }>;
    total: number;
  }>(`/api/me/stores/${shopId}/kadhias`);

  if (list.items.length === 0) {
    return { id: "", shopId, status: "draft", lines: [], totalTnd: "0.000" };
  }

  // Step 2: fetch full kadhia with lines
  type ApiLine = {
    id: string;
    merchant_product_id: string;
    product_name: string;
    unit_price_tnd: string;
    quantity: number;
    subtotal_tnd: string;
  };
  const { data } = await apiClient.get<{
    id: string;
    store_id: string;
    status: string;
    lines: ApiLine[];
    total_tnd: string;
  }>(`/api/me/kadhias/${list.items[0].id}`);

  return {
    id: data.id,
    shopId: data.store_id,
    status: data.status as Kadhia["status"],
    lines: data.lines.map((l): KadhiaLine => ({
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
    })),
    totalTnd: data.total_tnd,
  };
}

export async function addLine(
  shopId: string,
  product: ProductOffer,
  quantity = 1,
): Promise<Kadhia> {
  if (USE_MOCKS) {
    const current = (await getCurrentKadhia(shopId));
    const lines = [...current.lines];
    const existing = lines.find((l) => l.productOffer.id === product.id);
    if (existing) {
      existing.quantity += quantity;
      existing.lineTotalTnd = (
        parseFloat(existing.unitPriceTnd) * existing.quantity
      ).toFixed(3);
    } else {
      lines.push(makeLine(product, quantity));
    }
    const next: Kadhia = { ...current, lines, totalTnd: recompute(lines) };
    write(next);
    return mockDelay(next);
  }
  const kadhia = await getCurrentKadhia(shopId);
  const { data } = await apiClient.put<Kadhia>(
    `/api/me/kadhias/${kadhia.id}/lines/${product.id}`,
    { quantity },
  );
  return data;
}

export async function updateLineQuantity(
  shopId: string,
  lineId: string,
  quantity: number,
): Promise<Kadhia> {
  if (USE_MOCKS) {
    const current = await getCurrentKadhia(shopId);
    const lines = current.lines
      .map((l) =>
        l.id === lineId
          ? {
              ...l,
              quantity,
              lineTotalTnd: (parseFloat(l.unitPriceTnd) * quantity).toFixed(3),
            }
          : l,
      )
      .filter((l) => l.quantity > 0);
    const next: Kadhia = { ...current, lines, totalTnd: recompute(lines) };
    write(next);
    return mockDelay(next);
  }
  const kadhia = await getCurrentKadhia(shopId);
  const { data } = await apiClient.patch<Kadhia>(
    `/api/me/kadhias/${kadhia.id}/lines/${lineId}`,
    { quantity },
  );
  return data;
}

export async function clearKadhia(shopId?: string): Promise<void> {
  if (USE_MOCKS) {
    write(null);
    return;
  }
  // Resolve shopId from localStorage if not provided
  const sid = shopId ?? read()?.shopId;
  if (sid) {
    const kadhia = await getCurrentKadhia(sid).catch(() => null);
    if (kadhia?.id) {
      await apiClient.delete(`/api/me/kadhias/${kadhia.id}`).catch(() => undefined);
    }
  }
  write(null);
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

const MOCK_SUBMIT_ORDER_ID = 'order-demo-4821';

export async function submitKadhia(params: SubmitKadhiaParams): Promise<SubmittedOrder> {
  const { shopId, pickupSlotId, customerNote } = params;

  if (USE_MOCKS) {
    write(null);
    return mockDelay({ orderId: MOCK_SUBMIT_ORDER_ID, orderCode: 'CMD-4821' });
  }

  // 1. Read Kadhia from localStorage
  const local = read();
  if (!local || local.lines.length === 0) {
    throw new Error('Kadhia vide');
  }

  // 2. Create Kadhia on backend
  const { data: backendKadhia } = await apiClient.post<{ id: string }>(
    `/api/me/stores/${shopId}/kadhias`,
    {},
  );

  try {
    // 3. Sync lines in parallel
    await Promise.all(
      local.lines.map((line) =>
        apiClient.put(
          `/api/me/kadhias/${backendKadhia.id}/lines/${line.productOffer.id}`,
          { quantity: line.quantity },
        ),
      ),
    );

    // 4. Submit
    const { data: order } = await apiClient.post<{ id: string; code: string }>(
      `/api/me/kadhias/${backendKadhia.id}/submit`,
      { pickupSlotId, customerNote },
    );

    // 5. Clear localStorage
    write(null);

    return { orderId: order.id, orderCode: order.code };
  } catch (err) {
    // Clean up the orphaned backend Kadhia if sync or submit fails.
    // localStorage remains intact so the user can retry.
    await apiClient.delete(`/api/me/kadhias/${backendKadhia.id}`).catch(() => undefined);
    throw err;
  }
}
