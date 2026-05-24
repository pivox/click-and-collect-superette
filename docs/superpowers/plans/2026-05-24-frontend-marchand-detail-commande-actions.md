# Frontend Marchand Détail Commande Actions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the merchant order detail screen and merchant actions from `submitted` to `ready`, including partial acceptance, without adding pickup scan or withdrawal validation.

**Architecture:** Keep `/merchant/commandes` as a compact operational list and make `/merchant/commandes/[orderId]` the single action workspace. Extend the existing PR #134 merchant services/types instead of creating a second merchant frontend layer. The backend remains the source of truth: each mutation reloads the order detail.

**Tech Stack:** Next.js 14 App Router, React 18, TypeScript, Vitest, Testing Library, Axios `apiClient`, Symfony/API Platform backend contracts.

---

## Preconditions

- Start from `main` after PR #134 is present locally. If the checkout does not contain `apps/frontend/src/app/merchant`, update it first:

```bash
git fetch origin
git rebase origin/main
```

Expected: local branch contains:

- `apps/frontend/src/app/merchant/page.tsx`
- `apps/frontend/src/app/merchant/commandes/page.tsx`
- `apps/frontend/src/lib/auth/MerchantAuthContext.tsx`
- `apps/frontend/src/lib/services/merchant-orders.service.ts`
- `apps/frontend/src/lib/types/merchant.types.ts`

Do not include paiement en ligne, livraison, fidélité, marketplace multi-marchands, scan QR de retrait, `pickup_pending`, confirmation client, confirmation marchand de retrait, or force completion.

---

## File Structure

### Modify

- `apps/frontend/src/lib/types/merchant.types.ts`
  - Add detail, line, mutation payload, and mutation response types.
- `apps/frontend/src/lib/services/merchant-orders.service.ts`
  - Add detail loader and actions using the existing `/api/merchant/stores/{storeId}/orders/*` backend contract.
- `apps/frontend/src/app/merchant/commandes/page.tsx`
  - Convert each order row into a link to `/merchant/commandes/{orderId}`.
- `apps/frontend/src/tests/merchant.services.test.ts`
  - Cover detail and mutation service endpoint contracts.
- `apps/frontend/src/tests/merchant.commandes.test.tsx`
  - Cover the order detail links.

### Create

- `apps/frontend/src/components/merchant/OrderStatusBadge.tsx`
  - Small status badge shared by list and detail.
- `apps/frontend/src/components/merchant/RejectOrderDialog.tsx`
  - Refusal dialog with optional reason up to 500 chars.
- `apps/frontend/src/components/merchant/PartialAcceptDialog.tsx`
  - Partial acceptance dialog with line selection and notes.
- `apps/frontend/src/app/merchant/commandes/[orderId]/page.tsx`
  - Detail page, status-aware actions, line preparation controls.
- `apps/frontend/src/tests/merchant.order-detail.test.tsx`
  - UI tests for actions by status, partial acceptance validation, and no pickup action in `ready`.

### No Backend Changes Expected

Backend contracts already exist:

- `GET /api/merchant/stores/{storeId}/orders/{orderId}`
- `POST /api/merchant/stores/{storeId}/orders/{orderId}/accept`
- `POST /api/merchant/stores/{storeId}/orders/{orderId}/reject` with `{ "reason": string | null }`
- `POST /api/merchant/stores/{storeId}/orders/{orderId}/partially-accept` with `{ "rejected_merchant_product_ids": string[], "notes": string | null }`
- `POST /api/merchant/stores/{storeId}/orders/{orderId}/start-preparation`
- `PATCH /api/merchant/stores/{storeId}/orders/{orderId}/lines/{merchantProductId}/preparation` with `{ "prepared": boolean }`
- `POST /api/merchant/stores/{storeId}/orders/{orderId}/mark-ready`

---

### Task 1: Merchant Order Types and Services

**Files:**
- Modify: `apps/frontend/src/lib/types/merchant.types.ts`
- Modify: `apps/frontend/src/lib/services/merchant-orders.service.ts`
- Modify: `apps/frontend/src/tests/merchant.services.test.ts`

- [ ] **Step 1: Write failing service tests**

Append this test to `apps/frontend/src/tests/merchant.services.test.ts`. If the file already has a single `describe('merchant services', ...)`, place this `it(...)` inside it after the existing list test.

```ts
it('loads order detail and calls merchant order mutations', async () => {
  vi.mocked(apiClient.get).mockResolvedValueOnce({
    data: {
      id: 'order-1',
      store_id: 'store-1',
      status: 'submitted',
      total_tnd: '18.500',
      pickup_slot: {
        id: 'slot-1',
        starts_at: '2026-05-24T10:00:00+01:00',
        ends_at: '2026-05-24T11:00:00+01:00',
      },
      notes: 'Sans sachet.',
      customer_name: 'Fatma Ben Ali',
      customer_phone: '+21620111222',
      rejection_reason: null,
      lines: [
        {
          merchant_product_id: 'mp-1',
          product_name: 'Lait Vitalait 1L',
          quantity: 2,
          unit_price_tnd: '1.700',
          line_total_tnd: '3.400',
          prepared: false,
        },
      ],
      created_at: '2026-05-24T08:00:00+01:00',
      updated_at: '2026-05-24T08:00:00+01:00',
    },
  });
  vi.mocked(apiClient.post).mockResolvedValue({ data: { id: 'order-1', status: 'accepted' } });
  vi.mocked(apiClient.patch).mockResolvedValue({ data: { id: 'order-1', status: 'preparing' } });

  const detail = await getMerchantOrder('store-1', 'order-1');
  await acceptMerchantOrder('store-1', 'order-1');
  await rejectMerchantOrder('store-1', 'order-1', { reason: 'Produit indisponible' });
  await partiallyAcceptMerchantOrder('store-1', 'order-1', {
    rejected_merchant_product_ids: ['mp-1'],
    notes: 'Rupture.',
  });
  await startMerchantOrderPreparation('store-1', 'order-1');
  await setMerchantOrderLinePrepared('store-1', 'order-1', 'mp-1', { prepared: true });
  await markMerchantOrderReady('store-1', 'order-1');

  expect(detail.lines[0].product_name).toBe('Lait Vitalait 1L');
  expect(apiClient.get).toHaveBeenCalledWith('/api/merchant/stores/store-1/orders/order-1');
  expect(apiClient.post).toHaveBeenNthCalledWith(
    1,
    '/api/merchant/stores/store-1/orders/order-1/accept',
  );
  expect(apiClient.post).toHaveBeenNthCalledWith(
    2,
    '/api/merchant/stores/store-1/orders/order-1/reject',
    { reason: 'Produit indisponible' },
  );
  expect(apiClient.post).toHaveBeenNthCalledWith(
    3,
    '/api/merchant/stores/store-1/orders/order-1/partially-accept',
    { rejected_merchant_product_ids: ['mp-1'], notes: 'Rupture.' },
  );
  expect(apiClient.post).toHaveBeenNthCalledWith(
    4,
    '/api/merchant/stores/store-1/orders/order-1/start-preparation',
  );
  expect(apiClient.patch).toHaveBeenCalledWith(
    '/api/merchant/stores/store-1/orders/order-1/lines/mp-1/preparation',
    { prepared: true },
  );
  expect(apiClient.post).toHaveBeenNthCalledWith(
    5,
    '/api/merchant/stores/store-1/orders/order-1/mark-ready',
  );
});
```

Update the import block in the same test file:

```ts
import {
  acceptMerchantOrder,
  getMerchantOrder,
  listMerchantOrderHistory,
  listMerchantOrders,
  markMerchantOrderReady,
  partiallyAcceptMerchantOrder,
  rejectMerchantOrder,
  setMerchantOrderLinePrepared,
  startMerchantOrderPreparation,
} from '@/lib/services/merchant-orders.service';
```

Update the `apiClient` mock to include `patch`:

```ts
vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
  },
}));
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.services.test.ts
```

Expected: FAIL with missing exports such as `getMerchantOrder` or `acceptMerchantOrder`.

- [ ] **Step 3: Add merchant order detail and payload types**

Append these types to `apps/frontend/src/lib/types/merchant.types.ts`:

```ts
export type MerchantOrderStatus =
  | 'submitted'
  | 'accepted'
  | 'partially_accepted'
  | 'rejected'
  | 'preparing'
  | 'ready'
  | 'pickup_pending'
  | 'completed'
  | 'cancelled';

export interface MerchantOrderDetailPickupSlot {
  id: string;
  starts_at: string;
  ends_at: string;
}

export interface MerchantOrderLine {
  merchant_product_id: string;
  product_name: string | null;
  quantity: number;
  unit_price_tnd: string;
  line_total_tnd: string;
  prepared: boolean;
}

export interface MerchantOrderDetail {
  id: string;
  store_id: string;
  status: MerchantOrderStatus;
  total_tnd: string;
  pickup_slot: MerchantOrderDetailPickupSlot | null;
  notes: string | null;
  lines: MerchantOrderLine[];
  customer_name: string | null;
  customer_phone: string | null;
  rejection_reason: string | null;
  created_at: string;
  updated_at: string;
  order_number?: string;
}

export interface RejectMerchantOrderPayload {
  reason: string | null;
}

export interface PartiallyAcceptMerchantOrderPayload {
  rejected_merchant_product_ids: string[];
  notes: string | null;
}

export interface SetMerchantOrderLinePreparedPayload {
  prepared: boolean;
}

export interface MerchantOrderMutationResult {
  id: string;
  store_id?: string;
  status: MerchantOrderStatus;
  total_tnd?: string;
  rejection_reason?: string | null;
  created_at?: string;
  updated_at?: string;
}
```

- [ ] **Step 4: Add service functions**

Modify `apps/frontend/src/lib/services/merchant-orders.service.ts` so its imports include the new types:

```ts
import type {
  MerchantOrderDetail,
  MerchantOrderHistoryList,
  MerchantOrderList,
  MerchantOrderMutationResult,
  PartiallyAcceptMerchantOrderPayload,
  RejectMerchantOrderPayload,
  SetMerchantOrderLinePreparedPayload,
} from '@/lib/types/merchant.types';
```

Append these functions to the same file:

```ts
export async function getMerchantOrder(
  storeId: string,
  orderId: string,
): Promise<MerchantOrderDetail> {
  const { data } = await apiClient.get<MerchantOrderDetail>(
    `/api/merchant/stores/${storeId}/orders/${orderId}`,
  );
  return data;
}

export async function acceptMerchantOrder(
  storeId: string,
  orderId: string,
): Promise<MerchantOrderMutationResult> {
  const { data } = await apiClient.post<MerchantOrderMutationResult>(
    `/api/merchant/stores/${storeId}/orders/${orderId}/accept`,
  );
  return data;
}

export async function rejectMerchantOrder(
  storeId: string,
  orderId: string,
  payload: RejectMerchantOrderPayload,
): Promise<MerchantOrderMutationResult> {
  const { data } = await apiClient.post<MerchantOrderMutationResult>(
    `/api/merchant/stores/${storeId}/orders/${orderId}/reject`,
    payload,
  );
  return data;
}

export async function partiallyAcceptMerchantOrder(
  storeId: string,
  orderId: string,
  payload: PartiallyAcceptMerchantOrderPayload,
): Promise<MerchantOrderMutationResult> {
  const { data } = await apiClient.post<MerchantOrderMutationResult>(
    `/api/merchant/stores/${storeId}/orders/${orderId}/partially-accept`,
    payload,
  );
  return data;
}

export async function startMerchantOrderPreparation(
  storeId: string,
  orderId: string,
): Promise<MerchantOrderMutationResult> {
  const { data } = await apiClient.post<MerchantOrderMutationResult>(
    `/api/merchant/stores/${storeId}/orders/${orderId}/start-preparation`,
  );
  return data;
}

export async function setMerchantOrderLinePrepared(
  storeId: string,
  orderId: string,
  merchantProductId: string,
  payload: SetMerchantOrderLinePreparedPayload,
): Promise<MerchantOrderDetail> {
  const { data } = await apiClient.patch<MerchantOrderDetail>(
    `/api/merchant/stores/${storeId}/orders/${orderId}/lines/${merchantProductId}/preparation`,
    payload,
  );
  return data;
}

export async function markMerchantOrderReady(
  storeId: string,
  orderId: string,
): Promise<MerchantOrderMutationResult> {
  const { data } = await apiClient.post<MerchantOrderMutationResult>(
    `/api/merchant/stores/${storeId}/orders/${orderId}/mark-ready`,
  );
  return data;
}
```

- [ ] **Step 5: Run test to verify it passes**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.services.test.ts
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add apps/frontend/src/lib/types/merchant.types.ts apps/frontend/src/lib/services/merchant-orders.service.ts apps/frontend/src/tests/merchant.services.test.ts
git commit -m "feat(merchant): add order action services"
```

---

### Task 2: Link Commandes List to Detail

**Files:**
- Modify: `apps/frontend/src/app/merchant/commandes/page.tsx`
- Create: `apps/frontend/src/components/merchant/OrderStatusBadge.tsx`
- Modify: `apps/frontend/src/tests/merchant.commandes.test.tsx`

- [ ] **Step 1: Write failing list navigation test**

In `apps/frontend/src/tests/merchant.commandes.test.tsx`, extend the existing test after the existing assertions:

```ts
const detailLink = await screen.findByRole('link', { name: /voir la commande order-1/i });
expect(detailLink).toHaveAttribute('href', '/merchant/commandes/order-1');
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.commandes.test.tsx
```

Expected: FAIL because the list row is not a link yet.

- [ ] **Step 3: Add shared status badge**

Create `apps/frontend/src/components/merchant/OrderStatusBadge.tsx`:

```tsx
import type { MerchantOrderStatus } from '@/lib/types/merchant.types';

const STATUS_LABELS: Record<string, string> = {
  submitted: 'Soumise',
  accepted: 'Acceptée',
  partially_accepted: 'Acceptée partiellement',
  rejected: 'Refusée',
  preparing: 'En préparation',
  ready: 'Prête',
  pickup_pending: 'Retrait en cours',
  completed: 'Finalisée',
  cancelled: 'Annulée',
};

export function OrderStatusBadge({ status }: { status: MerchantOrderStatus | string }) {
  const label = STATUS_LABELS[status] ?? status;

  return (
    <span className="rounded-full bg-soft px-2 py-1 text-xs font-bold text-muted">
      {label}
    </span>
  );
}
```

- [ ] **Step 4: Update list rows into links**

Modify `apps/frontend/src/app/merchant/commandes/page.tsx`:

```tsx
import Link from 'next/link';
import { OrderStatusBadge } from '@/components/merchant/OrderStatusBadge';
```

Replace each order `<article ...>` block inside `orders.items.map` with:

```tsx
<Link
  key={order.id}
  href={`/merchant/commandes/${order.id}`}
  aria-label={`Voir la commande ${order.order_number ?? order.id}`}
  className="grid gap-3 p-5 transition hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary md:grid-cols-[1fr_auto]"
>
  <div>
    <div className="flex flex-wrap items-center gap-2">
      <strong>{order.order_number ?? order.id}</strong>
      <OrderStatusBadge status={order.status} />
    </div>
    <p className="mt-2 text-sm text-muted">
      {order.line_count} produits
      {order.pickup_slot?.starts_at
        ? ` · rendez-vous ${formatTime(order.pickup_slot.starts_at)}`
        : ''}
    </p>
  </div>
  <strong className="text-right text-lg">{formatTnd(order.total_tnd)}</strong>
</Link>
```

Also update the description copy:

```tsx
<p className="mt-1 text-sm text-muted">
  Ouvre une commande pour traiter la Kadhia jusqu&apos;à sa préparation.
</p>
```

- [ ] **Step 5: Run test to verify it passes**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.commandes.test.tsx
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add apps/frontend/src/app/merchant/commandes/page.tsx apps/frontend/src/components/merchant/OrderStatusBadge.tsx apps/frontend/src/tests/merchant.commandes.test.tsx
git commit -m "feat(merchant): link orders to detail"
```

---

### Task 3: Order Detail Page Status Actions

**Files:**
- Create: `apps/frontend/src/app/merchant/commandes/[orderId]/page.tsx`
- Create: `apps/frontend/src/tests/merchant.order-detail.test.tsx`

- [ ] **Step 1: Write failing detail action tests**

Create `apps/frontend/src/tests/merchant.order-detail.test.tsx`:

```tsx
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import MerchantOrderDetailPage from '@/app/merchant/commandes/[orderId]/page';
import {
  acceptMerchantOrder,
  getMerchantOrder,
  markMerchantOrderReady,
  setMerchantOrderLinePrepared,
  startMerchantOrderPreparation,
} from '@/lib/services/merchant-orders.service';
import type { MerchantOrderDetail } from '@/lib/types/merchant.types';

const merchantContext = {
  merchant: {
    store: { id: 'store-1', name: 'Supérette Ezzahra', active: true },
  },
};

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => merchantContext,
}));

vi.mock('@/lib/services/merchant-orders.service', () => ({
  acceptMerchantOrder: vi.fn(),
  getMerchantOrder: vi.fn(),
  markMerchantOrderReady: vi.fn(),
  partiallyAcceptMerchantOrder: vi.fn(),
  rejectMerchantOrder: vi.fn(),
  setMerchantOrderLinePrepared: vi.fn(),
  startMerchantOrderPreparation: vi.fn(),
}));

function makeOrder(status: MerchantOrderDetail['status']): MerchantOrderDetail {
  return {
    id: 'order-1',
    store_id: 'store-1',
    status,
    total_tnd: '18.500',
    pickup_slot: {
      id: 'slot-1',
      starts_at: '2026-05-24T10:00:00+01:00',
      ends_at: '2026-05-24T11:00:00+01:00',
    },
    notes: 'Sans sachet.',
    lines: [
      {
        merchant_product_id: 'mp-1',
        product_name: 'Lait Vitalait 1L',
        quantity: 2,
        unit_price_tnd: '1.700',
        line_total_tnd: '3.400',
        prepared: false,
      },
    ],
    customer_name: 'Fatma Ben Ali',
    customer_phone: '+21620111222',
    rejection_reason: null,
    created_at: '2026-05-24T08:00:00+01:00',
    updated_at: '2026-05-24T08:00:00+01:00',
  };
}

describe('MerchantOrderDetailPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows submitted actions and reloads after accept', async () => {
    vi.mocked(getMerchantOrder)
      .mockResolvedValueOnce(makeOrder('submitted'))
      .mockResolvedValueOnce(makeOrder('accepted'));
    vi.mocked(acceptMerchantOrder).mockResolvedValue({ id: 'order-1', status: 'accepted' });

    render(React.createElement(MerchantOrderDetailPage, { params: { orderId: 'order-1' } }));

    expect(await screen.findByRole('heading', { name: /commande order-1/i })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Accepter' }));

    await waitFor(() => expect(acceptMerchantOrder).toHaveBeenCalledWith('store-1', 'order-1'));
    expect(getMerchantOrder).toHaveBeenCalledTimes(2);
  });

  it('shows preparation action only for accepted orders', async () => {
    vi.mocked(getMerchantOrder)
      .mockResolvedValueOnce(makeOrder('accepted'))
      .mockResolvedValueOnce(makeOrder('preparing'));
    vi.mocked(startMerchantOrderPreparation).mockResolvedValue({ id: 'order-1', status: 'preparing' });

    render(React.createElement(MerchantOrderDetailPage, { params: { orderId: 'order-1' } }));

    fireEvent.click(await screen.findByRole('button', { name: 'Démarrer préparation' }));

    await waitFor(() =>
      expect(startMerchantOrderPreparation).toHaveBeenCalledWith('store-1', 'order-1'),
    );
  });

  it('shows line preparation and ready action only for preparing orders', async () => {
    vi.mocked(getMerchantOrder)
      .mockResolvedValueOnce(makeOrder('preparing'))
      .mockResolvedValueOnce({ ...makeOrder('preparing'), lines: [{ ...makeOrder('preparing').lines[0], prepared: true }] })
      .mockResolvedValueOnce(makeOrder('ready'));
    vi.mocked(setMerchantOrderLinePrepared).mockResolvedValue({
      ...makeOrder('preparing'),
      lines: [{ ...makeOrder('preparing').lines[0], prepared: true }],
    });
    vi.mocked(markMerchantOrderReady).mockResolvedValue({ id: 'order-1', status: 'ready' });

    render(React.createElement(MerchantOrderDetailPage, { params: { orderId: 'order-1' } }));

    fireEvent.click(await screen.findByRole('checkbox', { name: /marquer lait vitalait 1l préparé/i }));
    await waitFor(() =>
      expect(setMerchantOrderLinePrepared).toHaveBeenCalledWith('store-1', 'order-1', 'mp-1', {
        prepared: true,
      }),
    );

    fireEvent.click(screen.getByRole('button', { name: 'Commande prête' }));
    await waitFor(() => expect(markMerchantOrderReady).toHaveBeenCalledWith('store-1', 'order-1'));
  });

  it('does not expose pickup actions for ready orders', async () => {
    vi.mocked(getMerchantOrder).mockResolvedValue(makeOrder('ready'));

    render(React.createElement(MerchantOrderDetailPage, { params: { orderId: 'order-1' } }));

    expect(await screen.findByText('Commande prête pour le retrait.')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /scan/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /confirmer retrait/i })).not.toBeInTheDocument();
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.order-detail.test.tsx
```

Expected: FAIL because the detail page does not exist.

- [ ] **Step 3: Implement detail page**

Create `apps/frontend/src/app/merchant/commandes/[orderId]/page.tsx`:

```tsx
'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { OrderStatusBadge } from '@/components/merchant/OrderStatusBadge';
import { Button } from '@/components/ui/Button';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import { formatTime, formatTnd } from '@/lib/format';
import {
  acceptMerchantOrder,
  getMerchantOrder,
  markMerchantOrderReady,
  setMerchantOrderLinePrepared,
  startMerchantOrderPreparation,
} from '@/lib/services/merchant-orders.service';
import type { MerchantOrderDetail } from '@/lib/types/merchant.types';

interface PageProps {
  params: { orderId: string };
}

function apiErrorMessage(error: unknown): string {
  if (
    typeof error === 'object' &&
    error !== null &&
    'response' in error &&
    typeof (error as { response?: { data?: { detail?: unknown } } }).response?.data?.detail === 'string'
  ) {
    return (error as { response: { data: { detail: string } } }).response.data.detail;
  }
  return "L'action n'a pas pu être effectuée. Recharge la commande puis réessaie.";
}

export default function MerchantOrderDetailPage({ params }: PageProps) {
  const { merchant } = useMerchantAuth();
  const [order, setOrder] = useState<MerchantOrderDetail | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isMutating, setIsMutating] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadOrder = useCallback(async () => {
    if (!merchant) return;
    setIsLoading(true);
    setError(null);
    try {
      setOrder(await getMerchantOrder(merchant.store.id, params.orderId));
    } catch {
      setError('Impossible de charger cette commande.');
    } finally {
      setIsLoading(false);
    }
  }, [merchant, params.orderId]);

  useEffect(() => {
    void loadOrder();
  }, [loadOrder]);

  const runAction = async (action: () => Promise<unknown>) => {
    if (!merchant) return;
    setIsMutating(true);
    setError(null);
    try {
      await action();
      await loadOrder();
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setIsMutating(false);
    }
  };

  const togglePrepared = async (merchantProductId: string, prepared: boolean) => {
    await runAction(() =>
      setMerchantOrderLinePrepared(merchant!.store.id, params.orderId, merchantProductId, {
        prepared,
      }),
    );
  };

  if (isLoading) {
    return <p className="text-sm text-muted">Chargement de la commande...</p>;
  }

  if (!order) {
    return (
      <div>
        <p className="text-sm text-muted">Commande introuvable pour cette supérette.</p>
        <Button className="mt-4" variant="ghost" size="md" onClick={() => void loadOrder()}>
          Réessayer
        </Button>
      </div>
    );
  }

  return (
    <div>
      <Link href="/merchant/commandes" className="text-sm font-bold text-primary">
        Retour aux commandes
      </Link>

      <div className="mt-4 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h1 className="text-h1 font-black">Commande {order.order_number ?? order.id}</h1>
            <OrderStatusBadge status={order.status} />
          </div>
          <p className="mt-1 text-sm text-muted">
            Rendez-vous{' '}
            {order.pickup_slot
              ? `${formatTime(order.pickup_slot.starts_at)}-${formatTime(order.pickup_slot.ends_at)}`
              : 'non renseigné'}
          </p>
        </div>
        <strong className="text-h2 font-black">{formatTnd(order.total_tnd)}</strong>
      </div>

      {error && (
        <div className="mt-4 rounded-md bg-status-cancel-bg px-4 py-3 text-sm text-status-cancel">
          {error}
        </div>
      )}

      <section className="mt-5 grid gap-4 md:grid-cols-2">
        <div className="rounded-md bg-card p-5 shadow-card">
          <h2 className="font-black">Client</h2>
          <p className="mt-2 text-sm text-muted">{order.customer_name ?? 'Nom non renseigné'}</p>
          <p className="mt-1 text-sm text-muted">{order.customer_phone ?? 'Téléphone non renseigné'}</p>
        </div>
        <div className="rounded-md bg-card p-5 shadow-card">
          <h2 className="font-black">Notes client</h2>
          <p className="mt-2 text-sm text-muted">{order.notes ?? 'Aucune note.'}</p>
        </div>
      </section>

      <section className="mt-5 rounded-md bg-card shadow-card">
        <div className="border-b border-line p-5">
          <h2 className="text-lg font-black">Kadhia</h2>
        </div>
        {order.lines.length === 0 ? (
          <p className="p-5 text-sm text-muted">Aucune ligne dans cette Kadhia.</p>
        ) : (
          <div className="divide-y divide-line">
            {order.lines.map((line) => (
              <div key={line.merchant_product_id} className="grid gap-3 p-5 md:grid-cols-[1fr_auto]">
                <div>
                  <strong>{line.product_name ?? line.merchant_product_id}</strong>
                  <p className="mt-1 text-sm text-muted">
                    {line.quantity} x {formatTnd(line.unit_price_tnd)}
                  </p>
                  {order.status === 'preparing' && (
                    <label className="mt-3 flex items-center gap-2 text-sm font-bold">
                      <input
                        type="checkbox"
                        checked={line.prepared}
                        disabled={isMutating}
                        aria-label={`Marquer ${line.product_name ?? line.merchant_product_id} préparé`}
                        onChange={(event) =>
                          void togglePrepared(line.merchant_product_id, event.currentTarget.checked)
                        }
                      />
                      Ligne préparée
                    </label>
                  )}
                </div>
                <strong>{formatTnd(line.line_total_tnd)}</strong>
              </div>
            ))}
          </div>
        )}
      </section>

      <section className="mt-5 rounded-md bg-card p-5 shadow-card">
        <h2 className="text-lg font-black">Actions marchand</h2>
        <div className="mt-4 flex flex-wrap gap-3">
          {order.status === 'submitted' && (
            <Button
              size="md"
              disabled={isMutating}
              onClick={() =>
                void runAction(() => acceptMerchantOrder(merchant!.store.id, params.orderId))
              }
            >
              Accepter
            </Button>
          )}
          {order.status === 'accepted' && (
            <Button
              size="md"
              disabled={isMutating}
              onClick={() =>
                void runAction(() =>
                  startMerchantOrderPreparation(merchant!.store.id, params.orderId),
                )
              }
            >
              Démarrer préparation
            </Button>
          )}
          {order.status === 'preparing' && (
            <Button
              size="md"
              disabled={isMutating}
              onClick={() =>
                void runAction(() => markMerchantOrderReady(merchant!.store.id, params.orderId))
              }
            >
              Commande prête
            </Button>
          )}
          {order.status === 'partially_accepted' && (
            <p className="text-sm text-muted">
              Le client doit ajuster sa Kadhia et la re-soumettre avant la préparation.
            </p>
          )}
          {order.status === 'ready' && (
            <p className="text-sm font-bold text-primary">Commande prête pour le retrait.</p>
          )}
        </div>
      </section>
    </div>
  );
}
```

- [ ] **Step 4: Run detail test**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.order-detail.test.tsx
```

Expected: PASS for accept, preparation, line preparation, ready state.

- [ ] **Step 5: Commit**

```bash
git add apps/frontend/src/app/merchant/commandes/[orderId]/page.tsx apps/frontend/src/tests/merchant.order-detail.test.tsx
git commit -m "feat(merchant): add order detail actions"
```

---

### Task 4: Refuse and Partial Acceptance Dialogs

**Files:**
- Create: `apps/frontend/src/components/merchant/RejectOrderDialog.tsx`
- Create: `apps/frontend/src/components/merchant/PartialAcceptDialog.tsx`
- Modify: `apps/frontend/src/app/merchant/commandes/[orderId]/page.tsx`
- Modify: `apps/frontend/src/tests/merchant.order-detail.test.tsx`

- [ ] **Step 1: Add failing dialog tests**

Append these tests inside `describe('MerchantOrderDetailPage', ...)` in `apps/frontend/src/tests/merchant.order-detail.test.tsx`. Update the import from service mocks to include `partiallyAcceptMerchantOrder` and `rejectMerchantOrder`.

```tsx
it('rejects a submitted order with a reason and reloads', async () => {
  vi.mocked(getMerchantOrder)
    .mockResolvedValueOnce(makeOrder('submitted'))
    .mockResolvedValueOnce(makeOrder('rejected'));
  vi.mocked(rejectMerchantOrder).mockResolvedValue({ id: 'order-1', status: 'rejected' });

  render(React.createElement(MerchantOrderDetailPage, { params: { orderId: 'order-1' } }));

  fireEvent.click(await screen.findByRole('button', { name: 'Refuser' }));
  fireEvent.change(screen.getByLabelText('Motif de refus'), {
    target: { value: 'Produit indisponible' },
  });
  fireEvent.click(screen.getByRole('button', { name: 'Confirmer le refus' }));

  await waitFor(() =>
    expect(rejectMerchantOrder).toHaveBeenCalledWith('store-1', 'order-1', {
      reason: 'Produit indisponible',
    }),
  );
  expect(getMerchantOrder).toHaveBeenCalledTimes(2);
});

it('requires one accepted and one unavailable line before partial acceptance', async () => {
  vi.mocked(getMerchantOrder).mockResolvedValue({
    ...makeOrder('submitted'),
    lines: [
      makeOrder('submitted').lines[0],
      {
        merchant_product_id: 'mp-2',
        product_name: 'Eau minérale 1.5L',
        quantity: 1,
        unit_price_tnd: '0.900',
        line_total_tnd: '0.900',
        prepared: false,
      },
    ],
  });
  vi.mocked(partiallyAcceptMerchantOrder).mockResolvedValue({
    id: 'order-1',
    status: 'partially_accepted',
  });

  render(React.createElement(MerchantOrderDetailPage, { params: { orderId: 'order-1' } }));

  fireEvent.click(await screen.findByRole('button', { name: 'Accepter partiellement' }));
  expect(screen.getByRole('button', { name: 'Confirmer l’acceptation partielle' })).toBeDisabled();

  fireEvent.click(screen.getByRole('checkbox', { name: /eau minérale 1.5l disponible/i }));
  fireEvent.change(screen.getByLabelText('Note pour le client'), {
    target: { value: 'Eau indisponible.' },
  });
  fireEvent.click(screen.getByRole('button', { name: 'Confirmer l’acceptation partielle' }));

  await waitFor(() =>
    expect(partiallyAcceptMerchantOrder).toHaveBeenCalledWith('store-1', 'order-1', {
      rejected_merchant_product_ids: ['mp-2'],
      notes: 'Eau indisponible.',
    }),
  );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.order-detail.test.tsx
```

Expected: FAIL because the Refuser and Accepter partiellement dialogs do not exist yet.

- [ ] **Step 3: Create refusal dialog**

Create `apps/frontend/src/components/merchant/RejectOrderDialog.tsx`:

```tsx
import { useState } from 'react';
import { Button } from '@/components/ui/Button';

interface RejectOrderDialogProps {
  isOpen: boolean;
  isSubmitting: boolean;
  onCancel: () => void;
  onConfirm: (reason: string | null) => void;
}

export function RejectOrderDialog({
  isOpen,
  isSubmitting,
  onCancel,
  onConfirm,
}: RejectOrderDialogProps) {
  const [reason, setReason] = useState('');

  if (!isOpen) return null;

  const trimmedReason = reason.trim();

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/30 p-4">
      <div className="w-full max-w-md rounded-md bg-card p-5 shadow-card">
        <h2 className="text-lg font-black">Refuser la commande</h2>
        <label className="mt-4 block text-sm font-bold" htmlFor="reject-reason">
          Motif de refus
        </label>
        <textarea
          id="reject-reason"
          className="mt-2 min-h-24 w-full rounded-md border border-line p-3 text-sm"
          maxLength={500}
          value={reason}
          onChange={(event) => setReason(event.target.value)}
        />
        <div className="mt-5 flex justify-end gap-3">
          <Button variant="ghost" size="md" disabled={isSubmitting} onClick={onCancel}>
            Annuler
          </Button>
          <Button
            variant="danger"
            size="md"
            disabled={isSubmitting}
            onClick={() => onConfirm(trimmedReason === '' ? null : trimmedReason)}
          >
            Confirmer le refus
          </Button>
        </div>
      </div>
    </div>
  );
}
```

- [ ] **Step 4: Create partial acceptance dialog**

Create `apps/frontend/src/components/merchant/PartialAcceptDialog.tsx`:

```tsx
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/Button';
import type { MerchantOrderLine } from '@/lib/types/merchant.types';

interface PartialAcceptDialogProps {
  isOpen: boolean;
  isSubmitting: boolean;
  lines: MerchantOrderLine[];
  onCancel: () => void;
  onConfirm: (payload: { rejected_merchant_product_ids: string[]; notes: string | null }) => void;
}

export function PartialAcceptDialog({
  isOpen,
  isSubmitting,
  lines,
  onCancel,
  onConfirm,
}: PartialAcceptDialogProps) {
  const [availableIds, setAvailableIds] = useState<string[]>(
    lines.map((line) => line.merchant_product_id),
  );
  const [notes, setNotes] = useState('');

  const rejectedIds = useMemo(
    () =>
      lines
        .map((line) => line.merchant_product_id)
        .filter((lineId) => !availableIds.includes(lineId)),
    [availableIds, lines],
  );
  const canSubmit = availableIds.length > 0 && rejectedIds.length > 0;

  if (!isOpen) return null;

  const toggle = (lineId: string, checked: boolean) => {
    setAvailableIds((current) =>
      checked ? [...current, lineId] : current.filter((id) => id !== lineId),
    );
  };

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/30 p-4">
      <div className="w-full max-w-2xl rounded-md bg-card p-5 shadow-card">
        <h2 className="text-lg font-black">Accepter partiellement la Kadhia</h2>
        <div className="mt-4 divide-y divide-line rounded-md border border-line">
          {lines.map((line) => (
            <label key={line.merchant_product_id} className="flex items-center gap-3 p-4 text-sm">
              <input
                type="checkbox"
                checked={availableIds.includes(line.merchant_product_id)}
                aria-label={`${line.product_name ?? line.merchant_product_id} disponible`}
                onChange={(event) => toggle(line.merchant_product_id, event.currentTarget.checked)}
              />
              <span className="font-bold">{line.product_name ?? line.merchant_product_id}</span>
              <span className="text-muted">x{line.quantity}</span>
            </label>
          ))}
        </div>
        <label className="mt-4 block text-sm font-bold" htmlFor="partial-notes">
          Note pour le client
        </label>
        <textarea
          id="partial-notes"
          className="mt-2 min-h-20 w-full rounded-md border border-line p-3 text-sm"
          maxLength={500}
          value={notes}
          onChange={(event) => setNotes(event.target.value)}
        />
        {!canSubmit && (
          <p className="mt-3 text-sm text-muted">
            Garde au moins une ligne acceptée et marque au moins une ligne indisponible.
          </p>
        )}
        <div className="mt-5 flex justify-end gap-3">
          <Button variant="ghost" size="md" disabled={isSubmitting} onClick={onCancel}>
            Annuler
          </Button>
          <Button
            size="md"
            disabled={isSubmitting || !canSubmit}
            onClick={() =>
              onConfirm({
                rejected_merchant_product_ids: rejectedIds,
                notes: notes.trim() === '' ? null : notes.trim(),
              })
            }
          >
            Confirmer l’acceptation partielle
          </Button>
        </div>
      </div>
    </div>
  );
}
```

- [ ] **Step 5: Wire dialogs into detail page**

Modify `apps/frontend/src/app/merchant/commandes/[orderId]/page.tsx`.

Add imports:

```tsx
import { PartialAcceptDialog } from '@/components/merchant/PartialAcceptDialog';
import { RejectOrderDialog } from '@/components/merchant/RejectOrderDialog';
import {
  acceptMerchantOrder,
  getMerchantOrder,
  markMerchantOrderReady,
  partiallyAcceptMerchantOrder,
  rejectMerchantOrder,
  setMerchantOrderLinePrepared,
  startMerchantOrderPreparation,
} from '@/lib/services/merchant-orders.service';
```

Add state near the existing `error` state:

```tsx
const [isRejectOpen, setIsRejectOpen] = useState(false);
const [isPartialOpen, setIsPartialOpen] = useState(false);
```

In submitted actions, add buttons:

```tsx
<Button variant="ghost" size="md" disabled={isMutating} onClick={() => setIsRejectOpen(true)}>
  Refuser
</Button>
<Button variant="ghost" size="md" disabled={isMutating} onClick={() => setIsPartialOpen(true)}>
  Accepter partiellement
</Button>
```

Before the closing `</div>` of the page component, render:

```tsx
<RejectOrderDialog
  isOpen={isRejectOpen}
  isSubmitting={isMutating}
  onCancel={() => setIsRejectOpen(false)}
  onConfirm={(reason) => {
    setIsRejectOpen(false);
    void runAction(() => rejectMerchantOrder(merchant!.store.id, params.orderId, { reason }));
  }}
/>
<PartialAcceptDialog
  isOpen={isPartialOpen}
  isSubmitting={isMutating}
  lines={order.lines}
  onCancel={() => setIsPartialOpen(false)}
  onConfirm={(payload) => {
    setIsPartialOpen(false);
    void runAction(() =>
      partiallyAcceptMerchantOrder(merchant!.store.id, params.orderId, payload),
    );
  }}
/>
```

- [ ] **Step 6: Run detail tests**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.order-detail.test.tsx
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add apps/frontend/src/components/merchant/RejectOrderDialog.tsx apps/frontend/src/components/merchant/PartialAcceptDialog.tsx apps/frontend/src/app/merchant/commandes/[orderId]/page.tsx apps/frontend/src/tests/merchant.order-detail.test.tsx
git commit -m "feat(merchant): handle rejection and partial acceptance"
```

---

### Task 5: Full Frontend Verification and Cleanup

**Files:**
- Modify only if verification exposes a concrete issue:
  - `apps/frontend/src/app/merchant/commandes/[orderId]/page.tsx`
  - `apps/frontend/src/components/merchant/*.tsx`
  - `apps/frontend/src/lib/types/merchant.types.ts`
  - `apps/frontend/src/lib/services/merchant-orders.service.ts`

- [ ] **Step 1: Run focused merchant tests**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.services.test.ts src/tests/merchant.commandes.test.tsx src/tests/merchant.order-detail.test.tsx
```

Expected: PASS.

- [ ] **Step 2: Run frontend typecheck**

Run:

```bash
cd apps/frontend
npx tsc --noEmit
```

Expected: no TypeScript errors.

- [ ] **Step 3: Run frontend lint**

Run:

```bash
cd apps/frontend
npm run lint
```

Expected: no ESLint errors.

- [ ] **Step 4: Run frontend build**

Run:

```bash
cd apps/frontend
npm run build
```

Expected: Next.js build completes.

- [ ] **Step 5: Inspect scope**

Run:

```bash
git diff --stat origin/main...HEAD
git diff --name-only origin/main...HEAD
```

Expected: only frontend merchant files and tests changed, unless a real backend contract issue required a minimal backend patch. No paiement, livraison, fidélité, marketplace, pickup scan, or withdrawal confirmation files should be added.

- [ ] **Step 6: Commit final verification fixes if any**

If Step 1-4 required fixes, commit them:

```bash
git add apps/frontend
git commit -m "fix(merchant): stabilize order detail workflow"
```

If no fixes were needed, do not create an empty commit.

---

## Self-Review

### Spec Coverage

- Detail route `/merchant/commandes/[orderId]`: Task 3.
- Link from `/merchant/commandes`: Task 2.
- `GET /api/merchant/stores/{storeId}/orders/{orderId}`: Task 1 and Task 3.
- Accept order: Task 1 and Task 3.
- Reject order: Task 1 and Task 4.
- Partial acceptance: Task 1 and Task 4.
- Start preparation: Task 1 and Task 3.
- Line preparation: Task 1 and Task 3.
- Mark ready: Task 1 and Task 3.
- Reload after each mutation: Task 3 and Task 4.
- Error handling and disabled mutation controls: Task 3 and Task 4.
- No pickup scan or withdrawal validation: Task 3 ready-state test and Task 5 scope inspection.

### Placeholder Scan

No `TBD`, `TODO`, or vague implementation steps are intentionally left in this plan. Each task includes exact files, commands, expected outcomes, and code blocks for the planned implementation.

### Type Consistency

The plan uses backend snake_case response/payload names already present in the API contract:

- `merchant_product_id`
- `product_name`
- `unit_price_tnd`
- `line_total_tnd`
- `rejected_merchant_product_ids`
- `pickup_slot`
- `customer_name`
- `customer_phone`

The service names introduced in Task 1 are reused consistently by Tasks 3 and 4.
