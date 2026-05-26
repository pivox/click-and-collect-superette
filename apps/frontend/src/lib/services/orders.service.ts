import type { Order, TimelineStep } from "@/types";
import { MOCK_ORDER } from "@/lib/mock/orders.mock";
import { apiClient } from "@/lib/api";
import { USE_MOCKS, mockDelay } from "./index";

export async function listOrders(): Promise<Order[]> {
  if (USE_MOCKS) {
    return mockDelay([MOCK_ORDER]);
  }
  try {
    const { data } = await apiClient.get<{ 'hydra:member': Order[] }>('/api/me/orders');
    return data['hydra:member'] ?? [];
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
    const { data } = await apiClient.get<Order>(`/api/me/orders/${orderId}`);
    return data;
  } catch (err) {
    const status = (err as { response?: { status?: number } }).response?.status;
    if (status === 404) return null;
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
