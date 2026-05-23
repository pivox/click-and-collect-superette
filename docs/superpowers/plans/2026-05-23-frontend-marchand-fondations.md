# Frontend Marchand Foundations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the first merchant area connected to real backend endpoints: merchant login, `GET /api/merchant/me`, compact dashboard, and read-only orders page.

**Architecture:** Add a small API Platform read model for the current merchant context, then keep frontend API calls in typed service modules. The merchant frontend uses a protected shell that resolves `GET /api/merchant/me` once, stores the active supérette context, and passes the `storeId` to dashboard and orders services. Business processing actions stay out of scope.

**Tech Stack:** Symfony 7, API Platform, Doctrine ORM, PHPUnit functional tests, Next.js App Router, React, TypeScript, axios `apiClient`, Vitest + Testing Library.

---

## File Map

| File | Action | Responsibility |
|---|---|---|
| `apps/backend/src/ApiResource/MerchantMeOutput.php` | Create | API Platform DTO for `GET /api/merchant/me` |
| `apps/backend/src/ApiResource/MerchantMeStoreOutput.php` | Create | Nested active supérette representation |
| `apps/backend/src/Provider/MerchantMeProvider.php` | Create | Resolve authenticated merchant and enforce 0/1/many active supérettes rules |
| `apps/backend/tests/Functional/Api/MerchantMeApiTest.php` | Create | Functional coverage for 401, 403 non-marchand, 403 marchand suspendu, 404, 409, 200 |
| `apps/frontend/src/lib/types/merchant.types.ts` | Create | Merchant session, dashboard, orders types |
| `apps/frontend/src/lib/services/merchant-auth.service.ts` | Create | Login + current merchant context calls |
| `apps/frontend/src/lib/services/merchant-dashboard.service.ts` | Create | Dashboard today call |
| `apps/frontend/src/lib/services/merchant-orders.service.ts` | Create | Active orders and optional history calls |
| `apps/frontend/src/lib/auth/MerchantAuthContext.tsx` | Create | Merchant session provider and route guard state |
| `apps/frontend/src/components/merchant/MerchantShell.tsx` | Create | Merchant layout, active navigation, disabled future sections |
| `apps/frontend/src/app/merchant/login/page.tsx` | Create | Merchant login form |
| `apps/frontend/src/app/merchant/layout.tsx` | Create | Merchant auth provider boundary |
| `apps/frontend/src/app/merchant/page.tsx` | Create | Compact read-only dashboard |
| `apps/frontend/src/app/merchant/commandes/page.tsx` | Create | Read-only real orders list |
| `apps/frontend/src/tests/merchant*.test.tsx` | Create | Frontend service and UI tests |

---

## Task 1 — Backend `GET /api/merchant/me` contract, TDD

**Files:**
- Create: `apps/backend/tests/Functional/Api/MerchantMeApiTest.php`
- Create: `apps/backend/src/ApiResource/MerchantMeOutput.php`
- Create: `apps/backend/src/ApiResource/MerchantMeStoreOutput.php`
- Create: `apps/backend/src/Provider/MerchantMeProvider.php`

- [ ] **Step 1: Write failing functional tests**

Create `apps/backend/tests/Functional/Api/MerchantMeApiTest.php` with tests named exactly:

```php
public function test_anonymous_user_gets_401(): void
public function test_customer_user_gets_403(): void
public function test_merchant_without_active_store_gets_404(): void
public function test_merchant_with_multiple_active_stores_gets_409(): void
public function test_merchant_with_one_active_store_gets_current_context(): void
```

The successful assertion must verify this shape:

```php
self::assertSame($merchant->getId()->toRfc4122(), $payload['user_id']);
self::assertSame($merchant->getEmail(), $payload['email']);
self::assertContains('ROLE_MERCHANT', $payload['roles']);
self::assertSame($shop->getId()->toRfc4122(), $payload['store']['id']);
self::assertSame($shop->getName(), $payload['store']['name']);
self::assertTrue($payload['store']['active']);
self::assertArrayHasKey('onboarding_completed', $payload);
self::assertArrayNotHasKey('password', $payload);
```

- [ ] **Step 2: Run the failing backend test**

Run:

```bash
cd apps/backend && vendor/bin/phpunit tests/Functional/Api/MerchantMeApiTest.php
```

Expected: FAIL because `/api/merchant/me` is not registered yet.

- [ ] **Step 3: Add the API output classes**

Create `apps/backend/src/ApiResource/MerchantMeStoreOutput.php`:

```php
<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class MerchantMeStoreOutput
{
    public function __construct(
        #[Groups(['merchant_me:read'])]
        public string $id,
        #[Groups(['merchant_me:read'])]
        public string $name,
        #[Groups(['merchant_me:read'])]
        public bool $active,
    ) {
    }
}
```

Create `apps/backend/src/ApiResource/MerchantMeOutput.php`:

```php
<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\MerchantMeProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/merchant/me',
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_me:read']],
            security: "is_granted('ROLE_MERCHANT')",
            provider: MerchantMeProvider::class,
        ),
    ],
)]
final readonly class MerchantMeOutput
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['merchant_me:read'])]
        #[SerializedName('user_id')]
        public string $userId,
        #[Groups(['merchant_me:read'])]
        public string $email,
        #[Groups(['merchant_me:read'])]
        public array $roles,
        #[Groups(['merchant_me:read'])]
        public MerchantMeStoreOutput $store,
        #[Groups(['merchant_me:read'])]
        #[SerializedName('onboarding_completed')]
        public bool $onboardingCompleted,
    ) {
    }
}
```

- [ ] **Step 4: Add the provider**

Create `apps/backend/src/Provider/MerchantMeProvider.php`:

```php
<?php

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantMeOutput;
use App\ApiResource\MerchantMeStoreOutput;
use App\Entity\Shop;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<MerchantMeOutput>
 */
final readonly class MerchantMeProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MerchantMeOutput
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new NotFoundHttpException('Merchant account not found.');
        }

        $shops = $this->entityManager->getRepository(Shop::class)->findBy([
            'owner' => $user,
            'active' => true,
        ]);

        if ([] === $shops) {
            throw new NotFoundHttpException('No active supérette is attached to this merchant.');
        }

        if (\count($shops) > 1) {
            throw new ConflictHttpException('Multiple active supérettes are attached to this merchant.');
        }

        $shop = $shops[0];

        return new MerchantMeOutput(
            userId: $user->getId()->toRfc4122(),
            email: $user->getEmail(),
            roles: $user->getRoles(),
            store: new MerchantMeStoreOutput(
                id: $shop->getId()->toRfc4122(),
                name: $shop->getName(),
                active: $shop->isActive(),
            ),
            onboardingCompleted: null !== $user->getOnboardingCompletedAt(),
        );
    }
}
```

- [ ] **Step 5: Run the backend test to pass**

Run:

```bash
cd apps/backend && vendor/bin/phpunit tests/Functional/Api/MerchantMeApiTest.php
```

Expected: PASS for the five `MerchantMeApiTest` cases.

---

## Task 2 — Frontend merchant services and types, TDD

**Files:**
- Create: `apps/frontend/src/lib/types/merchant.types.ts`
- Create: `apps/frontend/src/lib/services/merchant-auth.service.ts`
- Create: `apps/frontend/src/lib/services/merchant-dashboard.service.ts`
- Create: `apps/frontend/src/lib/services/merchant-orders.service.ts`
- Create: `apps/frontend/src/tests/merchant.services.test.ts`

- [ ] **Step 1: Write failing service tests**

Create `apps/frontend/src/tests/merchant.services.test.ts` with assertions for:

```typescript
await loginMerchant({ email: 'merchant@example.test', password: 'secret' });
expect(apiClient.post).toHaveBeenCalledWith('/api/auth/login', {
  email: 'merchant@example.test',
  password: 'secret',
});

await getMerchantMe();
expect(apiClient.get).toHaveBeenCalledWith('/api/merchant/me');

await getMerchantDashboardToday('store-1');
expect(apiClient.get).toHaveBeenCalledWith('/api/merchant/stores/store-1/dashboard/today');

await getMerchantOrders('store-1');
expect(apiClient.get).toHaveBeenCalledWith('/api/merchant/stores/store-1/orders');

await getMerchantOrderHistory('store-1');
expect(apiClient.get).toHaveBeenCalledWith('/api/merchant/stores/store-1/orders/history');
```

- [ ] **Step 2: Run the failing service tests**

Run:

```bash
cd apps/frontend && npx vitest run src/tests/merchant.services.test.ts
```

Expected: FAIL because the merchant service modules do not exist.

- [ ] **Step 3: Add merchant types**

Create `apps/frontend/src/lib/types/merchant.types.ts`:

```typescript
export interface MerchantLoginPayload {
  email: string;
  password: string;
}

export interface MerchantMe {
  user_id: string;
  email: string;
  roles: string[];
  store: {
    id: string;
    name: string;
    active: boolean;
  };
  onboarding_completed: boolean;
}

export interface MerchantDashboardToday {
  store_id: string;
  date: string;
  total_orders_today: number;
  orders_by_status: Record<string, number>;
  submitted_count: number;
  accepted_count: number;
  partially_accepted_count: number;
  preparing_count: number;
  ready_count: number;
  cancelled_count: number;
  rejected_count: number;
  completed_count: number;
  pickup_pending_count: number;
  urgent_submitted_count: number;
  pickup_slots_today: Array<{
    id: string;
    starts_at: string;
    ends_at: string;
    order_count: number;
    capacity: number;
  }>;
}

export interface MerchantOrderSummary {
  id: string;
  status: string;
  total_tnd: string;
  pickup_slot?: {
    id: string;
    starts_at: string;
    ends_at: string;
  } | null;
  line_count: number;
  created_at: string;
  updated_at: string;
}

export interface MerchantOrderList {
  items: MerchantOrderSummary[];
  total: number;
  page: number;
  limit: number;
}

export interface MerchantOrderHistoryItem {
  id: string;
  status: string;
  status_label_fr: string;
  status_label_ar: string;
  customer: {
    id: string;
    name: string;
    email: string;
  };
  total: string;
  pickup_slot?: {
    id: string;
    starts_at: string;
    ends_at: string;
  } | null;
  created_at: string;
  updated_at: string;
}

export interface MerchantOrderHistoryList {
  items: MerchantOrderHistoryItem[];
  total: number;
  page: number;
  limit: number;
}
```

- [ ] **Step 4: Add service modules**

Create `apps/frontend/src/lib/services/merchant-auth.service.ts`:

```typescript
import { apiClient } from '@/lib/api';
import type { MerchantLoginPayload, MerchantMe } from '@/lib/types/merchant.types';

export async function loginMerchant(payload: MerchantLoginPayload): Promise<void> {
  await apiClient.post('/api/auth/login', payload);
}

export async function getMerchantMe(): Promise<MerchantMe> {
  const { data } = await apiClient.get<MerchantMe>('/api/merchant/me');
  return data;
}
```

Create `apps/frontend/src/lib/services/merchant-dashboard.service.ts`:

```typescript
import { apiClient } from '@/lib/api';
import type { MerchantDashboardToday } from '@/lib/types/merchant.types';

export async function getMerchantDashboardToday(storeId: string): Promise<MerchantDashboardToday> {
  const { data } = await apiClient.get<MerchantDashboardToday>(
    `/api/merchant/stores/${storeId}/dashboard/today`,
  );
  return data;
}
```

Create `apps/frontend/src/lib/services/merchant-orders.service.ts`:

```typescript
import { apiClient } from '@/lib/api';
import type { MerchantOrderHistoryList, MerchantOrderList } from '@/lib/types/merchant.types';

export async function getMerchantOrders(storeId: string): Promise<MerchantOrderList> {
  const { data } = await apiClient.get<MerchantOrderList>(
    `/api/merchant/stores/${storeId}/orders`,
  );
  return data;
}

export async function getMerchantOrderHistory(storeId: string): Promise<MerchantOrderHistoryList> {
  const { data } = await apiClient.get<MerchantOrderHistoryList>(
    `/api/merchant/stores/${storeId}/orders/history`,
  );
  return data;
}
```

- [ ] **Step 5: Run service tests to pass**

Run:

```bash
cd apps/frontend && npx vitest run src/tests/merchant.services.test.ts
```

Expected: PASS for all service endpoint assertions.

---

## Task 3 — Merchant auth context, login page, and shell, TDD

**Files:**
- Create: `apps/frontend/src/lib/auth/MerchantAuthContext.tsx`
- Create: `apps/frontend/src/components/merchant/MerchantShell.tsx`
- Create: `apps/frontend/src/app/merchant/layout.tsx`
- Create: `apps/frontend/src/app/merchant/login/page.tsx`
- Create: `apps/frontend/src/tests/merchant.auth-shell.test.tsx`

- [ ] **Step 1: Write failing UI tests**

Create `apps/frontend/src/tests/merchant.auth-shell.test.tsx` covering:

```typescript
expect(screen.getByRole('link', { name: 'Dashboard' })).toHaveAttribute('aria-current', 'page');
expect(screen.getByRole('link', { name: 'Commandes' })).toBeInTheDocument();
expect(screen.getByText('Créneaux')).toHaveAttribute('aria-disabled', 'true');
expect(screen.getByText('Catalogue')).toHaveAttribute('aria-disabled', 'true');
expect(screen.getByText('Paramètres')).toHaveAttribute('aria-disabled', 'true');
```

Also cover login submit:

```typescript
await user.type(screen.getByLabelText('Email'), 'merchant@example.test');
await user.type(screen.getByLabelText('Mot de passe'), 'secret');
await user.click(screen.getByRole('button', { name: 'Se connecter' }));
expect(loginMerchant).toHaveBeenCalledWith({
  email: 'merchant@example.test',
  password: 'secret',
});
expect(getMerchantMe).toHaveBeenCalled();
```

- [ ] **Step 2: Run failing UI tests**

Run:

```bash
cd apps/frontend && npx vitest run src/tests/merchant.auth-shell.test.tsx
```

Expected: FAIL because merchant auth UI does not exist.

- [ ] **Step 3: Implement `MerchantAuthContext`**

Create a client component that exposes:

```typescript
interface MerchantAuthState {
  merchant: MerchantMe | null;
  loading: boolean;
  errorStatus: number | null;
  refresh: () => Promise<void>;
}
```

Behavior:
- Call `getMerchantMe()` from `refresh`.
- Set `errorStatus` to `401`, `403`, `404`, or `409` when the API response has that status.
- Keep `merchant.store.id` as the only active supérette source for child pages.

- [ ] **Step 4: Implement `MerchantShell`**

Create a shell that renders the active supérette name, merchant email, active links for `/merchant` and `/merchant/commandes`, and disabled entries:

```tsx
<span aria-disabled="true">Créneaux</span>
<span aria-disabled="true">Catalogue</span>
<span aria-disabled="true">Paramètres</span>
```

Disabled entries must not be anchors.

- [ ] **Step 5: Implement `/merchant/login`**

Create a client page with labels `Email`, `Mot de passe`, button `Se connecter`, and these error messages:

```typescript
const merchantLoginMessages: Record<number, string> = {
  401: 'Session expirée ou identifiants invalides.',
  403: 'Ce compte ne dispose pas d’un accès marchand.',
  404: 'Aucune supérette active n’est rattachée à ce compte marchand.',
  409: 'Plusieurs supérettes actives sont rattachées à ce compte. Contactez un administrateur.',
};
```

On success, redirect to `/merchant`.

- [ ] **Step 6: Run UI tests to pass**

Run:

```bash
cd apps/frontend && npx vitest run src/tests/merchant.auth-shell.test.tsx
```

Expected: PASS for login and shell assertions.

---

## Task 4 — Dashboard compact branché backend, TDD

**Files:**
- Create: `apps/frontend/src/app/merchant/page.tsx`
- Create: `apps/frontend/src/tests/merchant.dashboard.test.tsx`

- [ ] **Step 1: Write failing dashboard tests**

Create tests that mock `useMerchantAuth()` with `store.id = 'store-1'` and `getMerchantDashboardToday()` returning:

```typescript
{
  store_id: 'store-1',
  date: '2026-05-23',
  total_orders_today: 4,
  orders_by_status: { submitted: 2, accepted: 1, preparing: 1, ready: 0 },
  submitted_count: 2,
  accepted_count: 1,
  partially_accepted_count: 0,
  preparing_count: 1,
  ready_count: 0,
  cancelled_count: 0,
  rejected_count: 0,
  completed_count: 0,
  pickup_pending_count: 0,
  urgent_submitted_count: 1,
  pickup_slots_today: [
    { id: 'slot-1', starts_at: '10:00', ends_at: '11:00', order_count: 2, capacity: 5 },
  ],
}
```

Assert visible labels: `Commandes du jour`, `En attente`, `Acceptées`, `En préparation`, `Prêtes`, `10:00`, `11:00`, and no business action button named `Accepter`, `Refuser`, `Préparer`, `Prête`, `Retrait`.

- [ ] **Step 2: Run failing dashboard tests**

Run:

```bash
cd apps/frontend && npx vitest run src/tests/merchant.dashboard.test.tsx
```

Expected: FAIL because `/merchant` is not implemented.

- [ ] **Step 3: Implement dashboard page**

The page must:
- read `storeId` from `useMerchantAuth()`;
- call `getMerchantDashboardToday(storeId)`;
- show loading, empty, API error, and retry states;
- render counts in compact cards;
- render pickup slots in a simple list;
- keep the screen read-only.

- [ ] **Step 4: Run dashboard tests to pass**

Run:

```bash
cd apps/frontend && npx vitest run src/tests/merchant.dashboard.test.tsx
```

Expected: PASS for data, empty, error retry, and read-only assertions.

---

## Task 5 — Commandes page branchée backend, TDD

**Files:**
- Create: `apps/frontend/src/app/merchant/commandes/page.tsx`
- Create: `apps/frontend/src/tests/merchant.commandes.test.tsx`

- [ ] **Step 1: Write failing commandes tests**

Mock `getMerchantOrders('store-1')` returning:

```typescript
{
  items: [
    {
      id: 'order-1',
      status: 'submitted',
      total_tnd: '42.500',
      pickup_slot: {
        id: 'slot-1',
        starts_at: '2026-05-23T10:00:00+01:00',
        ends_at: '2026-05-23T11:00:00+01:00',
      },
      line_count: 3,
      created_at: '2026-05-23T08:30:00+01:00',
      updated_at: '2026-05-23T08:35:00+01:00',
    },
  ],
  total: 1,
  page: 1,
  limit: 20,
}
```

Assert visible labels: `Commandes`, `submitted`, `3 produits`, `42.500 TND`, `10:00`, `11:00`. Assert no action buttons named `Accepter`, `Refuser`, `Préparer`, `Déclarer prête`, `Scanner`.

- [ ] **Step 2: Run failing commandes tests**

Run:

```bash
cd apps/frontend && npx vitest run src/tests/merchant.commandes.test.tsx
```

Expected: FAIL because `/merchant/commandes` is not implemented.

- [ ] **Step 3: Implement commandes page**

The page must:
- read `storeId` from `useMerchantAuth()`;
- call `getMerchantOrders(storeId)`;
- display a read-only list;
- show an empty state `Aucune commande à afficher.`;
- show an API error with a `Réessayer` button;
- avoid detail links and mutation controls.

- [ ] **Step 4: Run commandes tests to pass**

Run:

```bash
cd apps/frontend && npx vitest run src/tests/merchant.commandes.test.tsx
```

Expected: PASS for list, empty, retry, and read-only assertions.

---

## Task 6 — Verification

**Files:**
- Verify all files changed by Tasks 1-5.

- [ ] **Step 1: Run backend focused tests**

Run:

```bash
cd apps/backend && vendor/bin/phpunit tests/Functional/Api/MerchantMeApiTest.php tests/Functional/Api/MerchantDashboardApiTest.php tests/Functional/Api/MerchantOrderApiTest.php tests/Functional/Api/MerchantOrderHistoryApiTest.php
```

Expected: PASS.

- [ ] **Step 2: Run frontend focused tests**

Run:

```bash
cd apps/frontend && npx vitest run src/tests/merchant.services.test.ts src/tests/merchant.auth-shell.test.tsx src/tests/merchant.dashboard.test.tsx src/tests/merchant.commandes.test.tsx
```

Expected: PASS.

- [ ] **Step 3: Run available static checks**

Run:

```bash
cd apps/backend && symfony console lint:container
cd apps/frontend && npm run lint
```

Expected: no reported errors.

- [ ] **Step 4: Manual MVP scope check**

Confirm the PR does not add:
- online payment;
- delivery;
- loyalty program;
- marketplace or multi-merchant Kadhia;
- order detail;
- accept/refuse/preparation/ready/retrait actions;
- créneaux or catalogue management;
- merchant theme or personalization.

---

## Self-Review

- Spec coverage: backend `GET /api/merchant/me`, merchant login, shell navigation, dashboard, commandes, disabled future sections, error states, exclusions, and recommended tests are all mapped to tasks.
- Placeholder scan: no open-ended implementation markers are used; all planned files and endpoint calls are named.
- Type consistency: `MerchantMe`, `MerchantDashboardToday`, `MerchantOrderList`, and service function names are introduced before UI tasks reference them.
