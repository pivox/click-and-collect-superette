# Front Marchand Retrait Securise Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Add the merchant secure pickup flow from QR token scan to merchant confirmation and force completion.

**Architecture:** The frontend adds one focused merchant route `/merchant/retrait`, a pickup service wrapping the three Sprint 4 endpoints, and typed DTOs in the existing merchant types file. The UI keeps state local to the page and reuses the existing merchant shell, buttons, formatting helpers, and API client.

**Tech Stack:** Next.js 14 App Router, React client components, TypeScript, Axios `apiClient`, Vitest, Testing Library.

---

## File Structure

- Modify `apps/frontend/src/lib/types/merchant.types.ts`: add pickup session DTOs.
- Create `apps/frontend/src/lib/services/merchant-pickup.service.ts`: wrap scan, confirm, force-complete endpoints.
- Modify `apps/frontend/src/components/merchant/MerchantShell.tsx`: add active "Retrait" navigation item.
- Create `apps/frontend/src/app/merchant/retrait/page.tsx`: merchant pickup UI.
- Create `apps/frontend/src/tests/merchant.pickup.service.test.ts`: service tests.
- Create `apps/frontend/src/tests/merchant.retrait.test.tsx`: page interaction tests.

## Task 1: Pickup Service And Types

**Files:**
- Modify: `apps/frontend/src/lib/types/merchant.types.ts`
- Create: `apps/frontend/src/lib/services/merchant-pickup.service.ts`
- Test: `apps/frontend/src/tests/merchant.pickup.service.test.ts`

- [x] **Step 1: Write the service test**

Create `apps/frontend/src/tests/merchant.pickup.service.test.ts`:

```ts
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  confirmMerchantPickupSession,
  forceCompleteMerchantPickupSession,
  scanMerchantPickupSession,
} from '@/lib/services/merchant-pickup.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    post: vi.fn(),
    patch: vi.fn(),
  },
}));

describe('merchant pickup service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('scans a pickup session token', async () => {
    vi.mocked(apiClient.post).mockResolvedValueOnce({
      data: { id: 'session-1', status: 'pickup_pending' },
    });

    await expect(scanMerchantPickupSession('11111111-1111-4111-8111-111111111111')).resolves.toEqual({
      id: 'session-1',
      status: 'pickup_pending',
    });

    expect(apiClient.post).toHaveBeenCalledWith('/api/merchant/pickup-sessions/scan', {
      token: '11111111-1111-4111-8111-111111111111',
    });
  });

  it('confirms a pickup session with an empty JSON body', async () => {
    vi.mocked(apiClient.patch).mockResolvedValueOnce({
      data: { id: 'session-1', order_status: 'pickup_pending' },
    });

    await confirmMerchantPickupSession('session-1');

    expect(apiClient.patch).toHaveBeenCalledWith(
      '/api/merchant/pickup-sessions/session-1/confirm',
      {},
    );
  });

  it('force completes a pickup session with a note', async () => {
    vi.mocked(apiClient.patch).mockResolvedValueOnce({
      data: { id: 'session-1', order_status: 'completed', force_note: 'Client parti.' },
    });

    await forceCompleteMerchantPickupSession('session-1', 'Client parti.');

    expect(apiClient.patch).toHaveBeenCalledWith(
      '/api/merchant/pickup-sessions/session-1/force-complete',
      { note: 'Client parti.' },
    );
  });
});
```

- [x] **Step 2: Run the service test and verify it fails**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.pickup.service.test.ts
```

Expected: fail because `merchant-pickup.service.ts` does not exist.

- [x] **Step 3: Add pickup types**

Append to `apps/frontend/src/lib/types/merchant.types.ts`:

```ts
export interface MerchantPickupSessionCustomer {
  first_name: string | null;
  last_name: string | null;
  phone: string | null;
}

export interface MerchantPickupSessionLine {
  merchant_product_id: string;
  name: string;
  quantity: number;
  unit_price_tnd: string;
}

export interface MerchantPickupSessionScanResult {
  id: string;
  order_id: string;
  store_id: string;
  order_number: string | null;
  status: 'pickup_pending';
  scanned_at: string;
  customer: MerchantPickupSessionCustomer;
  lines: MerchantPickupSessionLine[];
}

export interface MerchantPickupSessionActionResult {
  id: string;
  order_id: string;
  order_status: MerchantOrderStatus;
  scanned_at: string;
  merchant_confirmed_at: string | null;
  customer_confirmed_at: string | null;
  is_used: boolean;
  is_completed: boolean;
}

export interface MerchantPickupSessionForceCompleteResult extends MerchantPickupSessionActionResult {
  force_completed_by_merchant: boolean;
  force_note: string | null;
}
```

- [x] **Step 4: Add pickup service**

Create `apps/frontend/src/lib/services/merchant-pickup.service.ts`:

```ts
import { apiClient } from '@/lib/api';
import type {
  MerchantPickupSessionActionResult,
  MerchantPickupSessionForceCompleteResult,
  MerchantPickupSessionScanResult,
} from '@/lib/types/merchant.types';

export async function scanMerchantPickupSession(
  token: string,
): Promise<MerchantPickupSessionScanResult> {
  const { data } = await apiClient.post<MerchantPickupSessionScanResult>(
    '/api/merchant/pickup-sessions/scan',
    { token },
  );
  return data;
}

export async function confirmMerchantPickupSession(
  sessionId: string,
): Promise<MerchantPickupSessionActionResult> {
  const { data } = await apiClient.patch<MerchantPickupSessionActionResult>(
    `/api/merchant/pickup-sessions/${sessionId}/confirm`,
    {},
  );
  return data;
}

export async function forceCompleteMerchantPickupSession(
  sessionId: string,
  note: string,
): Promise<MerchantPickupSessionForceCompleteResult> {
  const { data } = await apiClient.patch<MerchantPickupSessionForceCompleteResult>(
    `/api/merchant/pickup-sessions/${sessionId}/force-complete`,
    { note },
  );
  return data;
}
```

- [x] **Step 5: Run service test**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.pickup.service.test.ts
```

Expected: pass.

## Task 2: Merchant Pickup Page

**Files:**
- Create: `apps/frontend/src/app/merchant/retrait/page.tsx`
- Modify: `apps/frontend/src/components/merchant/MerchantShell.tsx`
- Test: `apps/frontend/src/tests/merchant.retrait.test.tsx`

- [x] **Step 1: Write page interaction tests**

Create `apps/frontend/src/tests/merchant.retrait.test.tsx`:

```tsx
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import MerchantPickupPage from '@/app/merchant/retrait/page';
import {
  confirmMerchantPickupSession,
  forceCompleteMerchantPickupSession,
  scanMerchantPickupSession,
} from '@/lib/services/merchant-pickup.service';

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => ({
    merchant: {
      store: { id: 'store-1', name: 'Supérette Test', active: true },
      email: 'merchant@example.test',
    },
  }),
}));

vi.mock('@/lib/services/merchant-pickup.service', () => ({
  scanMerchantPickupSession: vi.fn(),
  confirmMerchantPickupSession: vi.fn(),
  forceCompleteMerchantPickupSession: vi.fn(),
}));

const scanResult = {
  id: 'session-1',
  order_id: 'order-1',
  store_id: 'store-1',
  order_number: '#0042',
  status: 'pickup_pending',
  scanned_at: '2026-05-24T10:00:00+00:00',
  customer: { first_name: 'Haythem', last_name: 'Mabrouk', phone: '+21600000000' },
  lines: [
    {
      merchant_product_id: 'product-1',
      name: 'Lait Vitalait 1L',
      quantity: 2,
      unit_price_tnd: '2.800',
    },
  ],
} as const;

describe('MerchantPickupPage', () => {
  it('blocks an invalid token before calling the API', async () => {
    render(<MerchantPickupPage />);

    fireEvent.change(screen.getByLabelText('Token QR de retrait'), {
      target: { value: 'not-a-uuid' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Identifier la Kadhia' }));

    expect(await screen.findByText('Le token QR doit être un UUID valide.')).toBeInTheDocument();
    expect(scanMerchantPickupSession).not.toHaveBeenCalled();
  });

  it('scans a token and displays the pickup session', async () => {
    vi.mocked(scanMerchantPickupSession).mockResolvedValueOnce(scanResult);

    render(<MerchantPickupPage />);

    fireEvent.change(screen.getByLabelText('Token QR de retrait'), {
      target: { value: '11111111-1111-4111-8111-111111111111' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Identifier la Kadhia' }));

    expect(await screen.findByText('Commande #0042')).toBeInTheDocument();
    expect(screen.getByText('Haythem Mabrouk')).toBeInTheDocument();
    expect(screen.getByText('Lait Vitalait 1L')).toBeInTheDocument();
  });

  it('confirms the merchant handoff and shows waiting customer state', async () => {
    vi.mocked(scanMerchantPickupSession).mockResolvedValueOnce(scanResult);
    vi.mocked(confirmMerchantPickupSession).mockResolvedValueOnce({
      id: 'session-1',
      order_id: 'order-1',
      order_status: 'pickup_pending',
      scanned_at: '2026-05-24T10:00:00+00:00',
      merchant_confirmed_at: '2026-05-24T10:01:00+00:00',
      customer_confirmed_at: null,
      is_used: false,
      is_completed: false,
    });

    render(<MerchantPickupPage />);

    fireEvent.change(screen.getByLabelText('Token QR de retrait'), {
      target: { value: '11111111-1111-4111-8111-111111111111' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Identifier la Kadhia' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Remettre la Kadhia' }));

    expect(await screen.findByText('Confirmation marchand enregistrée.')).toBeInTheDocument();
    expect(screen.getByText('En attente de confirmation client.')).toBeInTheDocument();
  });

  it('requires a force completion note', async () => {
    vi.mocked(scanMerchantPickupSession).mockResolvedValueOnce(scanResult);
    vi.mocked(confirmMerchantPickupSession).mockResolvedValueOnce({
      id: 'session-1',
      order_id: 'order-1',
      order_status: 'pickup_pending',
      scanned_at: '2026-05-24T10:00:00+00:00',
      merchant_confirmed_at: '2026-05-24T10:01:00+00:00',
      customer_confirmed_at: null,
      is_used: false,
      is_completed: false,
    });

    render(<MerchantPickupPage />);

    fireEvent.change(screen.getByLabelText('Token QR de retrait'), {
      target: { value: '11111111-1111-4111-8111-111111111111' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Identifier la Kadhia' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Remettre la Kadhia' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Forcer la finalisation' }));

    expect(await screen.findByText('La note est obligatoire pour forcer la finalisation.')).toBeInTheDocument();
    expect(forceCompleteMerchantPickupSession).not.toHaveBeenCalled();
  });

  it('force completes with a note', async () => {
    vi.mocked(scanMerchantPickupSession).mockResolvedValueOnce(scanResult);
    vi.mocked(confirmMerchantPickupSession).mockResolvedValueOnce({
      id: 'session-1',
      order_id: 'order-1',
      order_status: 'pickup_pending',
      scanned_at: '2026-05-24T10:00:00+00:00',
      merchant_confirmed_at: '2026-05-24T10:01:00+00:00',
      customer_confirmed_at: null,
      is_used: false,
      is_completed: false,
    });
    vi.mocked(forceCompleteMerchantPickupSession).mockResolvedValueOnce({
      id: 'session-1',
      order_id: 'order-1',
      order_status: 'completed',
      scanned_at: '2026-05-24T10:00:00+00:00',
      merchant_confirmed_at: '2026-05-24T10:01:00+00:00',
      customer_confirmed_at: null,
      is_used: true,
      is_completed: true,
      force_completed_by_merchant: true,
      force_note: 'Client parti sans confirmer.',
    });

    render(<MerchantPickupPage />);

    fireEvent.change(screen.getByLabelText('Token QR de retrait'), {
      target: { value: '11111111-1111-4111-8111-111111111111' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Identifier la Kadhia' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Remettre la Kadhia' }));
    fireEvent.change(await screen.findByLabelText('Note de finalisation forcée'), {
      target: { value: 'Client parti sans confirmer.' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Forcer la finalisation' }));

    await waitFor(() => {
      expect(forceCompleteMerchantPickupSession).toHaveBeenCalledWith(
        'session-1',
        'Client parti sans confirmer.',
      );
    });
    expect(await screen.findByText('Retrait finalisé.')).toBeInTheDocument();
  });
});
```

- [x] **Step 2: Run page tests and verify they fail**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.retrait.test.tsx
```

Expected: fail because page does not exist.

- [x] **Step 3: Add active navigation item**

In `apps/frontend/src/components/merchant/MerchantShell.tsx`, add `Retrait` to `ACTIVE_NAV` and import `QrCode` from `lucide-react`:

```tsx
import { BarChart3, CalendarClock, Package, QrCode, Settings, ShoppingBasket } from 'lucide-react';

const ACTIVE_NAV = [
  { href: '/merchant', label: 'Dashboard', icon: BarChart3 },
  { href: '/merchant/commandes', label: 'Commandes', icon: ShoppingBasket },
  { href: '/merchant/retrait', label: 'Retrait', icon: QrCode },
];
```

- [x] **Step 4: Create pickup page**

Create `apps/frontend/src/app/merchant/retrait/page.tsx` with the UI described in the spec, using:

```ts
const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
```

Use `scanMerchantPickupSession`, `confirmMerchantPickupSession`, and `forceCompleteMerchantPickupSession`. Store the latest action result separately from scan result so the UI can show confirmation state.

- [x] **Step 5: Run page tests**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.retrait.test.tsx
```

Expected: pass.

## Task 3: Full Frontend Verification

**Files:**
- Verify frontend only.

- [x] **Step 1: Run targeted tests**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.pickup.service.test.ts src/tests/merchant.retrait.test.tsx
```

Expected: pass.

- [x] **Step 2: Run existing merchant tests**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.services.test.ts src/tests/merchant.commandes.test.tsx src/tests/merchant.order-detail.test.tsx src/tests/merchant.shell.test.tsx src/tests/merchant.dashboard.test.tsx
```

Expected: pass.

- [x] **Step 3: Run TypeScript**

Run:

```bash
cd apps/frontend
npx tsc --noEmit
```

Expected: no TypeScript errors.

- [x] **Step 4: Run lint**

Run:

```bash
cd apps/frontend
npm run lint
```

Expected: no lint errors.

- [x] **Step 5: Run build**

Run:

```bash
cd apps/frontend
npm run build
```

Expected: Next.js build succeeds.
