import type { Order, TimelineStep } from "@/types";
import { MOCK_ORDER } from "@/lib/mock/orders.mock";
import { apiClient } from "@/lib/api";
import { USE_MOCKS, mockDelay } from "./index";

/** Raw shape returned by GET /api/me/orders and /api/me/orders/{id}. */
interface RawOrder {
  id: string;
  kadhia_id: string | null;
  store_id: string;
  status: string;
  total_tnd: string;
  pickup_slot_id: string | null;
  notes: string | null;
  lines: unknown[];
  created_at: string;
  updated_at: string;
}

/** Derive a display code from UUID since the backend has no dedicated code field. */
function deriveCode(id: string): string {
  return `CMD-${id.slice(0, 8).toUpperCase()}`;
}

function mapRawOrder(raw: RawOrder): Order {
  return {
    id: raw.id,
    shopId: raw.store_id,
    status: raw.status as Order["status"],
    totalAmountTnd: raw.total_tnd,
    // API only returns the slot ID; full slot details unavailable without a second request.
    pickupSlot: null,
    submittedAt: null,
    acceptedAt: null,
    readyAt: null,
    completedAt: null,
    rejectionReason: null,
    code: deriveCode(raw.id),
    customerNote: raw.notes ?? null,
    lines: [],
  };
}

export async function listOrders(): Promise<Order[]> {
  if (USE_MOCKS) {
    return mockDelay([MOCK_ORDER]);
  }
  try {
    const { data } = await apiClient.get<{ "hydra:member": RawOrder[] }>(
      "/api/me/orders"
    );
    return (data["hydra:member"] ?? []).map(mapRawOrder);
  } catch {
    return [];
  }
}

export async function getOrder(orderId: string): Promise<Order | null> {
  if (USE_MOCKS) {
    if (orderId === MOCK_ORDER.id || orderId === MOCK_ORDER.code) {
      return mockDelay(MOCK_ORDER);
    }
    return mockDelay(null);
  }
  try {
    const { data } = await apiClient.get<RawOrder>(`/api/me/orders/${orderId}`);
    return mapRawOrder(data);
  } catch (err) {
    const status = (err as { response?: { status?: number } }).response?.status;
    if (status !== undefined && status >= 400 && status < 500) return null;
    throw err;
  }
}

/** Project an order's status onto the 5-step customer timeline. */
export function projectTimeline(order: Order): TimelineStep[] {
  const order_to_index: Record<Order["status"], number> = {
    draft: -1,
    submitted: 0,
    accepted: 1,
    partially_accepted: 0,
    preparing: 2,
    ready: 3,
    pickup_pending: 3,
    completed: 4,
    rejected: -1,
    cancelled: -1,
  };
  const idx = order_to_index[order.status];
  const stepsBase: Omit<TimelineStep, "state">[] = [
    {
      key: "submitted",
      label: "Commande soumise",
      hint: "Prix et créneau verrouillés",
    },
    {
      key: "accepted",
      label: "Acceptée par le marchand",
      hint: "Préparation lancée",
    },
    {
      key: "preparing",
      label: "En préparation",
      hint: "Commande préparée par le marchand",
    },
    {
      key: "ready",
      label: "Prête à retirer",
      hint: "QR code activé uniquement à cette étape",
    },
    {
      key: "completed",
      label: "Commande récupérée",
      hint: "Bonne dégustation !",
    },
  ];
  return stepsBase.map((s, i) => ({
    ...s,
    state: i < idx ? "done" : i === idx ? "current" : "todo",
  }));
}
