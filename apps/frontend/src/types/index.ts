/**
 * Domain types shared between frontend and backend.
 *
 * Aligned with apps/backend API contract (see docs/architecture/api-contract.md).
 * The shape of these types is what the mock services return today AND what
 * the real API will return tomorrow — services in lib/services swap their
 * implementation, not their signature.
 */

export type OrderStatus =
  | "draft"
  | "submitted"
  | "accepted"
  | "partially_accepted"
  | "rejected"
  | "preparing"
  | "ready"
  | "pickup_pending"
  | "completed"
  | "cancelled";

export type UserRole = "ROLE_CUSTOMER" | "ROLE_MERCHANT" | "ROLE_ADMIN";

export interface AuthUser {
  id: string;
  email: string;
  name: string;
  roles: UserRole[];
}

export interface Shop {
  id: string;
  name: string;
  slug: string;
  address: string | null;
  city: string | null;
  phone: string | null;
  isActive: boolean;
  /** UI extras — optional, populated by mock data, may come from a /stores/{id}/summary endpoint */
  distanceKm?: number;
  rating?: number;
  opensAt?: string;
  closesAt?: string;
  nextPickupAt?: string | null;
  logoLetter?: string;
}

export type ProductCategory = string;

export interface ProductOffer {
  id: string;
  productReferenceId: string;
  nameFr: string;
  nameAr: string | null;
  brand: string;
  volume: number | null;
  unit: string | null;
  priceTnd: string;
  isAvailable: boolean;
  photoUrl: string | null;
  category: ProductCategory;
  categoryNameFr?: string | null;
  categoryNameAr?: string | null;
  /** Emoji fallback used by the prototype when no photo is provided. */
  emoji?: string;
}

export interface KadhiaLine {
  id: string;
  productOffer: ProductOffer;
  quantity: number;
  unitPriceTnd: string;
  lineTotalTnd: string;
}

export interface Kadhia {
  id: string;
  shopId: string;
  shopName?: string | null;
  shopAddress?: string | null;
  shopCity?: string | null;
  status: OrderStatus;
  lines: KadhiaLine[];
  totalTnd: string;
  orderId?: string | null;
  notes?: string | null;
}

export interface PickupSlot {
  id: string;
  startsAt: string;
  endsAt: string;
  capacity: number | null;
  available: boolean;
  /** Optional label like "Complet bientôt" / "Disponible" */
  label?: string;
}

export interface Order {
  id: string;
  shopId: string;
  shopName?: string | null;
  shopAddress?: string | null;
  shopCity?: string | null;
  status: OrderStatus;
  totalAmountTnd: string;
  pickupSlot: PickupSlot | null;
  submittedAt: string | null;
  acceptedAt: string | null;
  readyAt: string | null;
  completedAt: string | null;
  rejectionReason: string | null;
  /** Order code shown to user, e.g. "#0042" or a legacy short code. */
  code: string;
  lines: KadhiaLine[];
  /** Optional note left by customer to the merchant. */
  customerNote?: string | null;
  /** 4-digit pickup code, visible only when status === 'ready'. */
  pickupCode?: string | null;
}

export interface PickupSession {
  id: string;
  token: string;
  expiresAt: string;
  isUsed: boolean;
  isExpired: boolean;
  qrPayload: string;
}

export interface CustomerOrderPickupSessionStatus {
  exists: boolean;
  isScanned: boolean;
  merchantConfirmed: boolean;
  customerConfirmed: boolean;
  isUsed: boolean;
  forceCompletedByMerchant: boolean;
}

export interface CustomerOrderStatusSnapshot {
  orderId: string;
  status: OrderStatus;
  statusLabelFr: string;
  statusLabelAr: string;
  updatedAt: string;
  pickupSession: CustomerOrderPickupSessionStatus;
}

export interface CustomerPickupSessionConfirmation {
  id: string;
  orderId: string;
  orderStatus: OrderStatus;
  scannedAt: string;
  merchantConfirmedAt: string | null;
  customerConfirmedAt: string | null;
  isUsed: boolean;
  isCompleted: boolean;
}

/** Step shown on the customer order tracking timeline. */
export interface TimelineStep {
  key: "submitted" | "accepted" | "preparing" | "ready" | "completed";
  label: string;
  hint?: string;
  state: "done" | "current" | "todo";
}

// snake_case properties match the backend API contract (SerializedName annotations)
export interface StoreSearchItem {
  store_id: string;
  name: string;
  slug: string;
  city: string | null;
  country: string;
  is_active: boolean;
}

export interface StoreSearchResult {
  items: StoreSearchItem[];
  total: number;
}
