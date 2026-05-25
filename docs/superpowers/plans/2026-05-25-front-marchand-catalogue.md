# Front Marchand Catalogue Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the merchant catalogue workflow for Kadhia: list and edit a supérette catalogue, add products from the shared referential, manage bulk availability, support merchant-local products, merchant categories, and a guided assistant.

**Architecture:** Open the PR from Checkpoint A, then enrich it checkpoint by checkpoint. Frontend code lives in the merchant area with isolated catalogue services/types/components. Backend changes start only when needed for merchant-local products and merchant categories, using Symfony/API Platform resources, DTOs, processors/providers, Doctrine migrations, and functional tests.

**Tech Stack:** Next.js App Router, React, TypeScript, Tailwind, Vitest/Testing Library, Symfony 7, API Platform, Doctrine, PHPUnit, PHPStan.

---

## Scope And Checkpoints

Implement in this order:

1. **Checkpoint A:** base `/merchant/catalogue` page, shell navigation, list/search/filter, loading/empty/error states.
2. **Checkpoint B:** edit price/availability/visibility/note and bulk availability.
3. **Checkpoint C:** add from `ProductReference` search.
4. **Checkpoint D:** backend + frontend for merchant-local products that are sellable immediately.
5. **Checkpoint E:** backend + frontend for merchant categories and category overrides.
6. **Checkpoint F:** guided assistant built from existing catalogue operations.

Open the PR after Checkpoint A. Keep adding commits to the same PR after each checkpoint.

## File Structure

Frontend files:

- Modify: `apps/frontend/src/components/merchant/MerchantShell.tsx`
  - Activates the `Catalogue` navigation item.
- Create: `apps/frontend/src/lib/types/merchant-catalog.types.ts`
  - Holds catalogue-specific DTOs and payload types.
- Create: `apps/frontend/src/lib/services/merchant-catalog.service.ts`
  - Isolates merchant catalogue API calls.
- Create: `apps/frontend/src/app/merchant/catalogue/page.tsx`
  - Page-level state and orchestration.
- Create: `apps/frontend/src/components/merchant/catalogue/MerchantCatalogFilters.tsx`
  - Search and filter controls.
- Create: `apps/frontend/src/components/merchant/catalogue/MerchantCatalogTable.tsx`
  - Responsive list/table of merchant products.
- Create: `apps/frontend/src/components/merchant/catalogue/MerchantCatalogEditDrawer.tsx`
  - Edit form for a merchant product.
- Create: `apps/frontend/src/components/merchant/catalogue/MerchantCatalogBulkActions.tsx`
  - Bulk selection and availability actions.
- Create: `apps/frontend/src/components/merchant/catalogue/ProductReferenceSearchDrawer.tsx`
  - Search shared product references and add one to catalogue.
- Create: `apps/frontend/src/components/merchant/catalogue/MerchantLocalProductDrawer.tsx`
  - Create a local merchant product from a free-form product.
- Create: `apps/frontend/src/components/merchant/catalogue/MerchantCategorySelector.tsx`
  - Category selector with fallback display.
- Create: `apps/frontend/src/components/merchant/catalogue/MerchantCatalogWizard.tsx`
  - Guided add flow.
- Create: `apps/frontend/src/tests/merchant.catalog.service.test.ts`
- Create: `apps/frontend/src/tests/merchant.catalogue.test.tsx`

Backend files for Checkpoints D and E:

- Create: `apps/backend/src/Entity/MerchantLocalProduct.php`
- Create: `apps/backend/src/Entity/MerchantCategory.php`
- Modify: `apps/backend/src/Entity/MerchantProduct.php`
- Create: `apps/backend/src/Dto/MerchantLocalProductCreateInput.php`
- Create: `apps/backend/src/Dto/MerchantLocalProductUpdateInput.php`
- Create: `apps/backend/src/Dto/MerchantCategoryCreateInput.php`
- Create: `apps/backend/src/Dto/MerchantCategoryUpdateInput.php`
- Modify/Create API resources under `apps/backend/src/ApiResource/`
- Create processors/providers under `apps/backend/src/Processor/` and `apps/backend/src/Provider/`
- Modify repositories under `apps/backend/src/Repository/`
- Create Doctrine migrations under `apps/backend/migrations/`
- Create functional tests under `apps/backend/tests/Functional/`
- Update `docs/architecture/api-contract.md` after backend contracts are final.

---

### Task 1: Add Frontend Catalogue Types And Service

**Files:**
- Create: `apps/frontend/src/lib/types/merchant-catalog.types.ts`
- Create: `apps/frontend/src/lib/services/merchant-catalog.service.ts`
- Create: `apps/frontend/src/tests/merchant.catalog.service.test.ts`

- [ ] **Step 1: Write the service tests**

Create `apps/frontend/src/tests/merchant.catalog.service.test.ts`:

```ts
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  addMerchantCatalogProduct,
  bulkUpdateMerchantProductAvailability,
  listMerchantCatalog,
  searchMerchantProductReferences,
  updateMerchantCatalogProduct,
} from '@/lib/services/merchant-catalog.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
  },
}));

describe('merchant catalogue service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('lists merchant catalogue products with filters', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { items: [], total: 0, page: 1, limit: 20 },
    });

    await listMerchantCatalog('store-1', {
      q: 'lait',
      availability: 'available',
      visibility: 'visible',
      category: 'lait',
      page: 2,
      limit: 10,
    });

    expect(apiClient.get).toHaveBeenCalledWith('/api/merchant/stores/store-1/catalog', {
      params: {
        q: 'lait',
        availability: 'available',
        visibility: 'visible',
        category: 'lait',
        page: 2,
        limit: 10,
      },
    });
  });

  it('updates a merchant product', async () => {
    vi.mocked(apiClient.patch).mockResolvedValue({ data: null });

    await updateMerchantCatalogProduct('mp-1', {
      price_tnd: '1.700',
      is_available: false,
      is_visible: true,
      merchant_note: 'Rupture temporaire',
    });

    expect(apiClient.patch).toHaveBeenCalledWith('/api/merchant/catalog/mp-1', {
      price_tnd: '1.700',
      is_available: false,
      is_visible: true,
      merchant_note: 'Rupture temporaire',
    });
  });

  it('bulk updates availability for selected products', async () => {
    vi.mocked(apiClient.patch).mockResolvedValue({
      data: {
        updated_count: 2,
        is_available: false,
        merchant_note: 'Rupture',
        merchant_product_ids: ['mp-1', 'mp-2'],
      },
    });

    const result = await bulkUpdateMerchantProductAvailability('store-1', {
      merchant_product_ids: ['mp-1', 'mp-2'],
      is_available: false,
      merchant_note: 'Rupture',
    });

    expect(apiClient.patch).toHaveBeenCalledWith(
      '/api/merchant/stores/store-1/products/bulk-availability',
      {
        merchant_product_ids: ['mp-1', 'mp-2'],
        is_available: false,
        merchant_note: 'Rupture',
      },
    );
    expect(result.updated_count).toBe(2);
  });

  it('searches product references in the store context', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { items: [], total: 0, page: 1, limit: 20 },
    });

    await searchMerchantProductReferences('store-1', {
      q: 'vitalait',
      categorySlug: 'lait',
      page: 1,
      limit: 20,
    });

    expect(apiClient.get).toHaveBeenCalledWith(
      '/api/merchant/stores/store-1/product-references',
      {
        params: {
          q: 'vitalait',
          categorySlug: 'lait',
          page: 1,
          limit: 20,
        },
      },
    );
  });

  it('adds a product reference to the merchant catalogue', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: null });

    await addMerchantCatalogProduct('store-1', {
      product_reference_id: 'ref-1',
      price_tnd: '1.650',
      is_available: true,
      is_visible: true,
      merchant_note: null,
    });

    expect(apiClient.post).toHaveBeenCalledWith('/api/merchant/stores/store-1/catalog', {
      product_reference_id: 'ref-1',
      price_tnd: '1.650',
      is_available: true,
      is_visible: true,
      merchant_note: null,
    });
  });
});
```

- [ ] **Step 2: Run the service test to verify it fails**

Run:

```bash
cd apps/frontend
npm test -- merchant.catalog.service.test.ts
```

Expected: FAIL because `merchant-catalog.service.ts` and its types do not exist.

- [ ] **Step 3: Create catalogue types**

Create `apps/frontend/src/lib/types/merchant-catalog.types.ts`:

```ts
export type MerchantCatalogAvailabilityFilter = 'all' | 'available' | 'unavailable';
export type MerchantCatalogVisibilityFilter = 'all' | 'visible' | 'hidden';

export interface MerchantCatalogProduct {
  id: string;
  product_reference_id: string | null;
  local_product_id?: string | null;
  name_fr: string;
  name_ar?: string | null;
  brand: string | null;
  category: string;
  merchant_category_id?: string | null;
  merchant_category_name?: string | null;
  volume: string | null;
  unit: string;
  price_tnd: string;
  is_available: boolean;
  is_visible: boolean;
  merchant_note: string | null;
}

export interface MerchantCatalogList {
  items: MerchantCatalogProduct[];
  total: number;
  page: number;
  limit: number;
}

export interface MerchantCatalogListOptions {
  q?: string;
  availability?: MerchantCatalogAvailabilityFilter;
  visibility?: MerchantCatalogVisibilityFilter;
  category?: string;
  page?: number;
  limit?: number;
}

export interface UpdateMerchantCatalogProductPayload {
  price_tnd?: string;
  is_available?: boolean;
  is_visible?: boolean;
  merchant_note?: string | null;
  merchant_category_id?: string | null;
}

export interface MerchantProductReferenceSearchItem {
  id: string;
  name_fr: string;
  name_ar: string | null;
  brand_id: string;
  brand: string;
  category_id: string;
  category: string;
  category_ar: string | null;
  category_slug: string;
  volume: string | null;
  unit: string;
  barcode: string | null;
  already_in_catalog: boolean;
}

export interface MerchantProductReferenceSearchResult {
  items: MerchantProductReferenceSearchItem[];
  total: number;
  page: number;
  limit: number;
}

export interface MerchantProductReferenceSearchOptions {
  q?: string;
  brandId?: string;
  categorySlug?: string;
  page?: number;
  limit?: number;
}

export interface AddMerchantCatalogProductPayload {
  product_reference_id: string;
  price_tnd: string;
  is_available: boolean;
  is_visible: boolean;
  merchant_note: string | null;
}

export interface MerchantBulkAvailabilityPayload {
  merchant_product_ids: string[];
  is_available: boolean;
  merchant_note?: string | null;
}

export interface MerchantBulkAvailabilityResult {
  updated_count: number;
  is_available: boolean;
  merchant_note: string | null;
  merchant_product_ids: string[];
}
```

- [ ] **Step 4: Create the service implementation**

Create `apps/frontend/src/lib/services/merchant-catalog.service.ts`:

```ts
import { apiClient } from '@/lib/api';
import type {
  AddMerchantCatalogProductPayload,
  MerchantBulkAvailabilityPayload,
  MerchantBulkAvailabilityResult,
  MerchantCatalogList,
  MerchantCatalogListOptions,
  MerchantProductReferenceSearchOptions,
  MerchantProductReferenceSearchResult,
  UpdateMerchantCatalogProductPayload,
} from '@/lib/types/merchant-catalog.types';

function omitAllFilters<T extends Record<string, unknown>>(params: T): Partial<T> {
  return Object.fromEntries(
    Object.entries(params).filter(([, value]) => value !== undefined && value !== '' && value !== 'all'),
  ) as Partial<T>;
}

export async function listMerchantCatalog(
  storeId: string,
  options: MerchantCatalogListOptions = {},
): Promise<MerchantCatalogList> {
  const { data } = await apiClient.get<MerchantCatalogList>(
    `/api/merchant/stores/${storeId}/catalog`,
    {
      params: omitAllFilters({
        q: options.q,
        availability: options.availability,
        visibility: options.visibility,
        category: options.category,
        page: options.page ?? 1,
        limit: options.limit ?? 20,
      }),
    },
  );
  return data;
}

export async function updateMerchantCatalogProduct(
  merchantProductId: string,
  payload: UpdateMerchantCatalogProductPayload,
): Promise<void> {
  await apiClient.patch(`/api/merchant/catalog/${merchantProductId}`, payload);
}

export async function bulkUpdateMerchantProductAvailability(
  storeId: string,
  payload: MerchantBulkAvailabilityPayload,
): Promise<MerchantBulkAvailabilityResult> {
  const { data } = await apiClient.patch<MerchantBulkAvailabilityResult>(
    `/api/merchant/stores/${storeId}/products/bulk-availability`,
    payload,
  );
  return data;
}

export async function searchMerchantProductReferences(
  storeId: string,
  options: MerchantProductReferenceSearchOptions = {},
): Promise<MerchantProductReferenceSearchResult> {
  const { data } = await apiClient.get<MerchantProductReferenceSearchResult>(
    `/api/merchant/stores/${storeId}/product-references`,
    {
      params: omitAllFilters({
        q: options.q,
        brandId: options.brandId,
        categorySlug: options.categorySlug,
        page: options.page ?? 1,
        limit: options.limit ?? 20,
      }),
    },
  );
  return data;
}

export async function addMerchantCatalogProduct(
  storeId: string,
  payload: AddMerchantCatalogProductPayload,
): Promise<void> {
  await apiClient.post(`/api/merchant/stores/${storeId}/catalog`, payload);
}
```

- [ ] **Step 5: Run service tests**

Run:

```bash
cd apps/frontend
npm test -- merchant.catalog.service.test.ts
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add apps/frontend/src/lib/types/merchant-catalog.types.ts \
  apps/frontend/src/lib/services/merchant-catalog.service.ts \
  apps/frontend/src/tests/merchant.catalog.service.test.ts
git commit -m "feat(frontend): add merchant catalogue service"
```

---

### Task 2: Activate Merchant Catalogue Navigation

**Files:**
- Modify: `apps/frontend/src/components/merchant/MerchantShell.tsx`
- Modify: `apps/frontend/src/tests/merchant.shell.test.tsx`

- [ ] **Step 1: Add a failing shell test**

In `apps/frontend/src/tests/merchant.shell.test.tsx`, add:

```ts
it('shows Catalogue as an active merchant navigation link', () => {
  render(
    <MerchantShell>
      <div>Contenu marchand</div>
    </MerchantShell>,
  );

  expect(screen.getAllByRole('link', { name: /Catalogue/i })[0]).toHaveAttribute(
    'href',
    '/merchant/catalogue',
  );
});
```

- [ ] **Step 2: Run the shell test to verify it fails**

Run:

```bash
cd apps/frontend
npm test -- merchant.shell.test.tsx
```

Expected: FAIL because `Catalogue` is still disabled.

- [ ] **Step 3: Modify `MerchantShell.tsx`**

Move `Catalogue` from `DISABLED_NAV` to `ACTIVE_NAV`:

```ts
const ACTIVE_NAV = [
  { href: '/merchant', label: 'Dashboard', icon: BarChart3 },
  { href: '/merchant/commandes', label: 'Commandes', icon: ShoppingBasket },
  { href: '/merchant/catalogue', label: 'Catalogue', icon: Package },
  { href: '/merchant/retrait', label: 'Retrait', icon: QrCode },
  { href: '/merchant/notifications', label: 'Notifications', icon: Bell, badge: 'notifications' },
];

const DISABLED_NAV = [
  { label: 'Créneaux', icon: CalendarClock },
  { label: 'Paramètres', icon: Settings },
];
```

- [ ] **Step 4: Run the shell test**

Run:

```bash
cd apps/frontend
npm test -- merchant.shell.test.tsx
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/frontend/src/components/merchant/MerchantShell.tsx \
  apps/frontend/src/tests/merchant.shell.test.tsx
git commit -m "feat(frontend): activate merchant catalogue navigation"
```

---

### Task 3: Build Checkpoint A Catalogue Page

**Files:**
- Create: `apps/frontend/src/app/merchant/catalogue/page.tsx`
- Create: `apps/frontend/src/components/merchant/catalogue/MerchantCatalogFilters.tsx`
- Create: `apps/frontend/src/components/merchant/catalogue/MerchantCatalogTable.tsx`
- Create: `apps/frontend/src/tests/merchant.catalogue.test.tsx`

- [ ] **Step 1: Write failing page tests**

Create `apps/frontend/src/tests/merchant.catalogue.test.tsx`:

```tsx
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MerchantCataloguePage from '@/app/merchant/catalogue/page';
import { listMerchantCatalog } from '@/lib/services/merchant-catalog.service';

const merchantContext = {
  merchant: {
    store: { id: 'store-1', name: 'Supérette Ezzahra', active: true },
  },
};

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => merchantContext,
}));

vi.mock('@/lib/services/merchant-catalog.service', () => ({
  listMerchantCatalog: vi.fn(),
}));

describe('MerchantCataloguePage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(listMerchantCatalog).mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
      limit: 20,
    });
  });

  it('renders merchant catalogue products with TND prices and category fallback', async () => {
    vi.mocked(listMerchantCatalog).mockResolvedValue({
      items: [
        {
          id: 'mp-1',
          product_reference_id: 'ref-1',
          name_fr: 'Lait demi-écrémé',
          brand: 'Vitalait',
          category: 'Lait & produits laitiers',
          volume: '1',
          unit: 'litre',
          price_tnd: '1.650',
          is_available: true,
          is_visible: true,
          merchant_note: null,
        },
      ],
      total: 1,
      page: 1,
      limit: 20,
    });

    render(React.createElement(MerchantCataloguePage));

    await waitFor(() => expect(listMerchantCatalog).toHaveBeenCalledWith('store-1', {
      page: 1,
      limit: 20,
    }));
    expect(screen.getByRole('heading', { name: 'Catalogue' })).toBeInTheDocument();
    expect(screen.getByText('Lait demi-écrémé')).toBeInTheDocument();
    expect(screen.getByText('Vitalait')).toBeInTheDocument();
    expect(screen.getByText('Lait & produits laitiers')).toBeInTheDocument();
    expect(screen.getByText('1,650 TND')).toBeInTheDocument();
    expect(screen.getByText('Disponible')).toBeInTheDocument();
    expect(screen.getByText('Visible')).toBeInTheDocument();
  });

  it('filters catalogue search', async () => {
    render(React.createElement(MerchantCataloguePage));

    fireEvent.change(await screen.findByLabelText('Rechercher dans le catalogue'), {
      target: { value: 'lait' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Rechercher' }));

    await waitFor(() =>
      expect(listMerchantCatalog).toHaveBeenLastCalledWith('store-1', {
        q: 'lait',
        availability: 'all',
        visibility: 'all',
        page: 1,
        limit: 20,
      }),
    );
  });

  it('renders empty and error states', async () => {
    vi.mocked(listMerchantCatalog).mockRejectedValueOnce(new Error('Network'));

    render(React.createElement(MerchantCataloguePage));

    expect(await screen.findByText('Impossible de charger le catalogue.')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Réessayer' }));
    expect(await screen.findByText('Aucun produit dans ce catalogue.')).toBeInTheDocument();
  });
});
```

- [ ] **Step 2: Run the page test to verify it fails**

Run:

```bash
cd apps/frontend
npm test -- merchant.catalogue.test.tsx
```

Expected: FAIL because the page and components do not exist.

- [ ] **Step 3: Create `MerchantCatalogFilters.tsx`**

Create `apps/frontend/src/components/merchant/catalogue/MerchantCatalogFilters.tsx`:

```tsx
'use client';

import { FormEvent, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { cn } from '@/lib/cn';
import type {
  MerchantCatalogAvailabilityFilter,
  MerchantCatalogVisibilityFilter,
} from '@/lib/types/merchant-catalog.types';

interface MerchantCatalogFiltersProps {
  q: string;
  availability: MerchantCatalogAvailabilityFilter;
  visibility: MerchantCatalogVisibilityFilter;
  onApply: (filters: {
    q: string;
    availability: MerchantCatalogAvailabilityFilter;
    visibility: MerchantCatalogVisibilityFilter;
  }) => void;
}

const availabilityOptions: Array<{ value: MerchantCatalogAvailabilityFilter; label: string }> = [
  { value: 'all', label: 'Tous' },
  { value: 'available', label: 'Disponibles' },
  { value: 'unavailable', label: 'Indisponibles' },
];

export function MerchantCatalogFilters({
  q,
  availability,
  visibility,
  onApply,
}: MerchantCatalogFiltersProps) {
  const [draftQ, setDraftQ] = useState(q);
  const [draftAvailability, setDraftAvailability] = useState(availability);
  const [draftVisibility, setDraftVisibility] = useState(visibility);

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    onApply({
      q: draftQ.trim(),
      availability: draftAvailability,
      visibility: draftVisibility,
    });
  };

  return (
    <form onSubmit={submit} className="rounded-md bg-card p-4 shadow-card">
      <div className="grid gap-3 md:grid-cols-[1fr_auto]">
        <label className="block">
          <span className="text-sm font-bold text-ink">Rechercher dans le catalogue</span>
          <input
            aria-label="Rechercher dans le catalogue"
            value={draftQ}
            onChange={(event) => setDraftQ(event.target.value)}
            className="mt-1 min-h-11 w-full rounded-md border border-line bg-white px-3 text-sm"
            placeholder="Nom, marque, catégorie"
          />
        </label>
        <Button type="submit" size="md" className="self-end">
          Rechercher
        </Button>
      </div>

      <div className="mt-4 flex flex-wrap gap-2">
        {availabilityOptions.map((option) => (
          <button
            key={option.value}
            type="button"
            className={cn(
              'rounded-md px-3 py-2 text-sm font-bold',
              draftAvailability === option.value ? 'bg-primary text-white' : 'bg-soft text-muted',
            )}
            onClick={() => setDraftAvailability(option.value)}
          >
            {option.label}
          </button>
        ))}
        <button
          type="button"
          className={cn(
            'rounded-md px-3 py-2 text-sm font-bold',
            draftVisibility === 'hidden' ? 'bg-primary text-white' : 'bg-soft text-muted',
          )}
          onClick={() => setDraftVisibility(draftVisibility === 'hidden' ? 'all' : 'hidden')}
        >
          Masqués
        </button>
      </div>
    </form>
  );
}
```

- [ ] **Step 4: Create `MerchantCatalogTable.tsx`**

Create `apps/frontend/src/components/merchant/catalogue/MerchantCatalogTable.tsx`:

```tsx
'use client';

import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { formatTnd } from '@/lib/format';
import type { MerchantCatalogProduct } from '@/lib/types/merchant-catalog.types';

interface MerchantCatalogTableProps {
  products: MerchantCatalogProduct[];
  onEdit: (product: MerchantCatalogProduct) => void;
}

function formatProductMeta(product: MerchantCatalogProduct): string {
  return [product.brand, product.volume, product.unit].filter(Boolean).join(' · ');
}

export function MerchantCatalogTable({ products, onEdit }: MerchantCatalogTableProps) {
  if (products.length === 0) {
    return <p className="rounded-md bg-card p-5 text-sm text-muted shadow-card">Aucun produit dans ce catalogue.</p>;
  }

  return (
    <section className="rounded-md bg-card shadow-card">
      <div className="divide-y divide-line">
        {products.map((product) => (
          <article
            key={product.id}
            className="grid gap-3 p-4 md:grid-cols-[1fr_auto_auto]"
          >
            <div>
              <div className="flex flex-wrap items-center gap-2">
                <h2 className="text-base font-black">{product.name_fr}</h2>
                <Badge tone={product.is_available ? 'ready' : 'cancel'}>
                  {product.is_available ? 'Disponible' : 'Indisponible'}
                </Badge>
                <Badge tone={product.is_visible ? 'info' : 'default'}>
                  {product.is_visible ? 'Visible' : 'Masqué'}
                </Badge>
              </div>
              <p className="mt-1 text-sm text-muted">{formatProductMeta(product)}</p>
              <p className="mt-1 text-sm font-bold text-ink">
                {product.merchant_category_name ?? product.category}
              </p>
              {product.merchant_note && (
                <p className="mt-1 text-sm text-muted">{product.merchant_note}</p>
              )}
            </div>
            <strong className="text-lg">{formatTnd(product.price_tnd)}</strong>
            <Button type="button" variant="ghost" size="md" onClick={() => onEdit(product)}>
              Modifier
            </Button>
          </article>
        ))}
      </div>
    </section>
  );
}
```

- [ ] **Step 5: Create the page**

Create `apps/frontend/src/app/merchant/catalogue/page.tsx`:

```tsx
'use client';

import { useCallback, useEffect, useState } from 'react';
import { MerchantCatalogFilters } from '@/components/merchant/catalogue/MerchantCatalogFilters';
import { MerchantCatalogTable } from '@/components/merchant/catalogue/MerchantCatalogTable';
import { Button } from '@/components/ui/Button';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import { listMerchantCatalog } from '@/lib/services/merchant-catalog.service';
import type {
  MerchantCatalogAvailabilityFilter,
  MerchantCatalogList,
  MerchantCatalogProduct,
  MerchantCatalogVisibilityFilter,
} from '@/lib/types/merchant-catalog.types';

const CATALOGUE_PAGE_LIMIT = 20;

export default function MerchantCataloguePage() {
  const { merchant } = useMerchantAuth();
  const [catalogue, setCatalogue] = useState<MerchantCatalogList | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [q, setQ] = useState('');
  const [availability, setAvailability] = useState<MerchantCatalogAvailabilityFilter>('all');
  const [visibility, setVisibility] = useState<MerchantCatalogVisibilityFilter>('all');

  const loadCatalogue = useCallback(async () => {
    if (!merchant) return;
    setIsLoading(true);
    setError(null);
    try {
      const filters = {
        ...(q ? { q } : {}),
        ...(availability !== 'all' ? { availability } : {}),
        ...(visibility !== 'all' ? { visibility } : {}),
        page: 1,
        limit: CATALOGUE_PAGE_LIMIT,
      };
      setCatalogue(await listMerchantCatalog(merchant.store.id, filters));
    } catch {
      setCatalogue(null);
      setError('Impossible de charger le catalogue.');
    } finally {
      setIsLoading(false);
    }
  }, [availability, merchant, q, visibility]);

  useEffect(() => {
    void loadCatalogue();
  }, [loadCatalogue]);

  const handleEdit = (_product: MerchantCatalogProduct) => {
    // Checkpoint B opens the edit drawer here.
  };

  return (
    <div>
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 className="text-h1 font-black">Catalogue</h1>
          <p className="mt-1 text-sm text-muted">
            Mets à jour les produits proposés par ta supérette.
          </p>
        </div>
        <Button variant="ghost" size="md" onClick={() => void loadCatalogue()}>
          Réessayer
        </Button>
      </div>

      <div className="mt-5">
        <MerchantCatalogFilters
          q={q}
          availability={availability}
          visibility={visibility}
          onApply={(filters) => {
            setQ(filters.q);
            setAvailability(filters.availability);
            setVisibility(filters.visibility);
          }}
        />
      </div>

      {error && (
        <div className="mt-4 rounded-md bg-status-cancel-bg px-4 py-3 text-sm text-status-cancel">
          {error}
        </div>
      )}

      <div className="mt-5">
        {isLoading ? (
          <p className="rounded-md bg-card p-5 text-sm text-muted shadow-card">Chargement du catalogue…</p>
        ) : (
          <MerchantCatalogTable products={catalogue?.items ?? []} onEdit={handleEdit} />
        )}
      </div>
    </div>
  );
}
```

- [ ] **Step 6: Run Checkpoint A tests**

Run:

```bash
cd apps/frontend
npm test -- merchant.catalog.service.test.ts merchant.catalogue.test.tsx merchant.shell.test.tsx
```

Expected: PASS.

- [ ] **Step 7: Commit Checkpoint A**

```bash
git add apps/frontend/src/app/merchant/catalogue/page.tsx \
  apps/frontend/src/components/merchant/catalogue/MerchantCatalogFilters.tsx \
  apps/frontend/src/components/merchant/catalogue/MerchantCatalogTable.tsx \
  apps/frontend/src/tests/merchant.catalogue.test.tsx
git commit -m "feat(frontend): add merchant catalogue page"
```

At this point, open the PR.

---

### Task 4: Add Checkpoint B Edit Drawer And Bulk Availability

**Files:**
- Create: `apps/frontend/src/components/merchant/catalogue/MerchantCatalogEditDrawer.tsx`
- Create: `apps/frontend/src/components/merchant/catalogue/MerchantCatalogBulkActions.tsx`
- Modify: `apps/frontend/src/app/merchant/catalogue/page.tsx`
- Modify: `apps/frontend/src/tests/merchant.catalogue.test.tsx`

- [ ] **Step 1: Add failing tests for edit and bulk**

Append tests to `apps/frontend/src/tests/merchant.catalogue.test.tsx`:

```tsx
it('updates price availability visibility and note from the edit drawer', async () => {
  const { updateMerchantCatalogProduct } = await import('@/lib/services/merchant-catalog.service');
  vi.mocked(listMerchantCatalog).mockResolvedValue({
    items: [{
      id: 'mp-1',
      product_reference_id: 'ref-1',
      name_fr: 'Lait',
      brand: 'Vitalait',
      category: 'Lait',
      volume: '1',
      unit: 'litre',
      price_tnd: '1.650',
      is_available: true,
      is_visible: true,
      merchant_note: null,
    }],
    total: 1,
    page: 1,
    limit: 20,
  });
  vi.mocked(updateMerchantCatalogProduct).mockResolvedValue();

  render(React.createElement(MerchantCataloguePage));

  fireEvent.click(await screen.findByRole('button', { name: 'Modifier' }));
  fireEvent.change(screen.getByLabelText('Prix TND'), { target: { value: '1.700' } });
  fireEvent.click(screen.getByLabelText('Disponible'));
  fireEvent.change(screen.getByLabelText('Note marchand'), { target: { value: 'Rupture temporaire' } });
  fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

  await waitFor(() =>
    expect(updateMerchantCatalogProduct).toHaveBeenCalledWith('mp-1', {
      price_tnd: '1.700',
      is_available: false,
      is_visible: true,
      merchant_note: 'Rupture temporaire',
    }),
  );
});

it('blocks bulk selection above 50 products', async () => {
  vi.mocked(listMerchantCatalog).mockResolvedValue({
    items: Array.from({ length: 51 }, (_, index) => ({
      id: `mp-${index}`,
      product_reference_id: `ref-${index}`,
      name_fr: `Produit ${index}`,
      brand: 'Marque',
      category: 'Catégorie',
      volume: null,
      unit: 'piece',
      price_tnd: '1.000',
      is_available: true,
      is_visible: true,
      merchant_note: null,
    })),
    total: 51,
    page: 1,
    limit: 51,
  });

  render(React.createElement(MerchantCataloguePage));

  fireEvent.click(await screen.findByRole('button', { name: 'Mode sélection' }));
  for (const checkbox of screen.getAllByRole('checkbox', { name: /Sélectionner Produit/ })) {
    fireEvent.click(checkbox);
  }

  expect(screen.getByText('La sélection est limitée à 50 produits.')).toBeInTheDocument();
});
```

Also update the service mock in the test file:

```ts
vi.mock('@/lib/services/merchant-catalog.service', () => ({
  bulkUpdateMerchantProductAvailability: vi.fn(),
  listMerchantCatalog: vi.fn(),
  updateMerchantCatalogProduct: vi.fn(),
}));
```

- [ ] **Step 2: Run tests to verify failure**

Run:

```bash
cd apps/frontend
npm test -- merchant.catalogue.test.tsx
```

Expected: FAIL because edit and bulk components do not exist.

- [ ] **Step 3: Create `MerchantCatalogEditDrawer.tsx`**

Create a drawer component with controlled form fields:

```tsx
'use client';

import { FormEvent, useEffect, useState } from 'react';
import { Button } from '@/components/ui/Button';
import type {
  MerchantCatalogProduct,
  UpdateMerchantCatalogProductPayload,
} from '@/lib/types/merchant-catalog.types';

interface MerchantCatalogEditDrawerProps {
  product: MerchantCatalogProduct | null;
  isSaving: boolean;
  error: string | null;
  onClose: () => void;
  onSave: (productId: string, payload: UpdateMerchantCatalogProductPayload) => void;
}

export function MerchantCatalogEditDrawer({
  product,
  isSaving,
  error,
  onClose,
  onSave,
}: MerchantCatalogEditDrawerProps) {
  const [priceTnd, setPriceTnd] = useState('');
  const [isAvailable, setIsAvailable] = useState(true);
  const [isVisible, setIsVisible] = useState(true);
  const [merchantNote, setMerchantNote] = useState('');

  useEffect(() => {
    if (!product) return;
    setPriceTnd(product.price_tnd);
    setIsAvailable(product.is_available);
    setIsVisible(product.is_visible);
    setMerchantNote(product.merchant_note ?? '');
  }, [product]);

  if (!product) return null;

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!/^\d+([.]\d{1,3})?$/.test(priceTnd) || Number(priceTnd) <= 0) {
      return;
    }
    onSave(product.id, {
      price_tnd: Number(priceTnd).toFixed(3),
      is_available: isAvailable,
      is_visible: isVisible,
      merchant_note: merchantNote.trim() || null,
    });
  };

  return (
    <div role="dialog" aria-modal="true" aria-label={`Modifier ${product.name_fr}`} className="fixed inset-0 z-50 bg-black/30">
      <div className="ml-auto flex h-full w-full max-w-lg flex-col bg-card p-5 shadow-card">
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-xl font-black">Modifier le produit</h2>
            <p className="text-sm text-muted">{product.name_fr}</p>
          </div>
          <Button type="button" variant="ghost" size="md" onClick={onClose}>
            Fermer
          </Button>
        </div>

        <form onSubmit={submit} className="mt-5 space-y-4">
          <label className="block">
            <span className="text-sm font-bold">Prix TND</span>
            <input
              aria-label="Prix TND"
              value={priceTnd}
              onChange={(event) => setPriceTnd(event.target.value)}
              className="mt-1 min-h-11 w-full rounded-md border border-line px-3"
            />
          </label>
          <label className="flex items-center gap-2 text-sm font-bold">
            <input
              aria-label="Disponible"
              type="checkbox"
              checked={isAvailable}
              onChange={(event) => setIsAvailable(event.target.checked)}
            />
            Disponible
          </label>
          <label className="flex items-center gap-2 text-sm font-bold">
            <input
              aria-label="Visible"
              type="checkbox"
              checked={isVisible}
              onChange={(event) => setIsVisible(event.target.checked)}
            />
            Visible
          </label>
          <label className="block">
            <span className="text-sm font-bold">Note marchand</span>
            <textarea
              aria-label="Note marchand"
              value={merchantNote}
              onChange={(event) => setMerchantNote(event.target.value)}
              className="mt-1 min-h-24 w-full rounded-md border border-line px-3 py-2"
            />
          </label>
          <p className="text-sm text-muted">Catégorie : {product.merchant_category_name ?? product.category}</p>
          {error && <p className="text-sm text-status-cancel">{error}</p>}
          <Button type="submit" disabled={isSaving}>
            Enregistrer
          </Button>
        </form>
      </div>
    </div>
  );
}
```

- [ ] **Step 4: Create `MerchantCatalogBulkActions.tsx`**

Create a bulk actions component:

```tsx
'use client';

import { Button } from '@/components/ui/Button';

interface MerchantCatalogBulkActionsProps {
  selectionCount: number;
  error: string | null;
  onStartSelection: () => void;
  onCancelSelection: () => void;
  onMarkUnavailable: () => void;
  onMarkAvailable: () => void;
  isSelecting: boolean;
  isSaving: boolean;
}

export function MerchantCatalogBulkActions({
  selectionCount,
  error,
  onStartSelection,
  onCancelSelection,
  onMarkUnavailable,
  onMarkAvailable,
  isSelecting,
  isSaving,
}: MerchantCatalogBulkActionsProps) {
  if (!isSelecting) {
    return (
      <Button type="button" variant="ghost" size="md" onClick={onStartSelection}>
        Mode sélection
      </Button>
    );
  }

  return (
    <div className="rounded-md bg-card p-4 shadow-card">
      <div className="flex flex-wrap items-center gap-2">
        <strong>{selectionCount} produit{selectionCount > 1 ? 's' : ''} sélectionné{selectionCount > 1 ? 's' : ''}</strong>
        <Button type="button" size="md" disabled={selectionCount === 0 || isSaving} onClick={onMarkUnavailable}>
          Marquer indisponible
        </Button>
        <Button type="button" variant="ghost" size="md" disabled={selectionCount === 0 || isSaving} onClick={onMarkAvailable}>
          Remettre disponible
        </Button>
        <Button type="button" variant="ghost" size="md" onClick={onCancelSelection}>
          Annuler
        </Button>
      </div>
      {error && <p className="mt-2 text-sm text-status-cancel">{error}</p>}
    </div>
  );
}
```

- [ ] **Step 5: Wire edit and bulk into page/table**

Modify `MerchantCatalogTable` to accept selection props:

```tsx
interface MerchantCatalogTableProps {
  products: MerchantCatalogProduct[];
  onEdit: (product: MerchantCatalogProduct) => void;
  isSelecting?: boolean;
  selectedIds?: string[];
  onToggleSelection?: (productId: string, productName: string) => void;
}
```

Render a checkbox before product title when `isSelecting` is true:

```tsx
{isSelecting && (
  <input
    type="checkbox"
    aria-label={`Sélectionner ${product.name_fr}`}
    checked={selectedIds?.includes(product.id) ?? false}
    onChange={() => onToggleSelection?.(product.id, product.name_fr)}
  />
)}
```

Modify `page.tsx` to import and call:

```ts
import { MerchantCatalogBulkActions } from '@/components/merchant/catalogue/MerchantCatalogBulkActions';
import { MerchantCatalogEditDrawer } from '@/components/merchant/catalogue/MerchantCatalogEditDrawer';
import {
  bulkUpdateMerchantProductAvailability,
  listMerchantCatalog,
  updateMerchantCatalogProduct,
} from '@/lib/services/merchant-catalog.service';
```

Add state:

```ts
const [editProduct, setEditProduct] = useState<MerchantCatalogProduct | null>(null);
const [isSavingEdit, setIsSavingEdit] = useState(false);
const [editError, setEditError] = useState<string | null>(null);
const [isSelecting, setIsSelecting] = useState(false);
const [selectedIds, setSelectedIds] = useState<string[]>([]);
const [bulkError, setBulkError] = useState<string | null>(null);
const [isSavingBulk, setIsSavingBulk] = useState(false);
```

Add save handlers:

```ts
const saveEdit = async (productId: string, payload: UpdateMerchantCatalogProductPayload) => {
  setIsSavingEdit(true);
  setEditError(null);
  try {
    await updateMerchantCatalogProduct(productId, payload);
    setEditProduct(null);
    await loadCatalogue();
  } catch {
    setEditError("Impossible d'enregistrer le produit.");
  } finally {
    setIsSavingEdit(false);
  }
};

const toggleSelection = (productId: string) => {
  setBulkError(null);
  setSelectedIds((current) => {
    if (current.includes(productId)) {
      return current.filter((id) => id !== productId);
    }
    if (current.length >= 50) {
      setBulkError('La sélection est limitée à 50 produits.');
      return current;
    }
    return [...current, productId];
  });
};

const applyBulkAvailability = async (isAvailable: boolean) => {
  if (!merchant || selectedIds.length === 0) return;
  setIsSavingBulk(true);
  setBulkError(null);
  try {
    await bulkUpdateMerchantProductAvailability(merchant.store.id, {
      merchant_product_ids: selectedIds,
      is_available: isAvailable,
      merchant_note: isAvailable ? null : 'Rupture temporaire',
    });
    setSelectedIds([]);
    setIsSelecting(false);
    await loadCatalogue();
  } catch {
    setBulkError("Impossible de mettre à jour les produits sélectionnés.");
  } finally {
    setIsSavingBulk(false);
  }
};
```

- [ ] **Step 6: Run Checkpoint B tests**

Run:

```bash
cd apps/frontend
npm test -- merchant.catalog.service.test.ts merchant.catalogue.test.tsx
```

Expected: PASS.

- [ ] **Step 7: Commit Checkpoint B**

```bash
git add apps/frontend/src/app/merchant/catalogue/page.tsx \
  apps/frontend/src/components/merchant/catalogue/MerchantCatalogTable.tsx \
  apps/frontend/src/components/merchant/catalogue/MerchantCatalogEditDrawer.tsx \
  apps/frontend/src/components/merchant/catalogue/MerchantCatalogBulkActions.tsx \
  apps/frontend/src/tests/merchant.catalogue.test.tsx
git commit -m "feat(frontend): edit merchant catalogue products"
```

---

### Task 5: Add Checkpoint C Product Reference Search And Add

**Files:**
- Create: `apps/frontend/src/components/merchant/catalogue/ProductReferenceSearchDrawer.tsx`
- Modify: `apps/frontend/src/app/merchant/catalogue/page.tsx`
- Modify: `apps/frontend/src/tests/merchant.catalogue.test.tsx`

- [ ] **Step 1: Add failing tests**

Add a test that clicks `Ajouter un produit`, searches references, blocks already-in-catalog products, and adds an available result:

```tsx
it('adds a product reference to the catalogue', async () => {
  const {
    addMerchantCatalogProduct,
    searchMerchantProductReferences,
  } = await import('@/lib/services/merchant-catalog.service');
  vi.mocked(searchMerchantProductReferences).mockResolvedValue({
    items: [
      {
        id: 'ref-1',
        name_fr: 'Couscous fin',
        name_ar: null,
        brand_id: 'brand-1',
        brand: 'Dari',
        category_id: 'cat-1',
        category: 'Epicerie',
        category_ar: null,
        category_slug: 'epicerie',
        volume: '1',
        unit: 'kilogramme',
        barcode: null,
        already_in_catalog: false,
      },
    ],
    total: 1,
    page: 1,
    limit: 20,
  });
  vi.mocked(addMerchantCatalogProduct).mockResolvedValue();

  render(React.createElement(MerchantCataloguePage));

  fireEvent.click(await screen.findByRole('button', { name: 'Ajouter un produit' }));
  fireEvent.change(screen.getByLabelText('Rechercher dans le référentiel'), {
    target: { value: 'couscous' },
  });
  fireEvent.click(screen.getByRole('button', { name: 'Chercher' }));
  fireEvent.click(await screen.findByRole('button', { name: 'Ajouter Couscous fin' }));
  fireEvent.change(screen.getByLabelText('Prix TND'), { target: { value: '2.400' } });
  fireEvent.click(screen.getByRole('button', { name: 'Ajouter à mon catalogue' }));

  await waitFor(() =>
    expect(addMerchantCatalogProduct).toHaveBeenCalledWith('store-1', {
      product_reference_id: 'ref-1',
      price_tnd: '2.400',
      is_available: true,
      is_visible: true,
      merchant_note: null,
    }),
  );
});
```

Update service mock:

```ts
vi.mock('@/lib/services/merchant-catalog.service', () => ({
  addMerchantCatalogProduct: vi.fn(),
  bulkUpdateMerchantProductAvailability: vi.fn(),
  listMerchantCatalog: vi.fn(),
  searchMerchantProductReferences: vi.fn(),
  updateMerchantCatalogProduct: vi.fn(),
}));
```

- [ ] **Step 2: Run tests to verify failure**

Run:

```bash
cd apps/frontend
npm test -- merchant.catalogue.test.tsx
```

Expected: FAIL because the drawer is not implemented.

- [ ] **Step 3: Create `ProductReferenceSearchDrawer.tsx`**

Create a drawer with search and add form. Required behaviors:

```tsx
export function ProductReferenceSearchDrawer({
  storeId,
  isOpen,
  onClose,
  onAdded,
}: {
  storeId: string;
  isOpen: boolean;
  onClose: () => void;
  onAdded: () => void;
}) {
  // State: q, results, selected reference, price, availability, visibility, note, error.
  // Search calls searchMerchantProductReferences(storeId, { q, page: 1, limit: 20 }).
  // Result button is disabled when already_in_catalog is true.
  // Add calls addMerchantCatalogProduct(storeId, payload), then onAdded() and onClose().
}
```

Use these exact labels for tests and accessibility:

- Search input: `Rechercher dans le référentiel`
- Search button: `Chercher`
- Add result button: `Ajouter ${reference.name_fr}`
- Price input: `Prix TND`
- Submit button: `Ajouter à mon catalogue`

- [ ] **Step 4: Wire drawer into page**

In `page.tsx`:

```ts
const [isAddDrawerOpen, setIsAddDrawerOpen] = useState(false);
```

Render button near page header:

```tsx
<Button type="button" onClick={() => setIsAddDrawerOpen(true)}>
  Ajouter un produit
</Button>
```

Render drawer:

```tsx
{merchant && (
  <ProductReferenceSearchDrawer
    storeId={merchant.store.id}
    isOpen={isAddDrawerOpen}
    onClose={() => setIsAddDrawerOpen(false)}
    onAdded={() => void loadCatalogue()}
  />
)}
```

- [ ] **Step 5: Run Checkpoint C tests**

Run:

```bash
cd apps/frontend
npm test -- merchant.catalog.service.test.ts merchant.catalogue.test.tsx
```

Expected: PASS.

- [ ] **Step 6: Commit Checkpoint C**

```bash
git add apps/frontend/src/app/merchant/catalogue/page.tsx \
  apps/frontend/src/components/merchant/catalogue/ProductReferenceSearchDrawer.tsx \
  apps/frontend/src/tests/merchant.catalogue.test.tsx
git commit -m "feat(frontend): add products from referential"
```

---

### Task 6: Add Checkpoint D Backend Merchant Local Product

**Files:**
- Create: `apps/backend/src/Entity/MerchantLocalProduct.php`
- Modify: `apps/backend/src/Entity/MerchantProduct.php`
- Create DTOs/processors/providers/API resources for merchant-local products.
- Create migration under `apps/backend/migrations/`
- Create: `apps/backend/tests/Functional/MerchantLocalProductApiTest.php`

- [ ] **Step 1: Write backend functional tests first**

Create `apps/backend/tests/Functional/MerchantLocalProductApiTest.php` with tests for:

```php
public function testMerchantCreatesLocalProductAndCatalogueOffer(): void
{
    // Login as merchant owner of store A.
    // POST /api/merchant/stores/{storeId}/local-products with name, category-like label, price_tnd, visibility.
    // Assert 201 and returned merchant_product_id.
    // GET /api/merchant/stores/{storeId}/catalog.
    // Assert the local product appears and product_reference_id is null.
}

public function testMerchantCannotCreateLocalProductForAnotherStore(): void
{
    // Login as merchant A.
    // POST local product to store B.
    // Assert 403.
}

public function testLocalProductDoesNotCreateApprovedProductReference(): void
{
    // Create local product.
    // Query ProductReference repository by the local product name.
    // Assert no approved ProductReference was created.
}
```

Use the existing functional test helpers already used by merchant API tests. If exact helper names differ, copy the pattern from `apps/backend/tests/Functional/*Merchant*ApiTest.php`.

- [ ] **Step 2: Run the new backend test to verify failure**

Run:

```bash
cd apps/backend
php bin/phpunit tests/Functional/MerchantLocalProductApiTest.php
```

Expected: FAIL because the entity and endpoint do not exist.

- [ ] **Step 3: Add `MerchantLocalProduct` entity**

Create fields:

```php
private Uuid $id;
private Shop $shop;
private string $nameFr;
private ?string $nameAr = null;
private ?string $brandName = null;
private ?string $volume = null;
private string $unit;
private ?string $barcode = null;
private ?string $defaultCategoryName = null;
private \DateTimeImmutable $createdAt;
private \DateTimeImmutable $updatedAt;
```

Constraints:

- `shop` not null;
- `nameFr` not blank, max 180;
- `unit` not blank;
- `barcode` nullable max 64;
- `brandName` nullable max 160.

- [ ] **Step 4: Modify `MerchantProduct` source relation**

Add:

```php
#[ORM\ManyToOne(targetEntity: MerchantLocalProduct::class)]
#[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
private ?MerchantLocalProduct $localProduct = null;
```

Make `productReference` nullable:

```php
#[ORM\ManyToOne(targetEntity: ProductReference::class)]
#[ORM\JoinColumn(nullable: true)]
private ?ProductReference $productReference = null;
```

Add a validation method used by processors/tests:

```php
public function hasExactlyOneProductSource(): bool
{
    return (null !== $this->productReference) xor (null !== $this->localProduct);
}
```

- [ ] **Step 5: Add API resource and processor**

Create merchant endpoint:

```http
POST /api/merchant/stores/{storeId}/local-products
```

Input DTO fields:

```php
public string $nameFr;
public ?string $nameAr = null;
public ?string $brandName = null;
public ?string $volume = null;
public string $unit;
public ?string $barcode = null;
public ?string $defaultCategoryName = null;
public string $priceTnd;
public bool $isAvailable = true;
public bool $isVisible = true;
public ?string $merchantNote = null;
```

Processor behavior:

1. Resolve `Shop` from `{storeId}`.
2. Verify current user owns the shop.
3. Create `MerchantLocalProduct`.
4. Create `MerchantProduct` linked to the local product with price/dispo/visibility/note.
5. Persist both in one transaction.
6. Return output containing `merchant_product_id`, local product fields, and catalogue display fields.

- [ ] **Step 6: Add migration**

Run:

```bash
cd apps/backend
php bin/console make:migration
```

Review migration to ensure:

- creates `merchant_local_products`;
- adds nullable `local_product_id` to `merchant_products`;
- makes `product_reference_id` nullable only if needed;
- adds an index for `shop_id`;
- does not drop existing catalogue data.

- [ ] **Step 7: Run backend tests**

Run:

```bash
cd apps/backend
php bin/phpunit tests/Functional/MerchantLocalProductApiTest.php
php bin/phpunit tests/Functional/MerchantCatalogProductApiTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit Checkpoint D backend**

```bash
git add apps/backend/src apps/backend/migrations apps/backend/tests/Functional/MerchantLocalProductApiTest.php
git commit -m "feat(backend): add merchant local products"
```

---

### Task 7: Add Checkpoint D Frontend Local Product Drawer

**Files:**
- Modify: `apps/frontend/src/lib/types/merchant-catalog.types.ts`
- Modify: `apps/frontend/src/lib/services/merchant-catalog.service.ts`
- Create: `apps/frontend/src/components/merchant/catalogue/MerchantLocalProductDrawer.tsx`
- Modify: `apps/frontend/src/components/merchant/catalogue/ProductReferenceSearchDrawer.tsx`
- Modify: `apps/frontend/src/tests/merchant.catalog.service.test.ts`
- Modify: `apps/frontend/src/tests/merchant.catalogue.test.tsx`

- [ ] **Step 1: Add service type and test**

Add payload:

```ts
export interface CreateMerchantLocalProductPayload {
  name_fr: string;
  name_ar: string | null;
  brand_name: string | null;
  volume: string | null;
  unit: string;
  barcode: string | null;
  default_category_name: string | null;
  price_tnd: string;
  is_available: boolean;
  is_visible: boolean;
  merchant_note: string | null;
}
```

Add service:

```ts
export async function createMerchantLocalProduct(
  storeId: string,
  payload: CreateMerchantLocalProductPayload,
): Promise<void> {
  await apiClient.post(`/api/merchant/stores/${storeId}/local-products`, payload);
}
```

Test expected call:

```ts
expect(apiClient.post).toHaveBeenCalledWith('/api/merchant/stores/store-1/local-products', {
  name_fr: 'Harissa maison',
  name_ar: null,
  brand_name: null,
  volume: '350',
  unit: 'gramme',
  barcode: null,
  default_category_name: 'Epicerie',
  price_tnd: '4.500',
  is_available: true,
  is_visible: true,
  merchant_note: null,
});
```

- [ ] **Step 2: Add UI test**

When reference search has no useful result, click `Créer un produit local`, fill fields, submit, and expect `createMerchantLocalProduct('store-1', payload)`.

- [ ] **Step 3: Implement `MerchantLocalProductDrawer.tsx`**

Use labels:

- `Nom français`
- `Nom arabe`
- `Marque`
- `Volume`
- `Unité`
- `Code-barres`
- `Catégorie`
- `Prix TND`
- `Créer dans mon catalogue`

Validate `name_fr`, `unit`, and positive `price_tnd` before submit.

- [ ] **Step 4: Wire from reference search drawer**

Add a `Créer un produit local` button that opens the local product drawer with the current search query prefilled into `name_fr`.

- [ ] **Step 5: Run tests**

Run:

```bash
cd apps/frontend
npm test -- merchant.catalog.service.test.ts merchant.catalogue.test.tsx
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add apps/frontend/src/lib/types/merchant-catalog.types.ts \
  apps/frontend/src/lib/services/merchant-catalog.service.ts \
  apps/frontend/src/components/merchant/catalogue/MerchantLocalProductDrawer.tsx \
  apps/frontend/src/components/merchant/catalogue/ProductReferenceSearchDrawer.tsx \
  apps/frontend/src/tests/merchant.catalog.service.test.ts \
  apps/frontend/src/tests/merchant.catalogue.test.tsx
git commit -m "feat(frontend): create merchant local products"
```

---

### Task 8: Add Checkpoint E Merchant Categories Backend

**Files:**
- Create: `apps/backend/src/Entity/MerchantCategory.php`
- Modify: `apps/backend/src/Entity/MerchantProduct.php`
- Create category DTO/resource/provider/processor files.
- Create migration.
- Create: `apps/backend/tests/Functional/MerchantCategoryApiTest.php`

- [ ] **Step 1: Write backend category tests**

Create tests for:

```php
public function testMerchantCreatesCategoryForOwnStore(): void
{
    // POST /api/merchant/stores/{storeId}/categories
    // Assert 201 and category name.
}

public function testMerchantAssignsCategoryOverrideToProduct(): void
{
    // Create MerchantCategory.
    // PATCH /api/merchant/catalog/{merchantProductId} with merchant_category_id.
    // GET catalogue.
    // Assert merchant_category_name overrides category display.
}

public function testCatalogueFallsBackToReferentialCategoryWhenNoMerchantCategory(): void
{
    // Existing referential product without merchant category.
    // GET catalogue.
    // Assert category equals ProductReference category.
}
```

- [ ] **Step 2: Run tests to verify failure**

Run:

```bash
cd apps/backend
php bin/phpunit tests/Functional/MerchantCategoryApiTest.php
```

Expected: FAIL.

- [ ] **Step 3: Create entity**

`MerchantCategory` fields:

```php
private Uuid $id;
private Shop $shop;
private string $nameFr;
private ?string $nameAr = null;
private ?MerchantCategory $parent = null;
private int $sortOrder = 0;
private bool $active = true;
private \DateTimeImmutable $createdAt;
private \DateTimeImmutable $updatedAt;
```

Add unique index on `shop_id + name_fr`.

- [ ] **Step 4: Add relation to `MerchantProduct`**

```php
#[ORM\ManyToOne(targetEntity: MerchantCategory::class)]
#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
private ?MerchantCategory $merchantCategory = null;
```

Extend update DTO with:

```php
public ?string $merchantCategoryId = null;
```

Update processor to verify category belongs to same shop before assignment.

- [ ] **Step 5: Add category endpoints**

Create:

```http
GET /api/merchant/stores/{storeId}/categories
POST /api/merchant/stores/{storeId}/categories
PATCH /api/merchant/categories/{merchantCategoryId}
DELETE /api/merchant/categories/{merchantCategoryId}
```

Delete should soft-deactivate if products are attached.

- [ ] **Step 6: Generate and review migration**

Run:

```bash
cd apps/backend
php bin/console make:migration
```

- [ ] **Step 7: Run backend tests**

Run:

```bash
cd apps/backend
php bin/phpunit tests/Functional/MerchantCategoryApiTest.php
php bin/phpunit tests/Functional/MerchantCatalogProductApiTest.php
vendor/bin/phpstan analyse
```

Expected: PASS / PHPStan clean.

- [ ] **Step 8: Commit**

```bash
git add apps/backend/src apps/backend/migrations apps/backend/tests/Functional/MerchantCategoryApiTest.php
git commit -m "feat(backend): add merchant catalogue categories"
```

---

### Task 9: Add Checkpoint E Frontend Category Selector

**Files:**
- Modify: `apps/frontend/src/lib/types/merchant-catalog.types.ts`
- Modify: `apps/frontend/src/lib/services/merchant-catalog.service.ts`
- Create: `apps/frontend/src/components/merchant/catalogue/MerchantCategorySelector.tsx`
- Modify edit/local/reference drawers to show selector.
- Modify tests.

- [ ] **Step 1: Add service tests**

Add tests for:

```ts
await listMerchantCategories('store-1');
expect(apiClient.get).toHaveBeenCalledWith('/api/merchant/stores/store-1/categories');

await createMerchantCategory('store-1', { name_fr: 'Rayon frais', name_ar: null });
expect(apiClient.post).toHaveBeenCalledWith('/api/merchant/stores/store-1/categories', {
  name_fr: 'Rayon frais',
  name_ar: null,
});
```

- [ ] **Step 2: Add types and service functions**

Add:

```ts
export interface MerchantCategory {
  id: string;
  name_fr: string;
  name_ar: string | null;
  active: boolean;
}

export interface CreateMerchantCategoryPayload {
  name_fr: string;
  name_ar: string | null;
}
```

Service:

```ts
export async function listMerchantCategories(storeId: string): Promise<MerchantCategory[]> {
  const { data } = await apiClient.get<MerchantCategory[]>(`/api/merchant/stores/${storeId}/categories`);
  return data;
}

export async function createMerchantCategory(
  storeId: string,
  payload: CreateMerchantCategoryPayload,
): Promise<MerchantCategory> {
  const { data } = await apiClient.post<MerchantCategory>(
    `/api/merchant/stores/${storeId}/categories`,
    payload,
  );
  return data;
}
```

- [ ] **Step 3: Create selector component**

`MerchantCategorySelector` props:

```ts
interface MerchantCategorySelectorProps {
  categories: MerchantCategory[];
  fallbackCategory: string;
  value: string | null;
  onChange: (value: string | null) => void;
}
```

Render a select with first option:

```tsx
<option value="">Catégorie par défaut : {fallbackCategory}</option>
```

- [ ] **Step 4: Wire selector into edit and creation forms**

Pass `merchant_category_id` in update and creation payloads when selected.

- [ ] **Step 5: Run tests and commit**

Run:

```bash
cd apps/frontend
npm test -- merchant.catalog.service.test.ts merchant.catalogue.test.tsx
```

Commit:

```bash
git add apps/frontend/src
git commit -m "feat(frontend): support merchant catalogue categories"
```

---

### Task 10: Add Checkpoint F Guided Assistant

**Files:**
- Create: `apps/frontend/src/components/merchant/catalogue/MerchantCatalogWizard.tsx`
- Modify: `apps/frontend/src/app/merchant/catalogue/page.tsx`
- Modify tests.

- [ ] **Step 1: Add failing wizard test**

Test that `Assistant guidé` opens a three-step flow:

```tsx
it('opens guided assistant for catalogue enrichment', async () => {
  render(React.createElement(MerchantCataloguePage));

  fireEvent.click(await screen.findByRole('button', { name: 'Assistant guidé' }));

  expect(screen.getByRole('dialog', { name: 'Assistant catalogue' })).toBeInTheDocument();
  expect(screen.getByText('1. Chercher')).toBeInTheDocument();
  expect(screen.getByText('2. Configurer')).toBeInTheDocument();
  expect(screen.getByText('3. Publier')).toBeInTheDocument();
});
```

- [ ] **Step 2: Implement wizard shell**

Create `MerchantCatalogWizard.tsx`:

```tsx
'use client';

import { Button } from '@/components/ui/Button';

export function MerchantCatalogWizard({
  isOpen,
  onClose,
}: {
  isOpen: boolean;
  onClose: () => void;
}) {
  if (!isOpen) return null;

  return (
    <div role="dialog" aria-modal="true" aria-label="Assistant catalogue" className="fixed inset-0 z-50 bg-black/30">
      <div className="mx-auto mt-10 w-full max-w-3xl rounded-md bg-card p-5 shadow-card">
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-xl font-black">Assistant catalogue</h2>
            <p className="text-sm text-muted">Ajoute un produit en suivant les étapes métier.</p>
          </div>
          <Button type="button" variant="ghost" size="md" onClick={onClose}>
            Fermer
          </Button>
        </div>
        <div className="mt-5 grid gap-2 md:grid-cols-3">
          <div className="rounded-md bg-soft p-3 font-bold">1. Chercher</div>
          <div className="rounded-md bg-soft p-3 font-bold">2. Configurer</div>
          <div className="rounded-md bg-soft p-3 font-bold">3. Publier</div>
        </div>
      </div>
    </div>
  );
}
```

- [ ] **Step 3: Reuse existing drawers inside wizard**

After the shell test passes, refactor wizard internals to call the same search/add/local product components used outside the wizard. Do not duplicate API calls in the wizard.

- [ ] **Step 4: Run frontend tests**

Run:

```bash
cd apps/frontend
npm test -- merchant.catalogue.test.tsx
npm run lint
npm run build
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/frontend/src/components/merchant/catalogue/MerchantCatalogWizard.tsx \
  apps/frontend/src/app/merchant/catalogue/page.tsx \
  apps/frontend/src/tests/merchant.catalogue.test.tsx
git commit -m "feat(frontend): add merchant catalogue assistant"
```

---

### Task 11: Documentation And Final Verification

**Files:**
- Modify: `docs/SprintFrontend/merchant-next-chantiers.md`
- Modify: `docs/architecture/api-contract.md` if backend endpoints changed.
- Optional: update `AI_CONTEXT.md` only if the delivered backend state changes materially.

- [ ] **Step 1: Update merchant chantier document**

Move `P1 — Gestion catalogue marchand` from planned to delivered/partially delivered with checkpoint details:

```md
### Catalogue marchand

Statut : en cours de livraison par checkpoints.

- Checkpoint A : page catalogue, liste, recherche, filtres.
- Checkpoint B : édition prix/disponibilité/visibilité/note, rupture en masse.
- Checkpoint C : ajout depuis référentiel.
- Checkpoint D : produit local marchand vendable immédiatement.
- Checkpoint E : catégories marchand.
- Checkpoint F : assistant guidé.
```

- [ ] **Step 2: Run full relevant verification**

Frontend:

```bash
cd apps/frontend
npm test -- merchant.catalog.service.test.ts merchant.catalogue.test.tsx merchant.shell.test.tsx
npm run lint
npm run build
```

Backend, if Checkpoints D/E were implemented:

```bash
cd apps/backend
php bin/phpunit tests/Functional/MerchantLocalProductApiTest.php tests/Functional/MerchantCategoryApiTest.php tests/Functional/MerchantCatalogProductApiTest.php
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run --diff
```

- [ ] **Step 3: Commit documentation**

```bash
git add docs/SprintFrontend/merchant-next-chantiers.md docs/architecture/api-contract.md AI_CONTEXT.md
git commit -m "docs(frontend): update merchant catalogue checkpoint status"
```

Only include files that actually changed.

---

## Self-Review Checklist

- Spec coverage:
  - Checkpoint A covered by Tasks 1-3.
  - Checkpoint B covered by Task 4.
  - Checkpoint C covered by Task 5.
  - Checkpoint D covered by Tasks 6-7.
  - Checkpoint E covered by Tasks 8-9.
  - Checkpoint F covered by Task 10.
  - Documentation and verification covered by Task 11.
- Placeholder scan:
  - No placeholder markers remain.
  - Backend tests describe exact behaviors and endpoint contracts, but implementation must copy existing functional-test helper names from the repository at execution time.
- Type consistency:
  - Frontend payload names use backend JSON names: `price_tnd`, `is_available`, `is_visible`, `merchant_note`.
  - Merchant category payload uses `merchant_category_id`.
  - Product local fields use snake_case JSON names to match API style.

## Execution Notes

- Before executing code changes, create or use an isolated worktree with `superpowers:using-git-worktrees` unless the user explicitly wants to work in the current tree.
- Keep PR open from Checkpoint A onward.
- Do not add payment, delivery, loyalty, marketplace cart, or complex stock management.
- Keep the vocabulary: Kadhia, supérette, marchand, client, rendez-vous, retrait.
