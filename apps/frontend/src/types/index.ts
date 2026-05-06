export type OrderStatus =
  | 'draft'
  | 'submitted'
  | 'accepted'
  | 'rejected'
  | 'preparing'
  | 'ready'
  | 'pickup_pending'
  | 'completed'
  | 'cancelled';

export type UserRole = 'ROLE_CUSTOMER' | 'ROLE_MERCHANT' | 'ROLE_ADMIN';

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
}

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
  category: string;
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
  status: OrderStatus;
  lines: KadhiaLine[];
  totalTnd: string;
}

export interface PickupSlot {
  id: string;
  startsAt: string;
  endsAt: string;
  capacity: number | null;
  available: boolean;
}

export interface Order {
  id: string;
  shopId: string;
  status: OrderStatus;
  totalAmountTnd: string;
  pickupSlot: PickupSlot | null;
  submittedAt: string | null;
  acceptedAt: string | null;
  readyAt: string | null;
  completedAt: string | null;
  rejectionReason: string | null;
}
