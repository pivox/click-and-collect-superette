import type { Order } from "@/types";
import { MOCK_PRODUCTS } from "./products.mock";
import { MOCK_SLOTS_TODAY } from "./slots.mock";

function makeSimpleOrder(
  id: string,
  code: string,
  status: Order["status"],
  totalAmountTnd: string,
): Order {
  return {
    id,
    code,
    shopId: "shop-el-amel",
    shopName: "Superette El Amel",
    status,
    totalAmountTnd,
    pickupSlot: MOCK_SLOTS_TODAY[0] ?? null,
    submittedAt: new Date(Date.now() - 2 * 24 * 60 * 60 * 1000).toISOString(),
    acceptedAt: null,
    readyAt: null,
    completedAt: null,
    rejectionReason: null,
    customerNote: null,
    lines: [],
    pickupCode: null,
    kadhiaId: null,
  };
}

/** Mock order — shape matches future GET /orders/{id} response. */
export const MOCK_ORDER: Order = {
  id: "ord-4821",
  code: "CMD-4821",
  shopId: "shop-el-amel",
  status: "ready",
  totalAmountTnd: "10.550",
  pickupSlot: MOCK_SLOTS_TODAY[1],
  submittedAt: new Date(Date.now() - 60 * 60 * 1000).toISOString(),
  acceptedAt: new Date(Date.now() - 50 * 60 * 1000).toISOString(),
  readyAt: new Date(Date.now() - 5 * 60 * 1000).toISOString(),
  completedAt: null,
  rejectionReason: null,
  customerNote:
    "Si un produit est absent, remplacer par une marque proche.",
  lines: [
    {
      id: "line-1",
      productOffer: MOCK_PRODUCTS[0],
      quantity: 2,
      unitPriceTnd: "3.000",
      lineTotalTnd: "6.000",
    },
    {
      id: "line-2",
      productOffer: MOCK_PRODUCTS[1],
      quantity: 1,
      unitPriceTnd: "1.850",
      lineTotalTnd: "1.850",
    },
    {
      id: "line-3",
      productOffer: MOCK_PRODUCTS[2],
      quantity: 1,
      unitPriceTnd: "2.700",
      lineTotalTnd: "2.700",
    },
  ],
  pickupCode: "4281",
};

export const MOCK_ORDER_SUBMITTED: Order = {
  id: "ord-submitted-1",
  code: "CMD-SUBM-1",
  shopId: "shop-el-amel",
  shopName: "Superette El Amel",
  status: "submitted",
  totalAmountTnd: "7.700",
  pickupSlot: MOCK_SLOTS_TODAY[2] ?? MOCK_SLOTS_TODAY[0],
  submittedAt: new Date(Date.now() - 10 * 60 * 1000).toISOString(),
  acceptedAt: null,
  readyAt: null,
  completedAt: null,
  rejectionReason: null,
  customerNote: null,
  lines: [
    {
      id: "line-s1",
      productOffer: MOCK_PRODUCTS[0],
      quantity: 2,
      unitPriceTnd: "3.000",
      lineTotalTnd: "6.000",
    },
    {
      id: "line-s2",
      productOffer: MOCK_PRODUCTS[2],
      quantity: 1,
      unitPriceTnd: "1.700",
      lineTotalTnd: "1.700",
    },
  ],
  pickupCode: null,
};

export const MOCK_ORDER_PARTIALLY_ACCEPTED: Order = {
  id: "ord-partial-1",
  code: "CMD-PART-1",
  shopId: "shop-el-amel",
  shopName: "Superette El Amel",
  kadhiaId: "kdh-demo-partial",
  status: "partially_accepted",
  totalAmountTnd: "4.850",
  pickupSlot: MOCK_SLOTS_TODAY[0],
  submittedAt: new Date(Date.now() - 30 * 60 * 1000).toISOString(),
  acceptedAt: null,
  readyAt: null,
  completedAt: null,
  rejectionReason: null,
  customerNote: null,
  lines: [
    {
      id: "line-p1",
      productOffer: MOCK_PRODUCTS[0],
      quantity: 1,
      unitPriceTnd: "3.000",
      lineTotalTnd: "3.000",
    },
    {
      id: "line-p2",
      productOffer: MOCK_PRODUCTS[1],
      quantity: 1,
      unitPriceTnd: "1.850",
      lineTotalTnd: "1.850",
    },
  ],
  pickupCode: null,
};

export const MOCK_ORDERS_ALL: Order[] = [
  MOCK_ORDER,
  MOCK_ORDER_SUBMITTED,
  MOCK_ORDER_PARTIALLY_ACCEPTED,
  makeSimpleOrder("ord-acc-1", "CMD-ACC-1", "accepted", "6.200"),
  makeSimpleOrder("ord-acc-2", "CMD-ACC-2", "accepted", "3.450"),
  makeSimpleOrder("ord-prep-1", "CMD-PREP-1", "preparing", "9.100"),
  makeSimpleOrder("ord-prep-2", "CMD-PREP-2", "preparing", "5.750"),
  makeSimpleOrder("ord-comp-1", "CMD-COMP-1", "completed", "12.000"),
  makeSimpleOrder("ord-comp-2", "CMD-COMP-2", "completed", "8.300"),
  makeSimpleOrder("ord-rej-1", "CMD-REJ-1", "rejected", "4.000"),
  makeSimpleOrder("ord-can-1", "CMD-CAN-1", "cancelled", "7.600"),
  makeSimpleOrder("ord-sub-2", "CMD-SUBM-2", "submitted", "2.900"),
];
