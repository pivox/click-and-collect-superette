import type { Order } from "@/types";
import { MOCK_PRODUCTS } from "./products.mock";
import { MOCK_SLOTS_TODAY } from "./slots.mock";

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
};
