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
  const { data } = await apiClient.get<Kadhia>(`/shops/${shopId}/kadhia`);
  return data;
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
  const { data } = await apiClient.post<Kadhia>(
    `/shops/${shopId}/kadhia/lines`,
    { productOfferId: product.id, quantity },
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
  const { data } = await apiClient.patch<Kadhia>(
    `/shops/${shopId}/kadhia/lines/${lineId}`,
    { quantity },
  );
  return data;
}

export async function clearKadhia(): Promise<void> {
  if (USE_MOCKS) {
    write(null);
    return;
  }
  await apiClient.delete(`/kadhia/current`);
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

  // 3. Sync lines
  for (const line of local.lines) {
    await apiClient.put(
      `/api/me/kadhias/${backendKadhia.id}/lines/${line.productOffer.id}`,
      { quantity: line.quantity },
    );
  }

  // 4. Submit
  const { data: order } = await apiClient.post<{ id: string; code: string }>(
    `/api/me/kadhias/${backendKadhia.id}/submit`,
    { pickupSlotId, customerNote },
  );

  // 5. Clear localStorage
  write(null);

  return { orderId: order.id, orderCode: order.code };
}
