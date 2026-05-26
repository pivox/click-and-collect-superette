# Recherche supérettes — autocomplete dropdown Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Brancher le champ de recherche de la page `/stores` sur l'endpoint `GET /api/stores/search?query=…` via un dropdown autocomplete React Query.

**Architecture:** `StoreSearchCombobox` est un Client Component autonome inséré dans la page `/stores` (Server Component). Il gère son propre state (inputValue, debouncedQuery) et consomme `useQuery` de React Query. ReactQueryProvider est ajouté au layout `(client)`. La grille de toutes les supérettes reste inchangée sous le dropdown.

**Tech Stack:** Next.js 14 App Router, React Query 5 (`@tanstack/react-query`), Axios (`apiClient`), Vitest + Testing Library, Tailwind CSS.

---

## File Map

| Fichier | Action |
|---|---|
| `apps/frontend/src/types/index.ts` | Modify — add `StoreSearchItem` |
| `apps/frontend/src/lib/services/store-search.service.ts` | Create — `searchStores()` function |
| `apps/frontend/src/lib/services/index.ts` | Modify — re-export store-search.service |
| `apps/frontend/src/lib/providers/ReactQueryProvider.tsx` | Create — QueryClientProvider wrapper |
| `apps/frontend/src/app/(client)/layout.tsx` | Modify — wrap with ReactQueryProvider |
| `apps/frontend/src/components/store/StoreSearchCombobox.tsx` | Create — Client Component dropdown |
| `apps/frontend/src/app/(client)/stores/page.tsx` | Modify — replace bare SearchInput |
| `apps/frontend/src/tests/client.store-search.service.test.ts` | Create — service unit tests |
| `apps/frontend/src/tests/client.store-search-combobox.test.tsx` | Create — component tests |

---

## Task 1: Add `StoreSearchItem` type and `searchStores` service

### Files:
- Modify: `apps/frontend/src/types/index.ts`
- Create: `apps/frontend/src/lib/services/store-search.service.ts`
- Create: `apps/frontend/src/tests/client.store-search.service.test.ts`
- Modify: `apps/frontend/src/lib/services/index.ts`

- [ ] **Step 1: Write the failing service test**

Create `apps/frontend/src/tests/client.store-search.service.test.ts`:

```ts
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import { searchStores } from '@/lib/services/store-search.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
  },
}));

describe('searchStores', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('calls GET /api/stores/search with query param', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { items: [], total: 0 },
    });

    await searchStores('marj');

    expect(apiClient.get).toHaveBeenCalledWith('/api/stores/search', {
      params: { query: 'marj' },
    });
  });

  it('returns items and total from the API response', async () => {
    const mockItems = [
      {
        store_id: 'uuid-1',
        name: 'Marjé El Amel',
        slug: 'marje-el-amel',
        city: 'Tunis',
        country: 'TN',
        is_active: true,
      },
    ];
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { items: mockItems, total: 1 },
    });

    const result = await searchStores('marj');

    expect(result.items).toHaveLength(1);
    expect(result.items[0].store_id).toBe('uuid-1');
    expect(result.items[0].name).toBe('Marjé El Amel');
    expect(result.total).toBe(1);
  });
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
cd apps/frontend && npx vitest run src/tests/client.store-search.service.test.ts
```

Expected: FAIL — `Cannot find module '@/lib/services/store-search.service'`

- [ ] **Step 3: Add `StoreSearchItem` type to `apps/frontend/src/types/index.ts`**

Add at the end of the file (after the last `export interface`):

```ts
export interface StoreSearchItem {
  store_id: string;
  name: string;
  slug: string;
  city: string | null;
  country: string;
  is_active: boolean;
}

export interface StoreSearchResult {
  items: StoreSearchItem[];
  total: number;
}
```

> Note: field names are snake_case because the backend uses `#[SerializedName]` annotations (`store_id`, `is_active`). This matches the pattern used by admin pages in this codebase.

- [ ] **Step 4: Create `apps/frontend/src/lib/services/store-search.service.ts`**

```ts
import { apiClient } from "@/lib/api";
import type { StoreSearchResult } from "@/types";

export async function searchStores(query: string): Promise<StoreSearchResult> {
  const { data } = await apiClient.get<StoreSearchResult>("/api/stores/search", {
    params: { query },
  });
  return data;
}
```

> No mock path — this endpoint is public and always hits the real API (no `USE_MOCKS` guard needed for a search widget).

- [ ] **Step 5: Export from `apps/frontend/src/lib/services/index.ts`**

Add at the end of the file:

```ts
export * from "./store-search.service";
```

- [ ] **Step 6: Run the test to verify it passes**

```bash
cd apps/frontend && npx vitest run src/tests/client.store-search.service.test.ts
```

Expected: PASS — 2 tests pass.

- [ ] **Step 7: Commit**

```bash
cd apps/frontend && git add src/types/index.ts src/lib/services/store-search.service.ts src/lib/services/index.ts src/tests/client.store-search.service.test.ts
git commit -m "feat(client/search): StoreSearchItem type + searchStores service"
```

---

## Task 2: Set up ReactQueryProvider

React Query 5 requiert un `QueryClientProvider` (Client Component) wrappant tous les composants qui utilisent `useQuery`. Il n'existe pas encore dans cette app.

### Files:
- Create: `apps/frontend/src/lib/providers/ReactQueryProvider.tsx`
- Modify: `apps/frontend/src/app/(client)/layout.tsx`

- [ ] **Step 1: Create `apps/frontend/src/lib/providers/ReactQueryProvider.tsx`**

```tsx
"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useState } from "react";

export function ReactQueryProvider({ children }: { children: React.ReactNode }) {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 30_000,
            retry: 1,
          },
        },
      }),
  );

  return (
    <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  );
}
```

> `useState(() => new QueryClient(...))` évite de recréer le client à chaque re-render. `staleTime: 30s` convient pour une recherche live — les résultats ne sont pas revalidés à chaque keystroke identique.

- [ ] **Step 2: Add `ReactQueryProvider` to `apps/frontend/src/app/(client)/layout.tsx`**

Fichier actuel :
```tsx
import type { Metadata } from "next";
import { DesktopNav } from "@/components/layout/DesktopNav";
import { BottomNav } from "@/components/layout/BottomNav";
import { ClientAuthProvider } from "@/lib/auth/ClientAuthContext";

export const metadata: Metadata = {
  title: "Kadhia · Click & Collect",
};

export default function ClientLayout({ children }: { children: React.ReactNode }) {
  return (
    <ClientAuthProvider>
      <div className="min-h-screen md:grid md:grid-cols-[280px_1fr]">
        <DesktopNav />
        <main className="relative px-4 pt-4 pb-24 md:p-7">
          {children}
        </main>
      </div>
      <BottomNav />
    </ClientAuthProvider>
  );
}
```

Remplacer par :
```tsx
import type { Metadata } from "next";
import { DesktopNav } from "@/components/layout/DesktopNav";
import { BottomNav } from "@/components/layout/BottomNav";
import { ClientAuthProvider } from "@/lib/auth/ClientAuthContext";
import { ReactQueryProvider } from "@/lib/providers/ReactQueryProvider";

export const metadata: Metadata = {
  title: "Kadhia · Click & Collect",
};

export default function ClientLayout({ children }: { children: React.ReactNode }) {
  return (
    <ReactQueryProvider>
      <ClientAuthProvider>
        <div className="min-h-screen md:grid md:grid-cols-[280px_1fr]">
          <DesktopNav />
          <main className="relative px-4 pt-4 pb-24 md:p-7">
            {children}
          </main>
        </div>
        <BottomNav />
      </ClientAuthProvider>
    </ReactQueryProvider>
  );
}
```

- [ ] **Step 3: Verify the build still passes**

```bash
cd apps/frontend && npm run build 2>&1 | tail -10
```

Expected: build succeeds with no TypeScript errors.

- [ ] **Step 4: Commit**

```bash
cd apps/frontend && git add src/lib/providers/ReactQueryProvider.tsx src/app/\(client\)/layout.tsx
git commit -m "feat(client): add ReactQueryProvider to client layout"
```

---

## Task 3: Create `StoreSearchCombobox` component

### Files:
- Create: `apps/frontend/src/components/store/StoreSearchCombobox.tsx`
- Create: `apps/frontend/src/tests/client.store-search-combobox.test.tsx`

- [ ] **Step 1: Write the failing component tests**

Create `apps/frontend/src/tests/client.store-search-combobox.test.tsx`:

```tsx
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StoreSearchCombobox } from '@/components/store/StoreSearchCombobox';
import * as storeSearchService from '@/lib/services/store-search.service';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn() }),
}));

vi.mock('@/lib/services/store-search.service', () => ({
  searchStores: vi.fn(),
}));

function wrapper({ children }: { children: React.ReactNode }) {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={qc}>{children}</QueryClientProvider>;
}

describe('StoreSearchCombobox', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the search input', () => {
    render(<StoreSearchCombobox />, { wrapper });
    expect(screen.getByRole('searchbox')).toBeInTheDocument();
  });

  it('does not show dropdown when input is empty', () => {
    render(<StoreSearchCombobox />, { wrapper });
    expect(screen.queryByRole('list')).not.toBeInTheDocument();
  });

  it('does not show dropdown when query is 1 character', async () => {
    render(<StoreSearchCombobox />, { wrapper });
    await userEvent.type(screen.getByRole('searchbox'), 'a');
    expect(screen.queryByRole('list')).not.toBeInTheDocument();
  });

  it('shows "aucune supérette" message when API returns empty results', async () => {
    vi.mocked(storeSearchService.searchStores).mockResolvedValue({
      items: [],
      total: 0,
    });
    render(<StoreSearchCombobox />, { wrapper });
    await userEvent.type(screen.getByRole('searchbox'), 'xyz');
    await waitFor(() =>
      expect(screen.getByText(/aucune supérette trouvée/i)).toBeInTheDocument(),
    );
  });

  it('displays store name and city for each result', async () => {
    vi.mocked(storeSearchService.searchStores).mockResolvedValue({
      items: [
        {
          store_id: 'uuid-1',
          name: 'Marjé El Amel',
          slug: 'marje-el-amel',
          city: 'Tunis',
          country: 'TN',
          is_active: true,
        },
      ],
      total: 1,
    });
    render(<StoreSearchCombobox />, { wrapper });
    await userEvent.type(screen.getByRole('searchbox'), 'mar');
    await waitFor(() =>
      expect(screen.getByText('Marjé El Amel')).toBeInTheDocument(),
    );
    expect(screen.getByText('Tunis')).toBeInTheDocument();
  });
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
cd apps/frontend && npx vitest run src/tests/client.store-search-combobox.test.tsx
```

Expected: FAIL — `Cannot find module '@/components/store/StoreSearchCombobox'`

- [ ] **Step 3: Create `apps/frontend/src/components/store/StoreSearchCombobox.tsx`**

```tsx
"use client";

import { useState, useEffect } from "react";
import { useQuery } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { SearchInput } from "@/components/ui/SearchInput";
import { searchStores } from "@/lib/services/store-search.service";
import type { StoreSearchItem } from "@/types";

export function StoreSearchCombobox() {
  const [inputValue, setInputValue] = useState("");
  const [debouncedQuery, setDebouncedQuery] = useState("");
  const [isOpen, setIsOpen] = useState(false);
  const router = useRouter();

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedQuery(inputValue), 400);
    return () => clearTimeout(timer);
  }, [inputValue]);

  const { data, isLoading } = useQuery({
    queryKey: ["store-search", debouncedQuery],
    queryFn: () => searchStores(debouncedQuery),
    enabled: debouncedQuery.trim().length >= 2,
  });

  const showDropdown = isOpen && inputValue.trim().length >= 2;

  function handleSelect(item: StoreSearchItem) {
    router.push(`/stores/${item.store_id}`);
    setInputValue("");
    setIsOpen(false);
  }

  return (
    <div className="relative mb-4 md:max-w-lg">
      <SearchInput
        placeholder="Nom de la supérette, quartier…"
        value={inputValue}
        onChange={(e) => {
          setInputValue(e.target.value);
          setIsOpen(true);
        }}
        onFocus={() => setIsOpen(true)}
        onBlur={() => setTimeout(() => setIsOpen(false), 200)}
      />
      {showDropdown && (
        <div className="absolute left-0 right-0 top-full z-50 mt-1 overflow-hidden rounded-md border border-line bg-white shadow-card">
          {isLoading && (
            <div className="space-y-2 p-3">
              {[1, 2, 3].map((i) => (
                <div
                  key={i}
                  className="h-10 animate-pulse rounded bg-product-tile"
                />
              ))}
            </div>
          )}
          {!isLoading && data?.items.length === 0 && (
            <p className="px-4 py-3 text-sm text-muted">
              Aucune supérette trouvée pour «&nbsp;{debouncedQuery}&nbsp;»
            </p>
          )}
          {!isLoading && data && data.items.length > 0 && (
            <ul role="list">
              {data.items.slice(0, 8).map((item) => (
                <li key={item.store_id}>
                  <button
                    type="button"
                    className="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-product-tile"
                    onMouseDown={() => handleSelect(item)}
                  >
                    <span aria-hidden="true" className="text-lg">
                      🏪
                    </span>
                    <div className="min-w-0 flex-1">
                      <strong className="block truncate text-sm">
                        {item.name}
                      </strong>
                      {item.city && (
                        <span className="text-xs text-muted">{item.city}</span>
                      )}
                    </div>
                    {item.is_active && (
                      <span className="shrink-0 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                        Ouverte
                      </span>
                    )}
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  );
}
```

> `onMouseDown` sur chaque bouton de suggestion : évite que le `onBlur` de l'input ferme le dropdown avant que `onClick` puisse s'exécuter (mousedown se déclenche avant blur). Le `setTimeout(..., 200)` sur `onBlur` est un filet de sécurité supplémentaire.

- [ ] **Step 4: Run the tests to verify they pass**

```bash
cd apps/frontend && npx vitest run src/tests/client.store-search-combobox.test.tsx
```

Expected: PASS — 5 tests pass.

- [ ] **Step 5: Commit**

```bash
cd apps/frontend && git add src/components/store/StoreSearchCombobox.tsx src/tests/client.store-search-combobox.test.tsx
git commit -m "feat(client/search): StoreSearchCombobox autocomplete dropdown"
```

---

## Task 4: Wire `StoreSearchCombobox` into the `/stores` page

### Files:
- Modify: `apps/frontend/src/app/(client)/stores/page.tsx`

- [ ] **Step 1: Replace bare `SearchInput` with `StoreSearchCombobox`**

Fichier actuel :
```tsx
import Link from "next/link";
import { TopBar } from "@/components/layout/TopBar";
import { SearchInput } from "@/components/ui/SearchInput";
import { StoreCard } from "@/components/store/StoreCard";
import { listShops } from "@/lib/services";
import type { Shop } from "@/types";

export const dynamic = "force-dynamic";

export default async function StoresPage() {
  let shops: Shop[] = [];
  try {
    shops = await listShops();
  } catch {
    // backend unavailable — render empty state
  }
  return (
    <>
      <TopBar
        title="Trouver une supérette"
        subtitle="Scan QR ou recherche par nom"
        backHref="/"
      />
      <SearchInput placeholder="Nom de la supérette, quartier…" className="mb-4 md:max-w-lg" />
      <div className="grid gap-2.5 md:grid-cols-3">
        {shops.map((s) => (
          <StoreCard key={s.id} shop={s} href={`/stores/${s.id}`} />
        ))}
      </div>
      <p className="mt-4 text-center text-xs text-muted">
        Tu peux aussi{" "}
        <Link href="/" className="font-extrabold text-primary">
          scanner directement
        </Link>{" "}
        le QR à l&apos;entrée.
      </p>
    </>
  );
}
```

Remplacer par :
```tsx
import Link from "next/link";
import { TopBar } from "@/components/layout/TopBar";
import { StoreSearchCombobox } from "@/components/store/StoreSearchCombobox";
import { StoreCard } from "@/components/store/StoreCard";
import { listShops } from "@/lib/services";
import type { Shop } from "@/types";

export const dynamic = "force-dynamic";

export default async function StoresPage() {
  let shops: Shop[] = [];
  try {
    shops = await listShops();
  } catch {
    // backend unavailable — render empty state
  }
  return (
    <>
      <TopBar
        title="Trouver une supérette"
        subtitle="Scan QR ou recherche par nom"
        backHref="/"
      />
      <StoreSearchCombobox />
      <div className="grid gap-2.5 md:grid-cols-3">
        {shops.map((s) => (
          <StoreCard key={s.id} shop={s} href={`/stores/${s.id}`} />
        ))}
      </div>
      <p className="mt-4 text-center text-xs text-muted">
        Tu peux aussi{" "}
        <Link href="/" className="font-extrabold text-primary">
          scanner directement
        </Link>{" "}
        le QR à l&apos;entrée.
      </p>
    </>
  );
}
```

- [ ] **Step 2: Run the full test suite**

```bash
cd apps/frontend && npx vitest run 2>&1 | tail -20
```

Expected: all tests pass (aucune régression).

- [ ] **Step 3: Run the lint check**

```bash
cd apps/frontend && npm run lint
```

Expected: no errors.

- [ ] **Step 4: Commit**

```bash
cd apps/frontend && git add src/app/\(client\)/stores/page.tsx
git commit -m "feat(client/search): wire StoreSearchCombobox into /stores page"
```

---

## Vérification manuelle

Après les 4 tâches, tester dans le navigateur (`npm run dev`) :

1. Ouvrir `/stores` — la grille de supérettes s'affiche normalement.
2. Taper 1 caractère dans le champ — aucun dropdown.
3. Taper 2+ caractères — le dropdown s'ouvre avec une animation de chargement, puis les résultats.
4. Cliquer une suggestion — navigation vers `/stores/{store_id}`.
5. Vider le champ — le dropdown se ferme, la grille reste visible.
6. Taper une chaîne sans résultat — message "Aucune supérette trouvée pour «…»" affiché.
