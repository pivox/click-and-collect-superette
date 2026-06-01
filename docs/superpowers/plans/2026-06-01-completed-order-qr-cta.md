# Completed Order QR CTA Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix issue #294 so completed customer orders no longer show a QR CTA implying the QR will become available later.

**Architecture:** Keep the behavior local to the customer order tracking page. Extract the CTA rendering into a small helper inside the page file so desktop and mobile use the same status logic.

**Tech Stack:** Next.js 14, React 18, TypeScript, Vitest, Testing Library.

---

### File Structure

- Modify: `apps/frontend/src/app/(client)/orders/[orderId]/page.tsx`
  - Add a small `renderPickupAction(order)` helper near the component.
  - Return the active QR link for `ready` and `pickup_pending`.
  - Return disabled "Retrait finalisé" for `completed`.
  - Return disabled "QR retrait — disponible quand la commande est prête" for earlier non-terminal statuses.
- Create: `apps/frontend/src/tests/client.order-tracking-page.test.tsx`
  - Mock auth and order service.
  - Render the customer order detail page with a `completed` order.
  - Assert the final state is visible and the waiting QR message is absent.

### Task 1: Add Regression Test

**Files:**
- Create: `apps/frontend/src/tests/client.order-tracking-page.test.tsx`

- [x] **Step 1: Write the failing test**

```tsx
import { render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('next/navigation', () => ({
  notFound: vi.fn(),
}));

vi.mock('@/lib/services', () => ({
  getOrder: vi.fn(),
  projectTimeline: vi.fn(() => [
    { key: 'submitted', label: 'Commande envoyée', state: 'done' },
    { key: 'accepted', label: 'Commande acceptée', state: 'done' },
    { key: 'preparing', label: 'Préparation', state: 'done' },
    { key: 'ready', label: 'Prête', state: 'done' },
    { key: 'completed', label: 'Commande récupérée', state: 'current' },
  ]),
}));

vi.mock('@/lib/auth/ClientAuthContext', () => ({
  useClientAuth: vi.fn(),
}));

import OrderTrackingPage from '@/app/(client)/orders/[orderId]/page';
import { useClientAuth } from '@/lib/auth/ClientAuthContext';
import { getOrder } from '@/lib/services';
import type { Order } from '@/types';

const MOCK_USER = { token: 'tok', email: 'client@test.com', name: 'Client Test' };

function makeOrder(status: Order['status']): Order {
  return {
    id: 'order-uuid-1',
    shopId: 'store-uuid-1',
    shopName: 'Supérette El Amen',
    shopAddress: 'Rue de la Liberté',
    shopCity: 'Tunis',
    status,
    totalAmountTnd: '12.500',
    pickupSlot: {
      id: 'slot-uuid-1',
      startsAt: '2026-05-28T10:00:00+01:00',
      endsAt: '2026-05-28T10:30:00+01:00',
      capacity: null,
      available: true,
    },
    submittedAt: null,
    acceptedAt: null,
    readyAt: null,
    completedAt: '2026-05-28T10:20:00+01:00',
    rejectionReason: null,
    code: '#0015',
    customerNote: null,
    lines: [],
  };
}

describe('OrderTrackingPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(useClientAuth).mockReturnValue({
      user: MOCK_USER,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
    } as unknown as ReturnType<typeof useClientAuth>);
  });

  it('affiche un état final au lieu du CTA QR en attente pour une commande récupérée', async () => {
    vi.mocked(getOrder).mockResolvedValue(makeOrder('completed'));

    render(<OrderTrackingPage params={{ orderId: 'order-uuid-1' }} />);

    expect(await screen.findAllByText('Retrait finalisé')).toHaveLength(2);
    expect(screen.queryByText(/disponible quand prête/i)).toBeNull();
    expect(screen.queryByText(/disponible quand la commande est prête/i)).toBeNull();
    expect(screen.queryByRole('link', { name: /Afficher le QR retrait/i })).toBeNull();

    await waitFor(() => {
      expect(getOrder).toHaveBeenCalledWith('order-uuid-1');
    });
  });
});
```

- [x] **Step 2: Run test to verify it fails**

Run:

```bash
npm run test:run -- client.order-tracking-page.test.tsx
```

Expected: FAIL because completed orders still render the disabled waiting QR text.

### Task 2: Implement Completed CTA State

**Files:**
- Modify: `apps/frontend/src/app/(client)/orders/[orderId]/page.tsx`

- [x] **Step 1: Add a shared CTA renderer**

```tsx
function renderPickupAction(order: Order) {
  if (order.status === "ready" || order.status === "pickup_pending") {
    return (
      <Link
        href={`/orders/${order.id}/pickup`}
        className={getButtonClassName({ full: true })}
      >
        Afficher le QR retrait
      </Link>
    );
  }

  if (order.status === "completed") {
    return (
      <Button full disabled>
        Retrait finalisé
      </Button>
    );
  }

  return (
    <Button full disabled>
      QR retrait — disponible quand la commande est prête
    </Button>
  );
}
```

- [x] **Step 2: Replace duplicate desktop and mobile CTA branches**

```tsx
<div className="hidden md:block mt-4">
  {renderPickupAction(order)}
</div>
```

```tsx
<StickyBottom className="md:hidden">
  {renderPickupAction(order)}
</StickyBottom>
```

- [x] **Step 3: Run targeted frontend test**

Run:

```bash
npm run test:run -- client.order-tracking-page.test.tsx
```

Expected: PASS.

- [x] **Step 4: Run related frontend service tests**

Run:

```bash
npm run test:run -- client.orders.service.test.ts client.pickup-page.test.tsx
```

Expected: PASS.

- [x] **Step 5: Commit**

```bash
git add docs/superpowers/specs/2026-06-01-completed-order-qr-cta-design.md docs/superpowers/plans/2026-06-01-completed-order-qr-cta.md apps/frontend/src/app/(client)/orders/[orderId]/page.tsx apps/frontend/src/tests/client.order-tracking-page.test.tsx
git commit -m "fix: show completed pickup state on client order"
```

### Self-Review

- Spec coverage: the plan covers completed, ready, pickup_pending, and pre-ready CTA states.
- Placeholder scan: no placeholder remains.
- Type consistency: the helper uses the existing `Order` type already imported by the page.
