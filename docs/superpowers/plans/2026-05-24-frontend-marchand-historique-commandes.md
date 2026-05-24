# Front Marchand Historique Commandes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Activer l'historique des commandes marchand sur `/merchant/commandes`, avec filtres "À retirer" et "Clôturées", pagination et liens vers le détail existant.

**Architecture:** Le backend existe déjà ; cette tranche est front-only. La page `/merchant/commandes` reste le point d'entrée unique, avec état séparé pour commandes actives et historique. Les types frontend history deviennent dédiés au payload réel de `GET /api/merchant/stores/{storeId}/orders/history`.

**Tech Stack:** Next.js 14 App Router, React 18, TypeScript, Tailwind CSS, Axios, Vitest, Testing Library.

---

## File Structure

- Modify: `apps/frontend/src/lib/types/merchant.types.ts`
  - Responsibility: types front marchand, dont les nouveaux types dédiés à l'historique.
- Modify: `apps/frontend/src/lib/services/merchant-orders.service.ts`
  - Responsibility: appels API commandes marchand ; typer correctement `listMerchantOrderHistory`.
- Modify: `apps/frontend/src/tests/merchant.services.test.ts`
  - Responsibility: test de contrat HTTP du service history.
- Modify: `apps/frontend/src/tests/merchant.commandes.test.tsx`
  - Responsibility: tests UI pour onglets, filtres, pagination, états erreur/vide et navigation.
- Modify: `apps/frontend/src/app/merchant/commandes/page.tsx`
  - Responsibility: écran commandes marchand avec onglets Actives/Historique.

Keep the implementation in these existing files. Do not add new dependencies. Do not add search/date/export features in this tranche.

---

### Task 1: Type The Merchant Order History API

**Files:**
- Modify: `apps/frontend/src/lib/types/merchant.types.ts`
- Test: `apps/frontend/src/tests/merchant.services.test.ts`

- [ ] **Step 1: Write the failing service type/params test**

In `apps/frontend/src/tests/merchant.services.test.ts`, add a focused assertion to the existing `"loads dashboard and order lists for the active supérette"` test after the existing `await listMerchantOrderHistory('store-1');` call:

```ts
await listMerchantOrderHistory('store-1', {
  page: 2,
  limit: 10,
  status: 'ready,pickup_pending',
});
```

Then add this expected call after the current third `expect(apiClient.get).toHaveBeenNthCalledWith(...)` assertion:

```ts
expect(apiClient.get).toHaveBeenNthCalledWith(
  4,
  '/api/merchant/stores/store-1/orders/history',
  { params: { page: 2, limit: 10, status: 'ready,pickup_pending' } },
);
```

The complete edited test block should read:

```ts
it('loads dashboard and order lists for the active supérette', async () => {
  vi.mocked(apiClient.get)
    .mockResolvedValueOnce({ data: { store_id: 'store-1', pickup_slots_today: [] } })
    .mockResolvedValueOnce({ data: { items: [], total: 0, page: 1, limit: 20 } })
    .mockResolvedValueOnce({ data: { items: [], total: 0, page: 1, limit: 20 } })
    .mockResolvedValueOnce({ data: { items: [], total: 0, page: 2, limit: 10 } });

  await getMerchantDashboardToday('store-1');
  await listMerchantOrders('store-1');
  await listMerchantOrderHistory('store-1');
  await listMerchantOrderHistory('store-1', {
    page: 2,
    limit: 10,
    status: 'ready,pickup_pending',
  });

  expect(apiClient.get).toHaveBeenNthCalledWith(
    1,
    '/api/merchant/stores/store-1/dashboard/today',
  );
  expect(apiClient.get).toHaveBeenNthCalledWith(
    2,
    '/api/merchant/stores/store-1/orders',
    { params: { page: 1, limit: 20 } },
  );
  expect(apiClient.get).toHaveBeenNthCalledWith(
    3,
    '/api/merchant/stores/store-1/orders/history',
    { params: { page: 1, limit: 20 } },
  );
  expect(apiClient.get).toHaveBeenNthCalledWith(
    4,
    '/api/merchant/stores/store-1/orders/history',
    { params: { page: 2, limit: 10, status: 'ready,pickup_pending' } },
  );
});
```

- [ ] **Step 2: Run the targeted service test to verify the current behavior**

Run from `apps/frontend/`:

```bash
npm run test:run -- src/tests/merchant.services.test.ts
```

Expected: the test should pass already for HTTP params, because the service supports `status`; TypeScript still needs dedicated history types in the next step.

- [ ] **Step 3: Add dedicated history types**

In `apps/frontend/src/lib/types/merchant.types.ts`, replace:

```ts
export type MerchantOrderHistoryList = MerchantOrderList;
```

with:

```ts
export interface MerchantOrderHistoryCustomer {
  first_name: string | null;
  last_name: string | null;
  phone: string | null;
}

export interface MerchantOrderHistoryPickupSlot {
  starts_at: string;
  ends_at: string;
}

export interface MerchantOrderHistoryItem {
  id: string;
  status: string;
  status_label_fr: string;
  status_label_ar: string;
  customer: MerchantOrderHistoryCustomer;
  total: string;
  pickup_slot: MerchantOrderHistoryPickupSlot | null;
  created_at: string;
  updated_at: string;
  order_number?: string;
}

export interface MerchantOrderHistoryList {
  items: MerchantOrderHistoryItem[];
  total: number;
  page: number;
  limit: number;
}
```

Keep `MerchantOrderList` unchanged for active orders.

- [ ] **Step 4: Run typecheck and targeted service test**

Run from `apps/frontend/`:

```bash
npx tsc --noEmit
npm run test:run -- src/tests/merchant.services.test.ts
```

Expected: both commands pass.

- [ ] **Step 5: Commit**

```bash
git add apps/frontend/src/lib/types/merchant.types.ts apps/frontend/src/tests/merchant.services.test.ts
git commit -m "test: type merchant order history service"
```

---

### Task 2: Add UI Tests For History Tab, Filters, Pagination, Error, And Empty State

**Files:**
- Modify: `apps/frontend/src/tests/merchant.commandes.test.tsx`
- Modify later: `apps/frontend/src/app/merchant/commandes/page.tsx`

- [ ] **Step 1: Replace the service mock to include history**

In `apps/frontend/src/tests/merchant.commandes.test.tsx`, change the import:

```ts
import { listMerchantOrders } from '@/lib/services/merchant-orders.service';
```

to:

```ts
import {
  listMerchantOrderHistory,
  listMerchantOrders,
} from '@/lib/services/merchant-orders.service';
```

Change the mock:

```ts
vi.mock('@/lib/services/merchant-orders.service', () => ({
  listMerchantOrders: vi.fn(),
}));
```

to:

```ts
vi.mock('@/lib/services/merchant-orders.service', () => ({
  listMerchantOrderHistory: vi.fn(),
  listMerchantOrders: vi.fn(),
}));
```

Add `beforeEach` and `fireEvent` imports:

```ts
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
```

Add this setup inside `describe('MerchantOrdersPage', () => {` before tests:

```ts
beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(listMerchantOrders).mockResolvedValue({
    items: [],
    total: 0,
    page: 1,
    limit: 20,
  });
  vi.mocked(listMerchantOrderHistory).mockResolvedValue({
    items: [],
    total: 0,
    page: 1,
    limit: 20,
  });
});
```

- [ ] **Step 2: Update the existing active-orders test to coexist with defaults**

In the existing `"renders read-only real order summaries"` test, leave the `listMerchantOrders` mock override in place. Add this assertion after the `listMerchantOrders` wait:

```ts
expect(listMerchantOrderHistory).not.toHaveBeenCalled();
```

The active tab should not load history until the merchant opens the history tab.

- [ ] **Step 3: Add failing test for opening history with default "À retirer" filter**

Add this test:

```ts
it('loads history with pickup statuses when the merchant opens Historique', async () => {
  vi.mocked(listMerchantOrderHistory).mockResolvedValue({
    items: [
      {
        id: 'order-ready-1',
        status: 'ready',
        status_label_fr: 'Prête',
        status_label_ar: 'جاهزة',
        customer: {
          first_name: 'Fatma',
          last_name: 'Ben Ali',
          phone: '+21620111222',
        },
        total: '42.300',
        pickup_slot: {
          starts_at: '2026-05-24T12:00:00+01:00',
          ends_at: '2026-05-24T12:30:00+01:00',
        },
        created_at: '2026-05-24T09:00:00+01:00',
        updated_at: '2026-05-24T11:45:00+01:00',
      },
    ],
    total: 1,
    page: 1,
    limit: 20,
  });

  render(React.createElement(MerchantOrdersPage));

  fireEvent.click(screen.getByRole('button', { name: 'Historique' }));

  await waitFor(() =>
    expect(listMerchantOrderHistory).toHaveBeenCalledWith('store-1', {
      page: 1,
      limit: 20,
      status: 'ready,pickup_pending',
    }),
  );

  expect(screen.getByRole('button', { name: 'À retirer' })).toBeInTheDocument();
  expect(screen.getByRole('button', { name: 'Clôturées' })).toBeInTheDocument();
  expect(screen.getByText('order-ready-1')).toBeInTheDocument();
  expect(screen.getByText('Prête')).toBeInTheDocument();
  expect(screen.getByText('42,300 TND')).toBeInTheDocument();
  expect(screen.getByText('Fatma Ben Ali')).toBeInTheDocument();
  expect(
    screen.getAllByText((_, node) => node?.textContent?.includes('rendez-vous 12:00') ?? false)
      .length,
  ).toBeGreaterThan(0);
  expect(
    screen.getAllByText((_, node) => node?.textContent?.includes('mis à jour 11:45') ?? false)
      .length,
  ).toBeGreaterThan(0);
  expect(
    screen.getByRole('link', { name: /voir la commande order-ready-1/i }),
  ).toHaveAttribute('href', '/merchant/commandes/order-ready-1');
});
```

- [ ] **Step 4: Add failing test for the "Clôturées" filter**

Add this test:

```ts
it('switches history to closed statuses', async () => {
  render(React.createElement(MerchantOrdersPage));

  fireEvent.click(screen.getByRole('button', { name: 'Historique' }));
  await waitFor(() => expect(listMerchantOrderHistory).toHaveBeenCalledTimes(1));

  fireEvent.click(screen.getByRole('button', { name: 'Clôturées' }));

  await waitFor(() =>
    expect(listMerchantOrderHistory).toHaveBeenLastCalledWith('store-1', {
      page: 1,
      limit: 20,
      status: 'completed,cancelled,rejected',
    }),
  );
});
```

- [ ] **Step 5: Add failing pagination test**

Add this test:

```ts
it('paginates merchant order history', async () => {
  vi.mocked(listMerchantOrderHistory).mockResolvedValue({
    items: [],
    total: 25,
    page: 1,
    limit: 20,
  });

  render(React.createElement(MerchantOrdersPage));

  fireEvent.click(screen.getByRole('button', { name: 'Historique' }));
  await waitFor(() => expect(listMerchantOrderHistory).toHaveBeenCalledTimes(1));

  fireEvent.click(screen.getByRole('button', { name: 'Page suivante' }));

  await waitFor(() =>
    expect(listMerchantOrderHistory).toHaveBeenLastCalledWith('store-1', {
      page: 2,
      limit: 20,
      status: 'ready,pickup_pending',
    }),
  );
});
```

- [ ] **Step 6: Add failing empty/error state tests**

Add these tests:

```ts
it('renders an empty history state', async () => {
  vi.mocked(listMerchantOrderHistory).mockResolvedValue({
    items: [],
    total: 0,
    page: 1,
    limit: 20,
  });

  render(React.createElement(MerchantOrdersPage));

  fireEvent.click(screen.getByRole('button', { name: 'Historique' }));

  expect(await screen.findByText('Aucune commande dans cet historique.')).toBeInTheDocument();
});

it('renders a dedicated history error', async () => {
  vi.mocked(listMerchantOrderHistory).mockRejectedValue(new Error('Network error'));

  render(React.createElement(MerchantOrdersPage));

  fireEvent.click(screen.getByRole('button', { name: 'Historique' }));

  expect(
    await screen.findByText("Impossible de charger l'historique des commandes."),
  ).toBeInTheDocument();
});
```

- [ ] **Step 7: Run the page tests to verify they fail before implementation**

Run from `apps/frontend/`:

```bash
npm run test:run -- src/tests/merchant.commandes.test.tsx
```

Expected: failures for missing `button` "Historique", missing `listMerchantOrderHistory` calls, and missing history UI.

---

### Task 3: Implement The History Tab And Filters

**Files:**
- Modify: `apps/frontend/src/app/merchant/commandes/page.tsx`
- Test: `apps/frontend/src/tests/merchant.commandes.test.tsx`

- [ ] **Step 1: Update imports and constants**

In `apps/frontend/src/app/merchant/commandes/page.tsx`, change the service import:

```ts
import { listMerchantOrders } from '@/lib/services/merchant-orders.service';
```

to:

```ts
import {
  listMerchantOrderHistory,
  listMerchantOrders,
} from '@/lib/services/merchant-orders.service';
```

Change the type import:

```ts
import type { MerchantOrderList } from '@/lib/types/merchant.types';
```

to:

```ts
import type {
  MerchantOrderHistoryItem,
  MerchantOrderHistoryList,
  MerchantOrderList,
} from '@/lib/types/merchant.types';
```

Add these constants below `ACTIVE_ORDER_STATUSES`:

```ts
const HISTORY_PICKUP_STATUSES = 'ready,pickup_pending';
const HISTORY_CLOSED_STATUSES = 'completed,cancelled,rejected';
const ORDERS_PAGE_LIMIT = 20;

type OrdersTab = 'active' | 'history';
type HistoryFilter = 'pickup' | 'closed';
```

- [ ] **Step 2: Add small render helpers above the component**

Add these helpers above `export default function MerchantOrdersPage()`:

```tsx
function historyCustomerName(order: MerchantOrderHistoryItem): string {
  const name = [order.customer.first_name, order.customer.last_name]
    .filter(Boolean)
    .join(' ')
    .trim();

  return name || 'Client non renseigné';
}

function historyStatusForFilter(filter: HistoryFilter): string {
  return filter === 'pickup' ? HISTORY_PICKUP_STATUSES : HISTORY_CLOSED_STATUSES;
}

function HistoryPagination({
  page,
  limit,
  total,
  onPageChange,
}: {
  page: number;
  limit: number;
  total: number;
  onPageChange: (page: number) => void;
}) {
  const totalPages = Math.max(1, Math.ceil(total / limit));

  if (totalPages <= 1) {
    return null;
  }

  return (
    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-line p-4">
      <p className="text-sm text-muted">
        Page {page} sur {totalPages}
      </p>
      <div className="flex gap-2">
        <Button
          variant="ghost"
          size="md"
          disabled={page <= 1}
          onClick={() => onPageChange(page - 1)}
        >
          Page précédente
        </Button>
        <Button
          variant="ghost"
          size="md"
          disabled={page >= totalPages}
          onClick={() => onPageChange(page + 1)}
        >
          Page suivante
        </Button>
      </div>
    </div>
  );
}
```

- [ ] **Step 3: Add component state and history loader**

Inside `MerchantOrdersPage`, after the existing state declarations, add:

```ts
const [selectedTab, setSelectedTab] = useState<OrdersTab>('active');
const [historyFilter, setHistoryFilter] = useState<HistoryFilter>('pickup');
const [historyPage, setHistoryPage] = useState(1);
const [historyOrders, setHistoryOrders] = useState<MerchantOrderHistoryList | null>(null);
const [isHistoryLoading, setIsHistoryLoading] = useState(false);
const [historyError, setHistoryError] = useState<string | null>(null);
```

Add this loader after `loadOrders`:

```ts
const loadHistoryOrders = useCallback(async () => {
  if (!merchant) return;
  setIsHistoryLoading(true);
  setHistoryError(null);
  try {
    setHistoryOrders(
      await listMerchantOrderHistory(merchant.store.id, {
        page: historyPage,
        limit: ORDERS_PAGE_LIMIT,
        status: historyStatusForFilter(historyFilter),
      }),
    );
  } catch {
    setHistoryError("Impossible de charger l'historique des commandes.");
  } finally {
    setIsHistoryLoading(false);
  }
}, [historyFilter, historyPage, merchant]);
```

Add this effect after the existing active orders effect:

```ts
useEffect(() => {
  if (selectedTab === 'history') {
    void loadHistoryOrders();
  }
}, [loadHistoryOrders, selectedTab]);
```

- [ ] **Step 4: Replace the tab pills with accessible buttons**

Replace the current tab block:

```tsx
<div className="mt-5 flex gap-2">
  <span className="rounded-md bg-primary px-3 py-2 text-sm font-bold text-white">Actives</span>
  <span className="rounded-md bg-soft px-3 py-2 text-sm font-bold text-muted">
    Historique à venir
  </span>
</div>
```

with:

```tsx
<div className="mt-5 flex gap-2">
  <button
    type="button"
    className={cn(
      'rounded-md px-3 py-2 text-sm font-bold',
      selectedTab === 'active' ? 'bg-primary text-white' : 'bg-soft text-muted',
    )}
    onClick={() => setSelectedTab('active')}
  >
    Actives
  </button>
  <button
    type="button"
    className={cn(
      'rounded-md px-3 py-2 text-sm font-bold',
      selectedTab === 'history' ? 'bg-primary text-white' : 'bg-soft text-muted',
    )}
    onClick={() => setSelectedTab('history')}
  >
    Historique
  </button>
</div>
```

Add the missing import at the top:

```ts
import { cn } from '@/lib/cn';
```

- [ ] **Step 5: Scope the active list to the active tab**

Replace the current standalone active error and `<section className="mt-5 rounded-md bg-card shadow-card">...</section>` block with this active-tab block:

```tsx
{selectedTab === 'active' && (
  <>
    {error && (
      <div className="mt-4 rounded-md bg-status-cancel-bg px-4 py-3 text-sm text-status-cancel">
        {error}
      </div>
    )}

    <section className="mt-5 rounded-md bg-card shadow-card">
      {isLoading ? (
        <p className="p-5 text-sm text-muted">Chargement des commandes…</p>
      ) : orders && orders.items.length > 0 ? (
        <div className="divide-y divide-line">
          {orders.items.map((order) => (
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
          ))}
        </div>
      ) : (
        <p className="p-5 text-sm text-muted">Aucune commande active pour cette supérette.</p>
      )}
    </section>
  </>
)}
```

This preserves the current active order row behavior while hiding it when the merchant selects "Historique".

- [ ] **Step 6: Add history filters and list section**

After the active-tab block, add:

```tsx
{selectedTab === 'history' && (
  <>
    <div className="mt-4 flex flex-wrap gap-2">
      <button
        type="button"
        className={cn(
          'rounded-md px-3 py-2 text-sm font-bold',
          historyFilter === 'pickup' ? 'bg-primary text-white' : 'bg-soft text-muted',
        )}
        onClick={() => {
          setHistoryFilter('pickup');
          setHistoryPage(1);
        }}
      >
        À retirer
      </button>
      <button
        type="button"
        className={cn(
          'rounded-md px-3 py-2 text-sm font-bold',
          historyFilter === 'closed' ? 'bg-primary text-white' : 'bg-soft text-muted',
        )}
        onClick={() => {
          setHistoryFilter('closed');
          setHistoryPage(1);
        }}
      >
        Clôturées
      </button>
      <Button variant="ghost" size="md" onClick={() => void loadHistoryOrders()}>
        Réessayer
      </Button>
    </div>

    {historyError && (
      <div className="mt-4 rounded-md bg-status-cancel-bg px-4 py-3 text-sm text-status-cancel">
        {historyError}
      </div>
    )}

    <section className="mt-5 rounded-md bg-card shadow-card">
      {isHistoryLoading ? (
        <p className="p-5 text-sm text-muted">Chargement de l'historique...</p>
      ) : historyOrders && historyOrders.items.length > 0 ? (
        <>
          <div className="divide-y divide-line">
            {historyOrders.items.map((order) => (
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
                    {historyCustomerName(order)}
                    {order.pickup_slot?.starts_at
                      ? ` · rendez-vous ${formatTime(order.pickup_slot.starts_at)}`
                      : ''}
                    {` · mis à jour ${formatTime(order.updated_at)}`}
                  </p>
                </div>
                <strong className="text-right text-lg">{formatTnd(order.total)}</strong>
              </Link>
            ))}
          </div>
          <HistoryPagination
            page={historyOrders.page}
            limit={historyOrders.limit}
            total={historyOrders.total}
            onPageChange={setHistoryPage}
          />
        </>
      ) : (
        <p className="p-5 text-sm text-muted">Aucune commande dans cet historique.</p>
      )}
    </section>
  </>
)}
```

- [ ] **Step 7: Run the page tests**

Run from `apps/frontend/`:

```bash
npm run test:run -- src/tests/merchant.commandes.test.tsx
```

Expected: tests pass. If `cn` import or JSX nesting fails, fix only the syntax causing the failure.

- [ ] **Step 8: Commit**

```bash
git add apps/frontend/src/app/merchant/commandes/page.tsx apps/frontend/src/tests/merchant.commandes.test.tsx
git commit -m "feat: add merchant order history tab"
```

---

### Task 4: Final Verification

**Files:**
- Verify: `apps/frontend/src/lib/types/merchant.types.ts`
- Verify: `apps/frontend/src/lib/services/merchant-orders.service.ts`
- Verify: `apps/frontend/src/app/merchant/commandes/page.tsx`
- Verify: `apps/frontend/src/tests/merchant.commandes.test.tsx`
- Verify: `apps/frontend/src/tests/merchant.services.test.ts`

- [ ] **Step 1: Run focused tests**

Run from `apps/frontend/`:

```bash
npm run test:run -- src/tests/merchant.services.test.ts src/tests/merchant.commandes.test.tsx
```

Expected: both test files pass.

- [ ] **Step 2: Run TypeScript**

Run from `apps/frontend/`:

```bash
npx tsc --noEmit
```

Expected: command exits with code `0`.

- [ ] **Step 3: Run lint**

Run from `apps/frontend/`:

```bash
npm run lint
```

Expected: command exits with code `0`.

- [ ] **Step 4: Inspect git diff**

Run from repo root:

```bash
git diff --stat HEAD~2..HEAD
git status --short
```

Expected: only the planned frontend files are changed by implementation commits. Existing unrelated untracked files may still appear; do not add them.

- [ ] **Step 5: Commit verification fix if needed**

If verification required a small syntax or lint fix, commit only touched implementation files:

```bash
git add apps/frontend/src/lib/types/merchant.types.ts apps/frontend/src/lib/services/merchant-orders.service.ts apps/frontend/src/app/merchant/commandes/page.tsx apps/frontend/src/tests/merchant.commandes.test.tsx apps/frontend/src/tests/merchant.services.test.ts
git commit -m "fix: verify merchant order history frontend"
```

If no fix was needed, do not create an empty commit.

---

## Self-Review

Spec coverage:

- Onglet "Historique" on `/merchant/commandes`: Task 3.
- API `GET /api/merchant/stores/{storeId}/orders/history`: Task 1 and Task 3.
- Filtres "À retirer" and "Clôturées": Task 2 and Task 3.
- Pagination `page` / `limit`: Task 2 and Task 3.
- Detail link `/merchant/commandes/{orderId}`: Task 2 and Task 3.
- Dedicated frontend history types: Task 1.
- Exclusions search/date/export/read-only provenance/polling: respected by file structure and tasks.

Completeness scan: every task has concrete files, commands, expected outcomes, and code snippets where edits are required.

Type consistency:

- `MerchantOrderHistoryList` is returned by `listMerchantOrderHistory`.
- History rows use `total`, `customer`, `status_label_fr`, `status_label_ar`, `pickup_slot`, `created_at`, `updated_at` from the backend history payload.
- Existing active rows keep `total_tnd`, `line_count`, and active `pickup_slot` shape.
