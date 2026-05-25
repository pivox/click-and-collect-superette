# Front Marchand Notifications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the merchant in-app notifications page and unread badge for the Kadhia merchant backoffice.

**Architecture:** Add a focused frontend slice: typed merchant notification API service, an active navigation item with unread badge in `MerchantShell`, and a client page at `/merchant/notifications`. The badge uses `GET /api/merchant/notifications?unread=true` and is refreshed by a browser event named `merchant-notifications:refresh`; no backend, polling, push, WebSocket, Mercure, payment, delivery, loyalty, or marketplace behavior is added.

**Tech Stack:** Next.js 14 App Router, React 18, TypeScript, Tailwind CSS, Axios API client, Vitest, Testing Library, existing Kadhia frontend components.

---

## File Structure

- Create `apps/frontend/src/lib/services/merchant-notifications.service.ts`: API service for listing notifications and marking them read.
- Modify `apps/frontend/src/lib/types/merchant.types.ts`: add notification list/item/read result types using backend `snake_case` fields.
- Create `apps/frontend/src/tests/merchant.notifications.service.test.ts`: service contract tests.
- Modify `apps/frontend/src/components/merchant/MerchantShell.tsx`: add "Notifications" nav entry, unread badge, and refresh event listener.
- Modify `apps/frontend/src/tests/merchant.shell.test.tsx`: shell navigation and badge tests.
- Create `apps/frontend/src/app/merchant/notifications/page.tsx`: merchant notifications page.
- Create `apps/frontend/src/tests/merchant.notifications.test.tsx`: page behavior tests.

## Task 1: Merchant Notification Service

**Files:**
- Modify: `apps/frontend/src/lib/types/merchant.types.ts`
- Create: `apps/frontend/src/lib/services/merchant-notifications.service.ts`
- Create: `apps/frontend/src/tests/merchant.notifications.service.test.ts`

- [ ] **Step 1: Write the failing service tests**

Create `apps/frontend/src/tests/merchant.notifications.service.test.ts`:

```ts
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  listMerchantNotifications,
  markAllMerchantNotificationsRead,
  markMerchantNotificationRead,
} from '@/lib/services/merchant-notifications.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
    patch: vi.fn(),
  },
}));

describe('merchant notifications service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('lists all merchant notifications on page 1 by default', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { items: [], total: 0, page: 1 },
    });

    const result = await listMerchantNotifications();

    expect(apiClient.get).toHaveBeenCalledWith('/api/merchant/notifications', {
      params: { page: 1 },
    });
    expect(result).toEqual({ items: [], total: 0, page: 1 });
  });

  it('lists unread merchant notifications with explicit page', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        items: [
          {
            id: 'notif-1',
            order_id: 'order-1',
            title_fr: 'Nouvelle commande',
            title_ar: 'طلب جديد',
            body_fr: 'Fatma vient de soumettre une commande.',
            body_ar: 'قدمت فاطمة طلبا جديدا.',
            is_read: false,
            created_at: '2026-05-25T10:00:00+01:00',
          },
        ],
        total: 1,
        page: 2,
      },
    });

    const result = await listMerchantNotifications({ page: 2, unread: true });

    expect(apiClient.get).toHaveBeenCalledWith('/api/merchant/notifications', {
      params: { page: 2, unread: true },
    });
    expect(result.items[0].title_fr).toBe('Nouvelle commande');
  });

  it('marks one merchant notification as read', async () => {
    vi.mocked(apiClient.patch).mockResolvedValue({
      data: {
        id: 'notif-1',
        order_id: 'order-1',
        title_fr: 'Nouvelle commande',
        title_ar: 'طلب جديد',
        body_fr: 'Fatma vient de soumettre une commande.',
        body_ar: 'قدمت فاطمة طلبا جديدا.',
        is_read: true,
        created_at: '2026-05-25T10:00:00+01:00',
      },
    });

    const result = await markMerchantNotificationRead('notif-1');

    expect(apiClient.patch).toHaveBeenCalledWith(
      '/api/merchant/notifications/notif-1/read',
      {},
    );
    expect(result.is_read).toBe(true);
  });

  it('marks all merchant notifications as read', async () => {
    vi.mocked(apiClient.patch).mockResolvedValue({ data: undefined });

    await markAllMerchantNotificationsRead();

    expect(apiClient.patch).toHaveBeenCalledWith(
      '/api/merchant/notifications/read-all',
      {},
    );
  });
});
```

- [ ] **Step 2: Run the service tests to verify they fail**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.notifications.service.test.ts
```

Expected: FAIL because `@/lib/services/merchant-notifications.service` does not exist.

- [ ] **Step 3: Add notification types**

Append these interfaces to `apps/frontend/src/lib/types/merchant.types.ts`:

```ts
export interface MerchantNotificationItem {
  id: string;
  order_id: string | null;
  title_fr: string;
  title_ar: string;
  body_fr: string;
  body_ar: string;
  is_read: boolean;
  created_at: string;
}

export interface MerchantNotificationList {
  items: MerchantNotificationItem[];
  total: number;
  page: number;
}

export interface MerchantNotificationListOptions {
  page?: number;
  unread?: boolean;
}

export type MerchantNotificationReadResult = MerchantNotificationItem;
```

- [ ] **Step 4: Implement the service**

Create `apps/frontend/src/lib/services/merchant-notifications.service.ts`:

```ts
import { apiClient } from '@/lib/api';
import type {
  MerchantNotificationList,
  MerchantNotificationListOptions,
  MerchantNotificationReadResult,
} from '@/lib/types/merchant.types';

export async function listMerchantNotifications(
  options: MerchantNotificationListOptions = {},
): Promise<MerchantNotificationList> {
  const { data } = await apiClient.get<MerchantNotificationList>(
    '/api/merchant/notifications',
    {
      params: {
        page: options.page ?? 1,
        ...(options.unread ? { unread: true } : {}),
      },
    },
  );
  return data;
}

export async function markMerchantNotificationRead(
  notificationId: string,
): Promise<MerchantNotificationReadResult> {
  const { data } = await apiClient.patch<MerchantNotificationReadResult>(
    `/api/merchant/notifications/${notificationId}/read`,
    {},
  );
  return data;
}

export async function markAllMerchantNotificationsRead(): Promise<void> {
  await apiClient.patch('/api/merchant/notifications/read-all', {});
}
```

- [ ] **Step 5: Run the service tests to verify they pass**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.notifications.service.test.ts
```

Expected: PASS for all tests in `merchant.notifications.service.test.ts`.

- [ ] **Step 6: Commit Task 1**

Run:

```bash
git add apps/frontend/src/lib/types/merchant.types.ts apps/frontend/src/lib/services/merchant-notifications.service.ts apps/frontend/src/tests/merchant.notifications.service.test.ts
git commit -m "feat(frontend): add merchant notification service"
```

## Task 2: Merchant Shell Notification Badge

**Files:**
- Modify: `apps/frontend/src/components/merchant/MerchantShell.tsx`
- Modify: `apps/frontend/src/tests/merchant.shell.test.tsx`

- [ ] **Step 1: Replace the shell tests with badge coverage**

Update `apps/frontend/src/tests/merchant.shell.test.tsx` to:

```tsx
import { render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { MerchantShell } from '@/components/merchant/MerchantShell';
import { listMerchantNotifications } from '@/lib/services/merchant-notifications.service';

let pathname = '/merchant';

vi.mock('next/navigation', () => ({
  usePathname: () => pathname,
}));

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => ({
    merchant: {
      email: 'marchand@kadhia.tn',
      store: { id: 'store-1', name: 'Supérette Ezzahra', active: true },
    },
    logout: vi.fn(),
  }),
}));

vi.mock('@/lib/services/merchant-notifications.service', () => ({
  listMerchantNotifications: vi.fn(),
}));

describe('MerchantShell', () => {
  beforeEach(() => {
    pathname = '/merchant';
    vi.clearAllMocks();
    vi.mocked(listMerchantNotifications).mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
    });
  });

  it('renders active merchant navigation and disabled future sections', async () => {
    render(
      React.createElement(
        MerchantShell,
        null,
        React.createElement('p', null, 'Contenu marchand'),
      ),
    );

    expect(screen.getAllByText('Supérette Ezzahra')).toHaveLength(2);
    expect(screen.getByText('marchand@kadhia.tn')).toBeInTheDocument();
    expect(screen.getAllByRole('link', { name: /Dashboard/i })[0]).toHaveAttribute(
      'href',
      '/merchant',
    );
    expect(screen.getAllByRole('link', { name: /Commandes/i })[0]).toHaveAttribute(
      'href',
      '/merchant/commandes',
    );
    expect(screen.getAllByRole('link', { name: /Notifications/i })[0]).toHaveAttribute(
      'href',
      '/merchant/notifications',
    );
    expect(screen.getByRole('button', { name: /Créneaux/i })).toBeDisabled();
    expect(screen.getByRole('button', { name: /Catalogue/i })).toBeDisabled();
    expect(screen.getByRole('button', { name: /Paramètres/i })).toBeDisabled();
    expect(screen.getByText('Contenu marchand')).toBeInTheDocument();

    await waitFor(() =>
      expect(listMerchantNotifications).toHaveBeenCalledWith({ unread: true }),
    );
  });

  it('shows unread notification badge when unread total is greater than zero', async () => {
    vi.mocked(listMerchantNotifications).mockResolvedValue({
      items: [],
      total: 3,
      page: 1,
    });

    render(React.createElement(MerchantShell, null, React.createElement('p', null, 'Page')));

    expect(await screen.findByLabelText('3 notifications non lues')).toBeInTheDocument();
  });

  it('does not show unread notification badge when unread total is zero', async () => {
    render(React.createElement(MerchantShell, null, React.createElement('p', null, 'Page')));

    await waitFor(() => expect(listMerchantNotifications).toHaveBeenCalled());
    expect(screen.queryByLabelText(/notifications non lues/i)).not.toBeInTheDocument();
  });

  it('refreshes unread notification badge when the refresh event is dispatched', async () => {
    vi.mocked(listMerchantNotifications)
      .mockResolvedValueOnce({ items: [], total: 1, page: 1 })
      .mockResolvedValueOnce({ items: [], total: 0, page: 1 });

    render(React.createElement(MerchantShell, null, React.createElement('p', null, 'Page')));

    expect(await screen.findByLabelText('1 notification non lue')).toBeInTheDocument();

    window.dispatchEvent(new Event('merchant-notifications:refresh'));

    await waitFor(() =>
      expect(screen.queryByLabelText(/notification non lue/i)).not.toBeInTheDocument(),
    );
  });

  it('keeps rendering the shell when unread badge loading fails', async () => {
    vi.mocked(listMerchantNotifications).mockRejectedValue(new Error('Network error'));

    render(React.createElement(MerchantShell, null, React.createElement('p', null, 'Page')));

    expect(screen.getByText('Page')).toBeInTheDocument();
    await waitFor(() => expect(listMerchantNotifications).toHaveBeenCalled());
    expect(screen.queryByLabelText(/notifications non lues/i)).not.toBeInTheDocument();
  });
});
```

- [ ] **Step 2: Run shell tests to verify they fail**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.shell.test.tsx
```

Expected: FAIL because `MerchantShell` does not call `listMerchantNotifications` and has no "Notifications" nav entry.

- [ ] **Step 3: Implement badge behavior in MerchantShell**

Update `apps/frontend/src/components/merchant/MerchantShell.tsx` to include the notification service, state, event listener, and active nav item. The relevant full file should become:

```tsx
'use client';

import Link from 'next/link';
import {
  BarChart3,
  Bell,
  CalendarClock,
  Package,
  QrCode,
  Settings,
  ShoppingBasket,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { usePathname } from 'next/navigation';
import { Button } from '@/components/ui/Button';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import { cn } from '@/lib/cn';
import { listMerchantNotifications } from '@/lib/services/merchant-notifications.service';

const ACTIVE_NAV = [
  { href: '/merchant', label: 'Dashboard', icon: BarChart3 },
  { href: '/merchant/commandes', label: 'Commandes', icon: ShoppingBasket },
  { href: '/merchant/retrait', label: 'Retrait', icon: QrCode },
  { href: '/merchant/notifications', label: 'Notifications', icon: Bell, badge: 'notifications' },
];

const DISABLED_NAV = [
  { label: 'Créneaux', icon: CalendarClock },
  { label: 'Catalogue', icon: Package },
  { label: 'Paramètres', icon: Settings },
];

export function MerchantShell({ children }: { children: React.ReactNode }) {
  const { merchant, logout } = useMerchantAuth();
  const pathname = usePathname();
  const [unreadNotifications, setUnreadNotifications] = useState(0);

  const refreshUnreadNotifications = useCallback(async () => {
    try {
      const data = await listMerchantNotifications({ unread: true });
      setUnreadNotifications(data.total);
    } catch {
      setUnreadNotifications(0);
    }
  }, []);

  useEffect(() => {
    void refreshUnreadNotifications();

    window.addEventListener('merchant-notifications:refresh', refreshUnreadNotifications);
    return () => {
      window.removeEventListener('merchant-notifications:refresh', refreshUnreadNotifications);
    };
  }, [refreshUnreadNotifications]);

  const renderBadge = (label: string) => {
    if (label !== 'notifications' || unreadNotifications <= 0) {
      return null;
    }

    return (
      <span
        aria-label={`${unreadNotifications} notification${unreadNotifications > 1 ? 's' : ''} non lue${unreadNotifications > 1 ? 's' : ''}`}
        className="ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-secondary px-1.5 py-0.5 text-[11px] font-black text-[#332500]"
      >
        {unreadNotifications}
      </span>
    );
  };

  return (
    <div className="flex min-h-screen bg-bg">
      <aside className="hidden w-64 shrink-0 flex-col border-r border-line bg-[#17211c] text-white md:flex">
        <div className="px-5 py-6">
          <span className="text-xs font-extrabold uppercase tracking-widest text-primary">
            Kadhia Marchand
          </span>
          <strong className="mt-2 block text-base text-white">
            {merchant?.store.name ?? 'Supérette'}
          </strong>
        </div>
        <nav className="flex-1 space-y-1 px-3">
          {ACTIVE_NAV.map((item) => {
            const Icon = item.icon;
            const isActive =
              pathname === item.href || (item.href !== '/merchant' && pathname.startsWith(item.href));
            return (
              <Link
                key={item.href}
                href={item.href}
                className={cn(
                  'flex items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-colors',
                  isActive
                    ? 'bg-white/10 font-semibold text-white'
                    : 'text-white/70 hover:bg-white/5 hover:text-white',
                )}
              >
                <Icon className="h-4 w-4 shrink-0" aria-hidden="true" />
                <span>{item.label}</span>
                {renderBadge(item.badge ?? '')}
              </Link>
            );
          })}
          <div className="pt-3">
            {DISABLED_NAV.map((item) => {
              const Icon = item.icon;
              return (
                <button
                  key={item.label}
                  type="button"
                  disabled
                  title="Prévu dans une prochaine PR"
                  className="flex w-full cursor-not-allowed items-center gap-3 rounded-md px-3 py-2.5 text-left text-sm text-white/35"
                >
                  <Icon className="h-4 w-4" aria-hidden="true" />
                  {item.label}
                </button>
              );
            })}
          </div>
        </nav>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex min-h-16 items-center justify-between border-b border-line bg-card px-4 md:px-6">
          <div>
            <p className="text-sm font-bold text-ink md:hidden">
              {merchant?.store.name ?? 'Supérette'}
            </p>
            <p className="text-sm text-muted">{merchant?.email}</p>
          </div>
          <Button variant="ghost" size="md" onClick={logout}>
            Déconnexion
          </Button>
        </header>
        <nav className="flex gap-2 overflow-x-auto border-b border-line bg-card px-4 py-2 md:hidden">
          {ACTIVE_NAV.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              className={cn(
                'inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-bold',
                pathname === item.href ? 'bg-primary text-white' : 'bg-soft text-ink',
              )}
            >
              {item.label}
              {renderBadge(item.badge ?? '')}
            </Link>
          ))}
        </nav>
        <main className="flex-1 overflow-auto p-4 md:p-6">{children}</main>
      </div>
    </div>
  );
}
```

- [ ] **Step 4: Run shell tests to verify they pass**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.shell.test.tsx
```

Expected: PASS for all shell tests.

- [ ] **Step 5: Commit Task 2**

Run:

```bash
git add apps/frontend/src/components/merchant/MerchantShell.tsx apps/frontend/src/tests/merchant.shell.test.tsx
git commit -m "feat(frontend): add merchant notification badge"
```

## Task 3: Merchant Notifications Page

**Files:**
- Create: `apps/frontend/src/app/merchant/notifications/page.tsx`
- Create: `apps/frontend/src/tests/merchant.notifications.test.tsx`

- [ ] **Step 1: Write the failing page tests**

Create `apps/frontend/src/tests/merchant.notifications.test.tsx`:

```tsx
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantNotificationsPage from '@/app/merchant/notifications/page';
import {
  listMerchantNotifications,
  markAllMerchantNotificationsRead,
  markMerchantNotificationRead,
} from '@/lib/services/merchant-notifications.service';

vi.mock('@/lib/services/merchant-notifications.service', () => ({
  listMerchantNotifications: vi.fn(),
  markAllMerchantNotificationsRead: vi.fn(),
  markMerchantNotificationRead: vi.fn(),
}));

const unreadNotification = {
  id: 'notif-1',
  order_id: 'order-1',
  title_fr: 'Nouvelle commande',
  title_ar: 'طلب جديد',
  body_fr: 'Fatma vient de soumettre une commande pour 12:00.',
  body_ar: 'قدمت فاطمة طلبا جديدا.',
  is_read: false,
  created_at: '2026-05-25T10:00:00+01:00',
};

const readNotification = {
  id: 'notif-2',
  order_id: null,
  title_fr: 'Retrait finalisé',
  title_ar: 'تم الاستلام',
  body_fr: 'La Kadhia a été retirée.',
  body_ar: 'تم استلام القاضية.',
  is_read: true,
  created_at: '2026-05-25T09:00:00+01:00',
};

describe('MerchantNotificationsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(listMerchantNotifications).mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
    });
    vi.mocked(markMerchantNotificationRead).mockResolvedValue({
      ...unreadNotification,
      is_read: true,
    });
    vi.mocked(markAllMerchantNotificationsRead).mockResolvedValue(undefined);
  });

  it('renders merchant notifications and command link', async () => {
    vi.mocked(listMerchantNotifications).mockResolvedValue({
      items: [unreadNotification, readNotification],
      total: 2,
      page: 1,
    });

    render(React.createElement(MerchantNotificationsPage));

    expect(screen.getByRole('heading', { name: 'Notifications' })).toBeInTheDocument();
    expect(await screen.findByText('Nouvelle commande')).toBeInTheDocument();
    expect(screen.getByText('Fatma vient de soumettre une commande pour 12:00.')).toBeInTheDocument();
    expect(screen.getByText('Non lue')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /voir la commande/i })).toHaveAttribute(
      'href',
      '/merchant/commandes/order-1',
    );
    expect(screen.getByText('Retrait finalisé')).toBeInTheDocument();
  });

  it('renders empty state for all notifications', async () => {
    render(React.createElement(MerchantNotificationsPage));

    expect(await screen.findByText('Aucune notification pour le moment.')).toBeInTheDocument();
  });

  it('renders empty state for unread notifications', async () => {
    render(React.createElement(MerchantNotificationsPage));

    fireEvent.click(await screen.findByRole('button', { name: 'Non lues' }));

    await waitFor(() =>
      expect(listMerchantNotifications).toHaveBeenLastCalledWith({ page: 1, unread: true }),
    );
    expect(await screen.findByText('Aucune notification non lue.')).toBeInTheDocument();
  });

  it('marks one notification as read and refreshes badge event', async () => {
    const refreshListener = vi.fn();
    window.addEventListener('merchant-notifications:refresh', refreshListener);
    vi.mocked(listMerchantNotifications)
      .mockResolvedValueOnce({ items: [unreadNotification], total: 1, page: 1 })
      .mockResolvedValueOnce({ items: [{ ...unreadNotification, is_read: true }], total: 1, page: 1 });

    render(React.createElement(MerchantNotificationsPage));

    fireEvent.click(await screen.findByRole('button', { name: /marquer comme lu/i }));

    await waitFor(() => expect(markMerchantNotificationRead).toHaveBeenCalledWith('notif-1'));
    await waitFor(() => expect(listMerchantNotifications).toHaveBeenCalledTimes(2));
    expect(refreshListener).toHaveBeenCalledTimes(1);
    window.removeEventListener('merchant-notifications:refresh', refreshListener);
  });

  it('marks all notifications as read and refreshes badge event', async () => {
    const refreshListener = vi.fn();
    window.addEventListener('merchant-notifications:refresh', refreshListener);
    vi.mocked(listMerchantNotifications)
      .mockResolvedValueOnce({ items: [unreadNotification], total: 1, page: 1 })
      .mockResolvedValueOnce({ items: [], total: 0, page: 1 });

    render(React.createElement(MerchantNotificationsPage));

    fireEvent.click(await screen.findByRole('button', { name: /tout marquer comme lu/i }));

    await waitFor(() => expect(markAllMerchantNotificationsRead).toHaveBeenCalled());
    await waitFor(() => expect(listMerchantNotifications).toHaveBeenCalledTimes(2));
    expect(refreshListener).toHaveBeenCalledTimes(1);
    window.removeEventListener('merchant-notifications:refresh', refreshListener);
  });

  it('paginates notifications', async () => {
    vi.mocked(listMerchantNotifications).mockResolvedValue({
      items: [readNotification],
      total: 25,
      page: 1,
    });

    render(React.createElement(MerchantNotificationsPage));

    fireEvent.click(await screen.findByRole('button', { name: 'Page suivante' }));

    await waitFor(() =>
      expect(listMerchantNotifications).toHaveBeenLastCalledWith({ page: 2 }),
    );
  });

  it('renders load error and retries', async () => {
    vi.mocked(listMerchantNotifications)
      .mockRejectedValueOnce(new Error('Network error'))
      .mockResolvedValueOnce({ items: [readNotification], total: 1, page: 1 });

    render(React.createElement(MerchantNotificationsPage));

    expect(await screen.findByText("Les notifications n'ont pas pu être chargées. Réessaie dans un instant.")).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Réessayer' }));

    expect(await screen.findByText('Retrait finalisé')).toBeInTheDocument();
  });
});
```

- [ ] **Step 2: Run page tests to verify they fail**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.notifications.test.tsx
```

Expected: FAIL because `/merchant/notifications/page.tsx` does not exist.

- [ ] **Step 3: Implement the notifications page**

Create `apps/frontend/src/app/merchant/notifications/page.tsx`:

```tsx
'use client';

import Link from 'next/link';
import { Bell, Check, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { cn } from '@/lib/cn';
import type { MerchantNotificationItem } from '@/lib/types/merchant.types';
import {
  listMerchantNotifications,
  markAllMerchantNotificationsRead,
  markMerchantNotificationRead,
} from '@/lib/services/merchant-notifications.service';

type NotificationFilter = 'all' | 'unread';

const PAGE_SIZE = 20;
const LOAD_ERROR = "Les notifications n'ont pas pu être chargées. Réessaie dans un instant.";
const MUTATION_ERROR = "La notification n'a pas pu être mise à jour. Réessaie dans un instant.";

function dispatchNotificationRefresh(): void {
  window.dispatchEvent(new Event('merchant-notifications:refresh'));
}

function formatNotificationDate(iso: string): string {
  try {
    return new Intl.DateTimeFormat('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    }).format(new Date(iso));
  } catch {
    return iso;
  }
}

export default function MerchantNotificationsPage() {
  const [items, setItems] = useState<MerchantNotificationItem[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [filter, setFilter] = useState<NotificationFilter>('all');
  const [isLoading, setIsLoading] = useState(true);
  const [isMutating, setIsMutating] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [mutationError, setMutationError] = useState<string | null>(null);

  const unreadOnly = filter === 'unread';
  const hasNextPage = page * PAGE_SIZE < total;
  const hasPreviousPage = page > 1;
  const hasUnreadOnPage = useMemo(() => items.some((item) => !item.is_read), [items]);
  const showMarkAllRead = hasUnreadOnPage || (unreadOnly && total > 0);

  const loadNotifications = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await listMerchantNotifications({
        page,
        ...(unreadOnly ? { unread: true } : {}),
      });
      setItems(data.items);
      setTotal(data.total);
    } catch {
      setError(LOAD_ERROR);
    } finally {
      setIsLoading(false);
    }
  }, [page, unreadOnly]);

  useEffect(() => {
    void loadNotifications();
  }, [loadNotifications]);

  const changeFilter = (nextFilter: NotificationFilter) => {
    setFilter(nextFilter);
    setPage(1);
  };

  const refresh = async () => {
    await loadNotifications();
    dispatchNotificationRefresh();
  };

  const markOneRead = async (notificationId: string) => {
    setIsMutating(true);
    setMutationError(null);
    try {
      await markMerchantNotificationRead(notificationId);
      await loadNotifications();
      dispatchNotificationRefresh();
    } catch {
      setMutationError(MUTATION_ERROR);
    } finally {
      setIsMutating(false);
    }
  };

  const markAllRead = async () => {
    setIsMutating(true);
    setMutationError(null);
    try {
      await markAllMerchantNotificationsRead();
      await loadNotifications();
      dispatchNotificationRefresh();
    } catch {
      setMutationError(MUTATION_ERROR);
    } finally {
      setIsMutating(false);
    }
  };

  return (
    <section className="mx-auto flex w-full max-w-5xl flex-col gap-5">
      <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <p className="text-sm font-bold text-primary">Supérette</p>
          <h1 className="text-2xl font-black text-ink">Notifications</h1>
        </div>
        <div className="flex flex-wrap gap-2">
          {showMarkAllRead && (
            <Button
              type="button"
              variant="ghost"
              size="md"
              disabled={isMutating}
              onClick={markAllRead}
            >
              <Check className="h-4 w-4" aria-hidden="true" />
              Tout marquer comme lu
            </Button>
          )}
          <Button type="button" variant="ghost" size="md" disabled={isLoading} onClick={refresh}>
            <RefreshCw className="h-4 w-4" aria-hidden="true" />
            Actualiser
          </Button>
        </div>
      </div>

      <div className="flex gap-2" role="group" aria-label="Filtres notifications">
        <button
          type="button"
          className={cn(
            'rounded-md px-4 py-2 text-sm font-black',
            filter === 'all' ? 'bg-primary text-white' : 'bg-soft text-ink',
          )}
          onClick={() => changeFilter('all')}
        >
          Toutes
        </button>
        <button
          type="button"
          className={cn(
            'rounded-md px-4 py-2 text-sm font-black',
            filter === 'unread' ? 'bg-primary text-white' : 'bg-soft text-ink',
          )}
          onClick={() => changeFilter('unread')}
        >
          Non lues
        </button>
      </div>

      {mutationError && (
        <div className="rounded-md border border-danger/30 bg-danger/10 px-4 py-3 text-sm font-semibold text-danger">
          {mutationError}
        </div>
      )}

      {isLoading && (
        <Card className="text-sm font-semibold text-muted">Chargement des notifications...</Card>
      )}

      {!isLoading && error && (
        <Card className="flex flex-col gap-3">
          <p className="text-sm font-semibold text-danger">{error}</p>
          <Button type="button" variant="ghost" size="md" className="self-start" onClick={loadNotifications}>
            Réessayer
          </Button>
        </Card>
      )}

      {!isLoading && !error && items.length === 0 && (
        <Card className="flex flex-col items-center gap-3 py-10 text-center">
          <Bell className="h-8 w-8 text-muted" aria-hidden="true" />
          <p className="font-black text-ink">
            {unreadOnly ? 'Aucune notification non lue.' : 'Aucune notification pour le moment.'}
          </p>
        </Card>
      )}

      {!isLoading && !error && items.length > 0 && (
        <div className="space-y-3">
          {items.map((notification) => (
            <Card
              key={notification.id}
              as="article"
              className={cn(
                'flex flex-col gap-3',
                !notification.is_read && 'border-primary/40 bg-primary/5',
              )}
            >
              <div className="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-2">
                    <h2 className="text-base font-black text-ink">{notification.title_fr}</h2>
                    {!notification.is_read && (
                      <span className="rounded-full bg-secondary px-2 py-0.5 text-xs font-black text-[#332500]">
                        Non lue
                      </span>
                    )}
                  </div>
                  <p className="mt-1 text-sm leading-6 text-muted">{notification.body_fr}</p>
                </div>
                <time className="shrink-0 text-xs font-bold text-muted" dateTime={notification.created_at}>
                  {formatNotificationDate(notification.created_at)}
                </time>
              </div>
              <div className="flex flex-wrap gap-2">
                {notification.order_id && (
                  <Link
                    href={`/merchant/commandes/${notification.order_id}`}
                    className="inline-flex min-h-[44px] items-center justify-center rounded-md border border-line bg-white px-4 text-sm font-black text-ink hover:bg-soft"
                  >
                    Voir la commande
                  </Link>
                )}
                {!notification.is_read && (
                  <Button
                    type="button"
                    variant="ghost"
                    size="md"
                    disabled={isMutating}
                    onClick={() => markOneRead(notification.id)}
                  >
                    Marquer comme lu
                  </Button>
                )}
              </div>
            </Card>
          ))}
        </div>
      )}

      {!isLoading && !error && total > PAGE_SIZE && (
        <div className="flex items-center justify-between gap-3">
          <Button
            type="button"
            variant="ghost"
            size="md"
            disabled={!hasPreviousPage || isLoading}
            onClick={() => setPage((current) => Math.max(1, current - 1))}
          >
            Page précédente
          </Button>
          <span className="text-sm font-bold text-muted">Page {page}</span>
          <Button
            type="button"
            variant="ghost"
            size="md"
            disabled={!hasNextPage || isLoading}
            onClick={() => setPage((current) => current + 1)}
          >
            Page suivante
          </Button>
        </div>
      )}
    </section>
  );
}
```

- [ ] **Step 4: Run page tests to verify they pass**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.notifications.test.tsx
```

Expected: PASS for all page tests.

- [ ] **Step 5: Commit Task 3**

Run:

```bash
git add apps/frontend/src/app/merchant/notifications/page.tsx apps/frontend/src/tests/merchant.notifications.test.tsx
git commit -m "feat(frontend): add merchant notifications page"
```

## Task 4: Integration Verification

**Files:**
- Verify: `apps/frontend/src/lib/services/merchant-notifications.service.ts`
- Verify: `apps/frontend/src/components/merchant/MerchantShell.tsx`
- Verify: `apps/frontend/src/app/merchant/notifications/page.tsx`
- Verify: `apps/frontend/src/tests/merchant.notifications.service.test.ts`
- Verify: `apps/frontend/src/tests/merchant.notifications.test.tsx`
- Verify: `apps/frontend/src/tests/merchant.shell.test.tsx`

- [ ] **Step 1: Run targeted frontend tests**

Run:

```bash
cd apps/frontend
npm run test:run -- src/tests/merchant.notifications.service.test.ts src/tests/merchant.notifications.test.tsx src/tests/merchant.shell.test.tsx
```

Expected: PASS for notification service, notification page, and shell tests.

- [ ] **Step 2: Run TypeScript check**

Run:

```bash
cd apps/frontend
npx tsc --noEmit
```

Expected: exits with code 0 and no TypeScript errors.

- [ ] **Step 3: Run frontend lint**

Run:

```bash
cd apps/frontend
npm run lint
```

Expected: exits with code 0 and no lint errors.

- [ ] **Step 4: Run frontend build**

Run:

```bash
cd apps/frontend
npm run build
```

Expected: exits with code 0 and produces a successful Next.js build.

- [ ] **Step 5: Inspect git diff for MVP scope**

Run:

```bash
git diff --stat HEAD
git diff -- apps/frontend/src/lib/types/merchant.types.ts apps/frontend/src/lib/services/merchant-notifications.service.ts apps/frontend/src/components/merchant/MerchantShell.tsx apps/frontend/src/app/merchant/notifications/page.tsx
```

Expected: only notification frontend files are changed. There is no backend change and no payment, delivery, loyalty, push, WebSocket, Mercure, or marketplace behavior.

- [ ] **Step 6: Commit any verification fixes**

If verification required small fixes, commit them:

```bash
git add apps/frontend/src/lib/types/merchant.types.ts apps/frontend/src/lib/services/merchant-notifications.service.ts apps/frontend/src/components/merchant/MerchantShell.tsx apps/frontend/src/app/merchant/notifications/page.tsx apps/frontend/src/tests/merchant.notifications.service.test.ts apps/frontend/src/tests/merchant.notifications.test.tsx apps/frontend/src/tests/merchant.shell.test.tsx
git commit -m "fix(frontend): stabilize merchant notifications"
```

Expected: create this commit only if Step 1-5 required changes after Task 3.

## Self-Review

- Spec coverage: route `/merchant/notifications`, active nav item, unread badge, list, all/unread filter, manual refresh, mark one read, mark all read, command link, loading/empty/error states, and tests are covered by Tasks 1-4.
- Scope: no backend work, no polling, no dropdown, no real-time transport, no payment, no delivery, no loyalty, no marketplace cart.
- Type consistency: service returns `MerchantNotificationList`, read mutation returns `MerchantNotificationReadResult`, page uses `MerchantNotificationItem`, and all fields match backend `snake_case`.
- Ambiguity resolved: badge refresh uses `merchant-notifications:refresh`; the shell listens to that event and reloads only the unread count.
