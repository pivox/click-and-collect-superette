import type {
  CustomerOrderStatusSnapshot,
  CustomerPickupSessionConfirmation,
  Order,
  PickupSession,
  TimelineStep,
} from "@/types";
import { MOCK_ORDER } from "@/lib/mock/orders.mock";
import { apiClient } from "@/lib/api";
import { USE_MOCKS, mockDelay } from "./index";

/** Raw shape returned by GET /api/me/orders and /api/me/orders/{id}. */
interface RawOrder {
  id: string;
  kadhia_id: string | null;
  store_id: string;
  store_name?: string | null;
  store_address?: string | null;
  store_city?: string | null;
  status: string;
  total_tnd: string;
  pickup_slot_id: string | null;
  pickup_slot?: {
    id: string;
    starts_at: string;
    ends_at: string;
  } | null;
  notes: string | null;
  lines: unknown[];
  created_at: string;
  updated_at: string;
  pickup_code?: string | null;
}

interface RawPickupSession {
  id: string;
  token: string;
  expires_at: string;
  is_used: boolean;
  is_expired: boolean;
  qr_payload: string;
}

interface RawCustomerPickupSessionConfirmation {
  id: string;
  order_id: string;
  order_status: Order["status"];
  scanned_at: string;
  merchant_confirmed_at: string | null;
  customer_confirmed_at: string | null;
  is_used: boolean;
  is_completed: boolean;
}

interface RawCustomerOrderPickupSessionStatus {
  exists: boolean;
  is_scanned: boolean;
  merchant_confirmed: boolean;
  customer_confirmed: boolean;
  is_used: boolean;
  force_completed_by_merchant: boolean;
}

interface RawCustomerOrderStatusSnapshot {
  order_id: string;
  status: Order["status"];
  status_label_fr: string;
  status_label_ar: string;
  updated_at: string;
  pickup_session: RawCustomerOrderPickupSessionStatus;
}

const MOCK_PICKUP_SESSION_TOKEN = "11111111-1111-4111-8111-111111111111";

/** Derive a display code from UUID since the backend has no dedicated code field. */
function deriveCode(id: string): string {
  return `CMD-${id.slice(0, 8).toUpperCase()}`;
}

function mapRawOrder(raw: RawOrder): Order {
  return {
    id: raw.id,
    shopId: raw.store_id,
    shopName: raw.store_name ?? null,
    shopAddress: raw.store_address ?? null,
    shopCity: raw.store_city ?? null,
    status: raw.status as Order["status"],
    totalAmountTnd: raw.total_tnd,
    pickupSlot: raw.pickup_slot
      ? {
          id: raw.pickup_slot.id,
          startsAt: raw.pickup_slot.starts_at,
          endsAt: raw.pickup_slot.ends_at,
          capacity: null,
          available: true,
        }
      : null,
    submittedAt: null,
    acceptedAt: null,
    readyAt: null,
    completedAt: null,
    rejectionReason: null,
    code: deriveCode(raw.id),
    customerNote: raw.notes ?? null,
    lines: [],
    pickupCode: raw.pickup_code ?? null,
  };
}

function mapRawPickupSession(raw: RawPickupSession): PickupSession {
  return {
    id: raw.id,
    token: raw.token,
    expiresAt: raw.expires_at,
    isUsed: raw.is_used,
    isExpired: raw.is_expired,
    qrPayload: raw.qr_payload,
  };
}

function mapRawCustomerPickupSessionConfirmation(
  raw: RawCustomerPickupSessionConfirmation,
): CustomerPickupSessionConfirmation {
  return {
    id: raw.id,
    orderId: raw.order_id,
    orderStatus: raw.order_status,
    scannedAt: raw.scanned_at,
    merchantConfirmedAt: raw.merchant_confirmed_at,
    customerConfirmedAt: raw.customer_confirmed_at,
    isUsed: raw.is_used,
    isCompleted: raw.is_completed,
  };
}

function mapRawCustomerOrderStatusSnapshot(
  raw: RawCustomerOrderStatusSnapshot,
): CustomerOrderStatusSnapshot {
  return {
    orderId: raw.order_id,
    status: raw.status,
    statusLabelFr: raw.status_label_fr,
    statusLabelAr: raw.status_label_ar,
    updatedAt: raw.updated_at,
    pickupSession: {
      exists: raw.pickup_session.exists,
      isScanned: raw.pickup_session.is_scanned,
      merchantConfirmed: raw.pickup_session.merchant_confirmed,
      customerConfirmed: raw.pickup_session.customer_confirmed,
      isUsed: raw.pickup_session.is_used,
      forceCompletedByMerchant: raw.pickup_session.force_completed_by_merchant,
    },
  };
}

export async function listOrders(): Promise<Order[]> {
  if (USE_MOCKS) {
    return mockDelay([MOCK_ORDER]);
  }
  const { data } = await apiClient.get<{ "hydra:member": RawOrder[] }>("/api/me/orders");
  return (data["hydra:member"] ?? []).map(mapRawOrder);
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
    if (status === 404) return null;
    throw err;
  }
}

export async function getPickupSession(orderId: string): Promise<PickupSession | null> {
  if (USE_MOCKS) {
    if (orderId === MOCK_ORDER.id || orderId === MOCK_ORDER.code) {
      return mockDelay({
        id: "pickup-session-demo",
        token: MOCK_PICKUP_SESSION_TOKEN,
        expiresAt: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString(),
        isUsed: false,
        isExpired: false,
        qrPayload: MOCK_PICKUP_SESSION_TOKEN,
      });
    }
    return mockDelay(null);
  }
  try {
    const { data } = await apiClient.get<RawPickupSession>(
      `/api/me/orders/${orderId}/pickup-session`,
    );
    return mapRawPickupSession(data);
  } catch (err) {
    const status = (err as { response?: { status?: number } }).response?.status;
    if (status === 404) return null;
    throw err;
  }
}

export async function getOrderStatus(
  orderId: string,
): Promise<CustomerOrderStatusSnapshot | null> {
  if (USE_MOCKS) {
    if (orderId === MOCK_ORDER.id || orderId === MOCK_ORDER.code) {
      return mockDelay({
        orderId: MOCK_ORDER.id,
        status: MOCK_ORDER.status,
        statusLabelFr: "Prête à retirer",
        statusLabelAr: "Ready for pickup AR",
        updatedAt: new Date().toISOString(),
        pickupSession: {
          exists: true,
          isScanned: false,
          merchantConfirmed: false,
          customerConfirmed: false,
          isUsed: false,
          forceCompletedByMerchant: false,
        },
      });
    }
    return mockDelay(null);
  }
  try {
    const { data } = await apiClient.get<RawCustomerOrderStatusSnapshot>(
      `/api/me/orders/${orderId}/status`,
    );
    return mapRawCustomerOrderStatusSnapshot(data);
  } catch (err) {
    const status = (err as { response?: { status?: number } }).response?.status;
    if (status === 404) return null;
    throw err;
  }
}

export async function confirmCustomerPickupSession(
  sessionId: string,
): Promise<CustomerPickupSessionConfirmation> {
  if (USE_MOCKS) {
    return mockDelay({
      id: sessionId,
      orderId: MOCK_ORDER.id,
      orderStatus: "pickup_pending",
      scannedAt: new Date().toISOString(),
      merchantConfirmedAt: null,
      customerConfirmedAt: new Date().toISOString(),
      isUsed: false,
      isCompleted: false,
    });
  }

  const { data } = await apiClient.patch<RawCustomerPickupSessionConfirmation>(
    `/api/me/pickup-sessions/${sessionId}/confirm`,
    {},
  );
  return mapRawCustomerPickupSessionConfirmation(data);
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
