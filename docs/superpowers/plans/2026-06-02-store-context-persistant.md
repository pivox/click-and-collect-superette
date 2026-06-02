# Store Context Persistant Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettre à l'utilisateur de sélectionner explicitement une supérette et d'afficher cette sélection en permanence dans la navigation (pill en haut du contenu + bloc sidebar desktop), avec avertissement si une Kadhia est en cours lors du changement.

**Architecture:** Un `SelectedStoreContext` React persiste la sélection en `localStorage` (clé `selected_store`). Une `StoreContextPill` client s'insère dans le `<main>` du layout client. La sélection se déclenche depuis `/stores` (StoreSelectList) et depuis `/stores/[shopId]` (StartKadhiaCta).

**Tech Stack:** Next.js 14, React 18, TypeScript, Vitest + React Testing Library, Tailwind CSS, happy-dom, localStorage

---

## File Map

| Statut | Fichier | Responsabilité |
|---|---|---|
| Créer | `apps/frontend/src/lib/store/SelectedStoreContext.tsx` | Context + Provider + `useSelectedStore()` hook |
| Créer | `apps/frontend/src/lib/store/hasActiveKadhia.ts` | Utilitaire — détecte Kadhia active en localStorage |
| Créer | `apps/frontend/src/components/store/StoreSwitchWarning.tsx` | Dialog d'avertissement changement de store |
| Créer | `apps/frontend/src/components/store/StoreContextPill.tsx` | Pill sticky en haut de `<main>` |
| Créer | `apps/frontend/src/components/store/StoreSelectList.tsx` | Wrapper client pour la liste /stores |
| Créer | `apps/frontend/src/components/store/StartKadhiaCta.tsx` | Bouton "Commencer ma Kadhia" client |
| Modifier | `apps/frontend/src/components/store/StoreCard.tsx` | Ajouter prop `selected?: boolean` |
| Modifier | `apps/frontend/src/app/(client)/layout.tsx` | Ajouter Provider + StoreContextPill |
| Modifier | `apps/frontend/src/components/layout/DesktopNav.tsx` | Ajouter bloc store actif |
| Modifier | `apps/frontend/src/app/(client)/stores/page.tsx` | Utiliser StoreSelectList |
| Modifier | `apps/frontend/src/app/(client)/stores/[shopId]/page.tsx` | Utiliser StartKadhiaCta |
| Modifier | `apps/frontend/src/app/(client)/stores/by-qr/[qrToken]/page.tsx` | Auto-select au QR scan |
| Modifier | `apps/frontend/src/app/(client)/page.tsx` | Supprimer bloc "en vedette" |
| Créer (tests) | `apps/frontend/src/tests/client.selected-store-context.test.tsx` | |
| Créer (tests) | `apps/frontend/src/tests/client.has-active-kadhia.test.ts` | |
| Créer (tests) | `apps/frontend/src/tests/client.store-switch-warning.test.tsx` | |
| Créer (tests) | `apps/frontend/src/tests/client.store-context-pill.test.tsx` | |
| Créer (tests) | `apps/frontend/src/tests/client.store-select-list.test.tsx` | |
| Créer (tests) | `apps/frontend/src/tests/client.start-kadhia-cta.test.tsx` | |

---

### Task 1: SelectedStoreContext + useSelectedStore

**Files:**
- Create: `apps/frontend/src/lib/store/SelectedStoreContext.tsx`
- Create (test): `apps/frontend/src/tests/client.selected-store-context.test.tsx`

- [ ] **Step 1.1 — Écrire le test en premier**

```tsx
// apps/frontend/src/tests/client.selected-store-context.test.tsx
import { render, screen, act, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it } from 'vitest';
import { SelectedStoreProvider, useSelectedStore } from '@/lib/store/SelectedStoreContext';

function Consumer() {
  const { selectedStore, selectStore, clearStore } = useSelectedStore();
  return (
    <div>
      <span data-testid="name">{selectedStore?.name ?? 'none'}</span>
      <button onClick={() => selectStore({ id: 's1', name: 'Aziza Montplaisir' })}>select</button>
      <button onClick={() => clearStore()}>clear</button>
    </div>
  );
}

describe('SelectedStoreContext', () => {
  beforeEach(() => { localStorage.clear(); });

  it('selectedStore est null sans localStorage', async () => {
    render(<SelectedStoreProvider><Consumer /></SelectedStoreProvider>);
    await waitFor(() => expect(screen.getByTestId('name').textContent).toBe('none'));
  });

  it('restaure le store depuis localStorage au montage', async () => {
    localStorage.setItem('selected_store', JSON.stringify({ id: 's1', name: 'Aziza Montplaisir' }));
    render(<SelectedStoreProvider><Consumer /></SelectedStoreProvider>);
    await waitFor(() => expect(screen.getByTestId('name').textContent).toBe('Aziza Montplaisir'));
  });

  it('selectStore met à jour le state et localStorage', async () => {
    render(<SelectedStoreProvider><Consumer /></SelectedStoreProvider>);
    await waitFor(() => screen.getByRole('button', { name: 'select' }));
    act(() => screen.getByRole('button', { name: 'select' }).click());
    await waitFor(() => expect(screen.getByTestId('name').textContent).toBe('Aziza Montplaisir'));
    expect(localStorage.getItem('selected_store')).toContain('Aziza Montplaisir');
  });

  it('clearStore remet selectedStore à null et vide localStorage', async () => {
    localStorage.setItem('selected_store', JSON.stringify({ id: 's1', name: 'Aziza Montplaisir' }));
    render(<SelectedStoreProvider><Consumer /></SelectedStoreProvider>);
    await waitFor(() => expect(screen.getByTestId('name').textContent).toBe('Aziza Montplaisir'));
    act(() => screen.getByRole('button', { name: 'clear' }).click());
    await waitFor(() => expect(screen.getByTestId('name').textContent).toBe('none'));
    expect(localStorage.getItem('selected_store')).toBeNull();
  });

  it('useSelectedStore throw hors du provider', () => {
    const err = console.error;
    console.error = () => {};
    expect(() => render(<Consumer />)).toThrow();
    console.error = err;
  });
});
```

- [ ] **Step 1.2 — Vérifier que le test échoue**

```bash
cd apps/frontend && npm run test:run -- src/tests/client.selected-store-context.test.tsx
```
Résultat attendu : FAIL — module introuvable

- [ ] **Step 1.3 — Créer le Context**

```tsx
// apps/frontend/src/lib/store/SelectedStoreContext.tsx
'use client';

import React, { createContext, useContext, useEffect, useState } from 'react';

const STORAGE_KEY = 'selected_store';

export interface SelectedStore {
  id: string;
  name: string;
  logoLetter?: string | null;
}

interface SelectedStoreContextValue {
  selectedStore: SelectedStore | null;
  selectStore: (shop: SelectedStore) => void;
  clearStore: () => void;
}

const SelectedStoreContext = createContext<SelectedStoreContextValue | null>(null);

export function SelectedStoreProvider({ children }: { children: React.ReactNode }) {
  const [selectedStore, setSelectedStore] = useState<SelectedStore | null>(null);

  useEffect(() => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (raw) setSelectedStore(JSON.parse(raw) as SelectedStore);
    } catch { /* ignore */ }
  }, []);

  const selectStore = (shop: SelectedStore) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(shop));
    setSelectedStore(shop);
  };

  const clearStore = () => {
    localStorage.removeItem(STORAGE_KEY);
    setSelectedStore(null);
  };

  return (
    <SelectedStoreContext.Provider value={{ selectedStore, selectStore, clearStore }}>
      {children}
    </SelectedStoreContext.Provider>
  );
}

export function useSelectedStore(): SelectedStoreContextValue {
  const ctx = useContext(SelectedStoreContext);
  if (!ctx) throw new Error('useSelectedStore must be used within SelectedStoreProvider');
  return ctx;
}
```

- [ ] **Step 1.4 — Vérifier que les tests passent**

```bash
cd apps/frontend && npm run test:run -- src/tests/client.selected-store-context.test.tsx
```
Résultat attendu : 5 passed

- [ ] **Step 1.5 — Commit**

```bash
git add apps/frontend/src/lib/store/SelectedStoreContext.tsx \
        apps/frontend/src/tests/client.selected-store-context.test.tsx
git commit -m "feat(store-context): add SelectedStoreContext + useSelectedStore hook"
```

---

### Task 2: hasActiveKadhia utility

**Files:**
- Create: `apps/frontend/src/lib/store/hasActiveKadhia.ts`
- Create (test): `apps/frontend/src/tests/client.has-active-kadhia.test.ts`

- [ ] **Step 2.1 — Écrire le test**

```ts
// apps/frontend/src/tests/client.has-active-kadhia.test.ts
import { beforeEach, describe, expect, it } from 'vitest';
import { hasActiveKadhia } from '@/lib/store/hasActiveKadhia';

describe('hasActiveKadhia', () => {
  beforeEach(() => { localStorage.clear(); });

  it('retourne false quand aucune clé en localStorage', () => {
    expect(hasActiveKadhia('store-1')).toBe(false);
  });

  it('retourne true si kadhia:active:{storeId} existe (mode réel)', () => {
    localStorage.setItem('kadhia:active:store-1', 'kadhia-uuid-123');
    expect(hasActiveKadhia('store-1')).toBe(true);
  });

  it('retourne false si kadhia:active: existe pour un autre store', () => {
    localStorage.setItem('kadhia:active:store-2', 'kadhia-uuid-456');
    expect(hasActiveKadhia('store-1')).toBe(false);
  });

  it('retourne true si kadhia:current mock contient des lignes pour ce store', () => {
    localStorage.setItem('kadhia:current', JSON.stringify({
      shopId: 'store-1',
      lines: [{ id: 'l1' }],
    }));
    expect(hasActiveKadhia('store-1')).toBe(true);
  });

  it('retourne false si kadhia:current mock a 0 lignes', () => {
    localStorage.setItem('kadhia:current', JSON.stringify({ shopId: 'store-1', lines: [] }));
    expect(hasActiveKadhia('store-1')).toBe(false);
  });

  it('retourne false si kadhia:current mock appartient à un autre store', () => {
    localStorage.setItem('kadhia:current', JSON.stringify({ shopId: 'store-2', lines: [{ id: 'l1' }] }));
    expect(hasActiveKadhia('store-1')).toBe(false);
  });
});
```

- [ ] **Step 2.2 — Vérifier que le test échoue**

```bash
cd apps/frontend && npm run test:run -- src/tests/client.has-active-kadhia.test.ts
```
Résultat attendu : FAIL — module introuvable

- [ ] **Step 2.3 — Créer l'utilitaire**

```ts
// apps/frontend/src/lib/store/hasActiveKadhia.ts
export function hasActiveKadhia(storeId: string): boolean {
  if (typeof window === 'undefined') return false;
  if (localStorage.getItem(`kadhia:active:${storeId}`)) return true;
  try {
    const mock = JSON.parse(localStorage.getItem('kadhia:current') ?? 'null') as {
      shopId?: string;
      lines?: unknown[];
    } | null;
    return mock?.shopId === storeId && (mock?.lines?.length ?? 0) > 0;
  } catch {
    return false;
  }
}
```

- [ ] **Step 2.4 — Vérifier que les tests passent**

```bash
cd apps/frontend && npm run test:run -- src/tests/client.has-active-kadhia.test.ts
```
Résultat attendu : 6 passed

- [ ] **Step 2.5 — Commit**

```bash
git add apps/frontend/src/lib/store/hasActiveKadhia.ts \
        apps/frontend/src/tests/client.has-active-kadhia.test.ts
git commit -m "feat(store-context): add hasActiveKadhia localStorage utility"
```

---

### Task 3: StoreSwitchWarning

**Files:**
- Create: `apps/frontend/src/components/store/StoreSwitchWarning.tsx`
- Create (test): `apps/frontend/src/tests/client.store-switch-warning.test.tsx`

- [ ] **Step 3.1 — Écrire le test**

```tsx
// apps/frontend/src/tests/client.store-switch-warning.test.tsx
import { render, screen } from '@testing-library/react';
import React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { StoreSwitchWarning } from '@/components/store/StoreSwitchWarning';

describe('StoreSwitchWarning', () => {
  it('affiche le nom du store actuel dans le message', () => {
    render(
      <StoreSwitchWarning currentStoreName="Aziza Montplaisir" onConfirm={vi.fn()} onCancel={vi.fn()} />,
    );
    expect(screen.getByText('Aziza Montplaisir')).toBeTruthy();
  });

  it('appelle onConfirm au clic sur "Changer quand même"', () => {
    const onConfirm = vi.fn();
    render(<StoreSwitchWarning currentStoreName="Aziza" onConfirm={onConfirm} onCancel={vi.fn()} />);
    screen.getByRole('button', { name: 'Changer quand même' }).click();
    expect(onConfirm).toHaveBeenCalledOnce();
  });

  it('appelle onCancel au clic sur "Annuler"', () => {
    const onCancel = vi.fn();
    render(<StoreSwitchWarning currentStoreName="Aziza" onConfirm={vi.fn()} onCancel={onCancel} />);
    screen.getByRole('button', { name: 'Annuler' }).click();
    expect(onCancel).toHaveBeenCalledOnce();
  });
});
```

- [ ] **Step 3.2 — Vérifier que le test échoue**

```bash
cd apps/frontend && npm run test:run -- src/tests/client.store-switch-warning.test.tsx
```
Résultat attendu : FAIL — module introuvable

- [ ] **Step 3.3 — Créer le composant**

```tsx
// apps/frontend/src/components/store/StoreSwitchWarning.tsx
interface StoreSwitchWarningProps {
  currentStoreName: string;
  onConfirm: () => void;
  onCancel: () => void;
}

export function StoreSwitchWarning({ currentStoreName, onConfirm, onCancel }: StoreSwitchWarningProps) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
        <h2 className="mb-3 text-base font-extrabold">Changer de supérette ?</h2>
        <p className="mb-5 text-sm text-muted">
          Tu as une Kadhia en cours chez{' '}
          <strong>{currentStoreName}</strong>. Changer de supérette ne la supprime pas,
          mais elle sera mise en pause jusqu&apos;à ton retour.
        </p>
        <div className="flex gap-3">
          <button
            type="button"
            onClick={onCancel}
            className="flex-1 rounded-lg border border-line py-2.5 text-sm font-extrabold text-muted hover:bg-soft"
          >
            Annuler
          </button>
          <button
            type="button"
            onClick={onConfirm}
            className="flex-1 rounded-lg bg-primary py-2.5 text-sm font-extrabold text-white hover:bg-primary-dark"
          >
            Changer quand même
          </button>
        </div>
      </div>
    </div>
  );
}
```

- [ ] **Step 3.4 — Vérifier que les tests passent**

```bash
cd apps/frontend && npm run test:run -- src/tests/client.store-switch-warning.test.tsx
```
Résultat attendu : 3 passed

- [ ] **Step 3.5 — Commit**

```bash
git add apps/frontend/src/components/store/StoreSwitchWarning.tsx \
        apps/frontend/src/tests/client.store-switch-warning.test.tsx
git commit -m "feat(store-context): add StoreSwitchWarning dialog"
```

---

### Task 4: StoreContextPill

**Files:**
- Create: `apps/frontend/src/components/store/StoreContextPill.tsx`
- Create (test): `apps/frontend/src/tests/client.store-context-pill.test.tsx`

- [ ] **Step 4.1 — Écrire le test**

```tsx
// apps/frontend/src/tests/client.store-context-pill.test.tsx
import { render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn() }),
}));
vi.mock('@/lib/store/SelectedStoreContext', () => ({
  useSelectedStore: vi.fn(),
}));
vi.mock('@/lib/hooks/useHydrated', () => ({
  useHydrated: vi.fn(),
}));

import { StoreContextPill } from '@/components/store/StoreContextPill';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';
import { useHydrated } from '@/lib/hooks/useHydrated';

describe('StoreContextPill', () => {
  it('ne rend rien avant hydratation (SSR guard)', () => {
    vi.mocked(useHydrated).mockReturnValue(false);
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: null, selectStore: vi.fn(), clearStore: vi.fn(),
    });
    const { container } = render(<StoreContextPill />);
    expect(container.firstChild).toBeNull();
  });

  it('affiche la pill ambre "Choisir une supérette" quand aucun store', async () => {
    vi.mocked(useHydrated).mockReturnValue(true);
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: null, selectStore: vi.fn(), clearStore: vi.fn(),
    });
    render(<StoreContextPill />);
    await waitFor(() => expect(screen.getByText('Choisir une supérette')).toBeTruthy());
  });

  it('affiche le nom du store quand sélectionné', async () => {
    vi.mocked(useHydrated).mockReturnValue(true);
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: { id: 's1', name: 'Aziza Montplaisir' },
      selectStore: vi.fn(),
      clearStore: vi.fn(),
    });
    render(<StoreContextPill />);
    await waitFor(() => expect(screen.getByText('Aziza Montplaisir')).toBeTruthy());
  });

  it('les deux pills sont des liens vers /stores', async () => {
    vi.mocked(useHydrated).mockReturnValue(true);
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: null, selectStore: vi.fn(), clearStore: vi.fn(),
    });
    render(<StoreContextPill />);
    await waitFor(() => {
      expect(screen.getByRole('link').getAttribute('href')).toBe('/stores');
    });
  });
});
```

- [ ] **Step 4.2 — Vérifier que le test échoue**

```bash
cd apps/frontend && npm run test:run -- src/tests/client.store-context-pill.test.tsx
```
Résultat attendu : FAIL — module introuvable

- [ ] **Step 4.3 — Créer le composant**

```tsx
// apps/frontend/src/components/store/StoreContextPill.tsx
'use client';

import Link from 'next/link';
import { useHydrated } from '@/lib/hooks/useHydrated';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';

export function StoreContextPill() {
  const isHydrated = useHydrated();
  const { selectedStore } = useSelectedStore();

  if (!isHydrated) return null;

  if (selectedStore) {
    return (
      <Link
        href="/stores"
        className="mb-4 flex w-fit items-center gap-2 rounded-full border border-primary/30 bg-primary/5 px-4 py-2 text-sm font-extrabold text-primary-dark hover:bg-primary/10 transition-colors"
      >
        <span aria-hidden>📍</span>
        <span className="max-w-[180px] truncate">{selectedStore.name}</span>
        <span className="text-muted" aria-label="changer">↕</span>
      </Link>
    );
  }

  return (
    <Link
      href="/stores"
      className="mb-4 flex w-fit items-center gap-2 rounded-full border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-extrabold text-amber-800 hover:bg-amber-100 transition-colors"
    >
      <span aria-hidden>🏪</span>
      <span>Choisir une supérette</span>
      <span aria-hidden>→</span>
    </Link>
  );
}
```

- [ ] **Step 4.4 — Vérifier que les tests passent**

```bash
cd apps/frontend && npm run test:run -- src/tests/client.store-context-pill.test.tsx
```
Résultat attendu : 4 passed

- [ ] **Step 4.5 — Commit**

```bash
git add apps/frontend/src/components/store/StoreContextPill.tsx \
        apps/frontend/src/tests/client.store-context-pill.test.tsx
git commit -m "feat(store-context): add StoreContextPill component"
```

---

### Task 5: StoreCard — prop `selected` + StoreSelectList

**Files:**
- Modify: `apps/frontend/src/components/store/StoreCard.tsx`
- Create: `apps/frontend/src/components/store/StoreSelectList.tsx`
- Create (test): `apps/frontend/src/tests/client.store-select-list.test.tsx`

- [ ] **Step 5.1 — Ajouter la prop `selected` à StoreCard**

Modifier `apps/frontend/src/components/store/StoreCard.tsx` :

```tsx
import Link from 'next/link';
import type { Shop } from '@/types';
import { Card } from '@/components/ui/Card';

export interface StoreCardProps {
  shop: Shop;
  href?: string;
  selected?: boolean;
}

export function StoreCard({ shop, href, selected }: StoreCardProps) {
  const inner = (
    <>
      <div className="grid h-[54px] w-[54px] flex-shrink-0 place-items-center rounded-md bg-product-tile text-2xl font-black text-primary-dark">
        {shop.logoLetter ?? shop.name.charAt(0)}
      </div>
      <div className="flex-1 min-w-0">
        <strong className="block text-sm truncate">{shop.name}</strong>
        <span className="mt-0.5 block text-xs text-muted truncate">
          {shop.isActive ? 'Ouverte' : 'Fermée'}
          {shop.distanceKm != null && ` · ${shop.distanceKm.toFixed(1).replace('.', ',')} km`}
          {shop.nextPickupAt && ` · Retrait dès ${shop.nextPickupAt}`}
        </span>
      </div>
      {shop.rating != null && (
        <span className="font-black text-primary-dark whitespace-nowrap">
          {shop.rating.toFixed(1)}
        </span>
      )}
      {selected && (
        <span className="ml-1 rounded-full bg-primary px-2 py-0.5 text-[10px] font-extrabold text-white">
          ✓
        </span>
      )}
    </>
  );
  if (href) {
    return (
      <Link href={href}>
        <Card compact className="flex items-center gap-3 hover:bg-soft transition-colors">
          {inner}
        </Card>
      </Link>
    );
  }
  return (
    <Card compact className="flex items-center gap-3 hover:bg-soft transition-colors cursor-pointer">
      {inner}
    </Card>
  );
}
```

- [ ] **Step 5.2 — Écrire le test StoreSelectList**

```tsx
// apps/frontend/src/tests/client.store-select-list.test.tsx
import { render, screen, act, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const push = vi.fn();

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push }),
}));
vi.mock('@/lib/store/SelectedStoreContext', () => ({
  useSelectedStore: vi.fn(),
}));
vi.mock('@/lib/store/hasActiveKadhia', () => ({
  hasActiveKadhia: vi.fn(),
}));

import { StoreSelectList } from '@/components/store/StoreSelectList';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';
import { hasActiveKadhia } from '@/lib/store/hasActiveKadhia';
import type { Shop } from '@/types';

const SHOP_A: Shop = { id: 'a', name: 'Aziza', slug: 'aziza', isActive: true, address: null, city: null, phone: null };
const SHOP_B: Shop = { id: 'b', name: 'Monoprix', slug: 'monoprix', isActive: true, address: null, city: null, phone: null };

describe('StoreSelectList', () => {
  const selectStore = vi.fn();

  beforeEach(() => {
    push.mockClear();
    selectStore.mockClear();
    vi.mocked(hasActiveKadhia).mockReturnValue(false);
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: null, selectStore, clearStore: vi.fn(),
    });
  });

  it('affiche le message vide si aucune supérette', () => {
    render(<StoreSelectList shops={[]} />);
    expect(screen.getByText('Aucune supérette disponible pour le moment.')).toBeTruthy();
  });

  it('affiche les cartes des supérettes', () => {
    render(<StoreSelectList shops={[SHOP_A, SHOP_B]} />);
    expect(screen.getByText('Aziza')).toBeTruthy();
    expect(screen.getByText('Monoprix')).toBeTruthy();
  });

  it('sélectionne le store et navigue au clic sans Kadhia active', async () => {
    render(<StoreSelectList shops={[SHOP_A]} />);
    act(() => screen.getByText('Aziza').closest('[role="button"]')!.click());
    await waitFor(() => expect(selectStore).toHaveBeenCalledWith({ id: 'a', name: 'Aziza', logoLetter: undefined }));
    expect(push).toHaveBeenCalledWith('/stores/a');
  });

  it('affiche le warning si Kadhia active dans le store courant', async () => {
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: { id: 'a', name: 'Aziza' }, selectStore, clearStore: vi.fn(),
    });
    vi.mocked(hasActiveKadhia).mockReturnValue(true);
    render(<StoreSelectList shops={[SHOP_A, SHOP_B]} />);
    act(() => screen.getByText('Monoprix').closest('[role="button"]')!.click());
    await waitFor(() => expect(screen.getByText('Changer de supérette ?')).toBeTruthy());
    expect(selectStore).not.toHaveBeenCalled();
  });

  it('confirmer le warning appelle selectStore et navigue', async () => {
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: { id: 'a', name: 'Aziza' }, selectStore, clearStore: vi.fn(),
    });
    vi.mocked(hasActiveKadhia).mockReturnValue(true);
    render(<StoreSelectList shops={[SHOP_A, SHOP_B]} />);
    act(() => screen.getByText('Monoprix').closest('[role="button"]')!.click());
    await waitFor(() => screen.getByRole('button', { name: 'Changer quand même' }));
    act(() => screen.getByRole('button', { name: 'Changer quand même' }).click());
    await waitFor(() => expect(selectStore).toHaveBeenCalledWith({ id: 'b', name: 'Monoprix', logoLetter: undefined }));
    expect(push).toHaveBeenCalledWith('/stores/b');
  });
});
```

- [ ] **Step 5.3 — Vérifier que le test échoue**

```bash
cd apps/frontend && npm run test:run -- src/tests/client.store-select-list.test.tsx
```
Résultat attendu : FAIL — module introuvable

- [ ] **Step 5.4 — Créer StoreSelectList**

```tsx
// apps/frontend/src/components/store/StoreSelectList.tsx
'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import type { Shop } from '@/types';
import { StoreCard } from '@/components/store/StoreCard';
import { StoreSwitchWarning } from '@/components/store/StoreSwitchWarning';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';
import { hasActiveKadhia } from '@/lib/store/hasActiveKadhia';

export function StoreSelectList({ shops }: { shops: Shop[] }) {
  const router = useRouter();
  const { selectedStore, selectStore } = useSelectedStore();
  const [pendingShop, setPendingShop] = useState<Shop | null>(null);

  function doSelect(shop: Shop) {
    selectStore({ id: shop.id, name: shop.name, logoLetter: shop.logoLetter });
    router.push(`/stores/${shop.id}`);
  }

  function handleSelect(shop: Shop) {
    if (selectedStore && selectedStore.id !== shop.id && hasActiveKadhia(selectedStore.id)) {
      setPendingShop(shop);
      return;
    }
    doSelect(shop);
  }

  return (
    <>
      {pendingShop && selectedStore && (
        <StoreSwitchWarning
          currentStoreName={selectedStore.name}
          onConfirm={() => { const s = pendingShop; setPendingShop(null); doSelect(s); }}
          onCancel={() => setPendingShop(null)}
        />
      )}
      <div className="grid gap-2.5 md:grid-cols-3">
        {shops.length === 0 ? (
          <p className="col-span-3 py-6 text-center text-sm text-muted">
            Aucune supérette disponible pour le moment.
          </p>
        ) : (
          shops.map((s) => (
            <div
              key={s.id}
              role="button"
              tabIndex={0}
              onClick={() => handleSelect(s)}
              onKeyDown={(e) => e.key === 'Enter' && handleSelect(s)}
            >
              <StoreCard shop={s} selected={selectedStore?.id === s.id} />
            </div>
          ))
        )}
      </div>
    </>
  );
}
```

- [ ] **Step 5.5 — Vérifier que les tests passent**

```bash
cd apps/frontend && npm run test:run -- src/tests/client.store-select-list.test.tsx
```
Résultat attendu : 5 passed

- [ ] **Step 5.6 — Commit**

```bash
git add apps/frontend/src/components/store/StoreCard.tsx \
        apps/frontend/src/components/store/StoreSelectList.tsx \
        apps/frontend/src/tests/client.store-select-list.test.tsx
git commit -m "feat(store-context): add StoreSelectList + extend StoreCard with selected prop"
```

---

### Task 6: StartKadhiaCta

**Files:**
- Create: `apps/frontend/src/components/store/StartKadhiaCta.tsx`
- Create (test): `apps/frontend/src/tests/client.start-kadhia-cta.test.tsx`

- [ ] **Step 6.1 — Écrire le test**

```tsx
// apps/frontend/src/tests/client.start-kadhia-cta.test.tsx
import { render, screen, act, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const push = vi.fn();
vi.mock('next/navigation', () => ({ useRouter: () => ({ push }) }));
vi.mock('@/lib/store/SelectedStoreContext', () => ({ useSelectedStore: vi.fn() }));
vi.mock('@/lib/store/hasActiveKadhia', () => ({ hasActiveKadhia: vi.fn() }));

import { StartKadhiaCta } from '@/components/store/StartKadhiaCta';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';
import { hasActiveKadhia } from '@/lib/store/hasActiveKadhia';

const SHOP = { id: 'shop-1', name: 'Aziza', logoLetter: 'A' };

describe('StartKadhiaCta', () => {
  const selectStore = vi.fn();

  beforeEach(() => {
    push.mockClear();
    selectStore.mockClear();
    vi.mocked(hasActiveKadhia).mockReturnValue(false);
  });

  it('auto-sélectionne le store au montage si aucun store actif', async () => {
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: null, selectStore, clearStore: vi.fn(),
    });
    render(<StartKadhiaCta shop={SHOP} />);
    await waitFor(() => expect(selectStore).toHaveBeenCalledWith(SHOP));
  });

  it("n'auto-sélectionne pas si le même store est déjà actif", async () => {
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: SHOP, selectStore, clearStore: vi.fn(),
    });
    render(<StartKadhiaCta shop={SHOP} />);
    await waitFor(() => expect(selectStore).not.toHaveBeenCalled());
  });

  it('navigue vers le catalogue au clic sans conflit', async () => {
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: SHOP, selectStore, clearStore: vi.fn(),
    });
    render(<StartKadhiaCta shop={SHOP} />);
    act(() => screen.getByRole('button').click());
    await waitFor(() => expect(push).toHaveBeenCalledWith('/stores/shop-1/catalog'));
  });

  it('affiche le warning si Kadhia active dans un autre store', async () => {
    vi.mocked(useSelectedStore).mockReturnValue({
      selectedStore: { id: 'other', name: 'Monoprix' }, selectStore, clearStore: vi.fn(),
    });
    vi.mocked(hasActiveKadhia).mockReturnValue(true);
    render(<StartKadhiaCta shop={SHOP} />);
    act(() => screen.getByRole('button').click());
    await waitFor(() => expect(screen.getByText('Changer de supérette ?')).toBeTruthy());
  });
});
```

- [ ] **Step 6.2 — Vérifier que le test échoue**

```bash
cd apps/frontend && npm run test:run -- src/tests/client.start-kadhia-cta.test.tsx
```
Résultat attendu : FAIL — module introuvable

- [ ] **Step 6.3 — Créer le composant**

```tsx
// apps/frontend/src/components/store/StartKadhiaCta.tsx
'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { getButtonClassName } from '@/components/ui/Button';
import { StoreSwitchWarning } from '@/components/store/StoreSwitchWarning';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';
import { hasActiveKadhia } from '@/lib/store/hasActiveKadhia';

interface StartKadhiaCtaProps {
  shop: { id: string; name: string; logoLetter?: string | null };
}

export function StartKadhiaCta({ shop }: StartKadhiaCtaProps) {
  const router = useRouter();
  const { selectedStore, selectStore } = useSelectedStore();
  const [showWarning, setShowWarning] = useState(false);

  useEffect(() => {
    if (!selectedStore) {
      selectStore(shop);
    }
  }, []);  // eslint-disable-line react-hooks/exhaustive-deps

  function handleClick() {
    if (selectedStore && selectedStore.id !== shop.id && hasActiveKadhia(selectedStore.id)) {
      setShowWarning(true);
      return;
    }
    selectStore(shop);
    router.push(`/stores/${shop.id}/catalog`);
  }

  function confirmSwitch() {
    setShowWarning(false);
    selectStore(shop);
    router.push(`/stores/${shop.id}/catalog`);
  }

  return (
    <>
      {showWarning && selectedStore && (
        <StoreSwitchWarning
          currentStoreName={selectedStore.name}
          onConfirm={confirmSwitch}
          onCancel={() => setShowWarning(false)}
        />
      )}
      <button type="button" onClick={handleClick} className={getButtonClassName({ full: true })}>
        Commencer ma Kadhia
      </button>
    </>
  );
}
```

- [ ] **Step 6.4 — Vérifier que les tests passent**

```bash
cd apps/frontend && npm run test:run -- src/tests/client.start-kadhia-cta.test.tsx
```
Résultat attendu : 4 passed

- [ ] **Step 6.5 — Commit**

```bash
git add apps/frontend/src/components/store/StartKadhiaCta.tsx \
        apps/frontend/src/tests/client.start-kadhia-cta.test.tsx
git commit -m "feat(store-context): add StartKadhiaCta with auto-select and switch warning"
```

---

### Task 7: Wiring — layout + DesktopNav

**Files:**
- Modify: `apps/frontend/src/app/(client)/layout.tsx`
- Modify: `apps/frontend/src/components/layout/DesktopNav.tsx`

- [ ] **Step 7.1 — Mettre à jour layout.tsx**

Remplacer `apps/frontend/src/app/(client)/layout.tsx` entièrement :

```tsx
import type { Metadata } from 'next';
import { DesktopNav } from '@/components/layout/DesktopNav';
import { BottomNav } from '@/components/layout/BottomNav';
import { GlobalSearchBar } from '@/components/layout/GlobalSearchBar';
import { ClientAuthProvider } from '@/lib/auth/ClientAuthContext';
import { ReactQueryProvider } from '@/lib/providers/ReactQueryProvider';
import { SelectedStoreProvider } from '@/lib/store/SelectedStoreContext';
import { StoreContextPill } from '@/components/store/StoreContextPill';

export const metadata: Metadata = {
  title: 'Kadhia · Click & Collect',
};

export default function ClientLayout({ children }: { children: React.ReactNode }) {
  return (
    <ReactQueryProvider>
      <ClientAuthProvider>
        <SelectedStoreProvider>
          <div className="min-h-screen md:grid md:grid-cols-[280px_1fr]">
            <DesktopNav />
            <div data-testid="client-content-column" className="flex min-w-0 flex-col">
              <header className="hidden md:flex items-center gap-4 border-b border-line bg-white/80 backdrop-blur-md px-7 py-3 sticky top-0 z-10">
                <GlobalSearchBar />
                <span className="shrink-0 rounded-full bg-soft px-3 py-1.5 text-xs font-extrabold text-primary-dark">
                  🇹🇳 TND
                </span>
              </header>
              <main className="relative min-w-0 px-4 pt-4 pb-40 md:p-7">
                <StoreContextPill />
                {children}
              </main>
            </div>
          </div>
          <BottomNav />
        </SelectedStoreProvider>
      </ClientAuthProvider>
    </ReactQueryProvider>
  );
}
```

- [ ] **Step 7.2 — Mettre à jour DesktopNav.tsx**

Ajouter le bloc store actif. Remplacer entièrement `apps/frontend/src/components/layout/DesktopNav.tsx` :

```tsx
'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { Home, Search, ShoppingBasket, ClipboardList, LogIn, LogOut } from 'lucide-react';
import { cn } from '@/lib/cn';
import { useClientAuth } from '@/lib/auth/ClientAuthContext';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';
import { useHydrated } from '@/lib/hooks/useHydrated';

const NAV = [
  { href: '/',        label: 'Accueil',    icon: Home },
  { href: '/stores',  label: 'Supérettes', icon: Search },
  { href: '/kadhia',  label: 'Kadhia',     icon: ShoppingBasket },
  { href: '/orders',  label: 'Commandes',  icon: ClipboardList },
] as const;

export function DesktopNav() {
  const pathname = usePathname() ?? '/';
  const { user, logout } = useClientAuth();
  const { selectedStore } = useSelectedStore();
  const isHydrated = useHydrated();
  const router = useRouter();

  function handleLogout() {
    logout();
    router.push('/');
  }

  const avatarLetters = user
    ? (user.name || user.email)
        .split(/[\s@._-]+/)
        .filter(Boolean)
        .map((w) => w[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)
    : '';

  return (
    <aside className="sticky top-0 hidden h-screen overflow-y-auto border-r border-line bg-white p-6 md:flex md:flex-col md:justify-between">
      <div>
        <div className="mb-7 flex items-center gap-3">
          <div className="grid h-12 w-12 place-items-center rounded-md bg-primary text-white text-lg font-black">
            K
          </div>
          <div>
            <strong className="block text-base">Kadhia</strong>
            <span className="text-xs text-muted">Click &amp; Collect Supérette</span>
          </div>
        </div>
        <nav className="grid gap-2">
          {NAV.map(({ href, label, icon: Icon }) => {
            const active = pathname === href;
            return (
              <Link
                key={href}
                href={href}
                className={cn(
                  'flex items-center gap-3 rounded-md px-4 py-3 text-sm font-extrabold transition-colors',
                  active
                    ? 'bg-soft text-primary-dark'
                    : 'text-muted hover:bg-soft hover:text-primary-dark',
                )}
              >
                <Icon size={18} />
                {label}
              </Link>
            );
          })}
        </nav>
      </div>

      <div className="mt-6 space-y-4">
        {/* Bloc supérette active */}
        {isHydrated && (
          <div className="border-t border-line pt-4">
            <p className="mb-2 text-[10px] font-extrabold uppercase tracking-widest text-muted">
              Supérette active
            </p>
            <Link
              href="/stores"
              className="flex items-center gap-3 rounded-lg border border-line bg-soft px-3 py-2.5 transition-colors hover:border-primary/30 hover:bg-primary/5"
            >
              <div className="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-primary/10 text-sm font-black text-primary-dark">
                {selectedStore ? (selectedStore.logoLetter ?? selectedStore.name.charAt(0)) : '?'}
              </div>
              <div className="min-w-0 flex-1">
                <strong className="block truncate text-xs">
                  {selectedStore ? selectedStore.name : 'Aucune supérette'}
                </strong>
                <span className="text-[10px] text-primary">Changer →</span>
              </div>
            </Link>
          </div>
        )}

        {/* Bloc user */}
        <div className="border-t border-line pt-4">
          {user ? (
            <div className="flex items-center gap-3">
              <div className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary text-sm font-black text-white">
                {avatarLetters}
              </div>
              <div className="min-w-0 flex-1">
                <strong className="block truncate text-xs">{user.name || 'Client'}</strong>
                <span className="block truncate text-[11px] text-muted">{user.email}</span>
              </div>
              <button
                type="button"
                onClick={handleLogout}
                aria-label="Se déconnecter"
                className="grid h-8 w-8 shrink-0 place-items-center rounded-md text-muted hover:bg-soft hover:text-red-600"
              >
                <LogOut size={16} />
              </button>
            </div>
          ) : (
            <div className="grid gap-2">
              <Link
                href="/login"
                className="flex items-center gap-2 rounded-md px-4 py-2.5 text-sm font-extrabold text-muted hover:bg-soft hover:text-primary-dark"
              >
                <LogIn size={16} />
                Se connecter
              </Link>
              <Link
                href="/register"
                className="flex items-center justify-center rounded-md bg-primary px-4 py-2.5 text-sm font-extrabold text-white hover:bg-primary-dark"
              >
                Créer un compte
              </Link>
            </div>
          )}
        </div>
      </div>
    </aside>
  );
}
```

- [ ] **Step 7.3 — Mettre à jour le test client.layout existant**

Le test mock `DesktopNav`. Il faut aussi mocker `SelectedStoreProvider` et `StoreContextPill` pour que le test de layout continue de passer.

Ouvrir `apps/frontend/src/tests/client.layout.test.tsx` et ajouter les mocks :

```tsx
import { render, screen } from '@testing-library/react';
import React from 'react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('next/navigation', () => ({
  usePathname: () => '/',
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
}));
vi.mock('@/components/layout/DesktopNav', () => ({
  DesktopNav: () => <aside data-testid="desktop-nav" />,
}));
vi.mock('@/components/layout/BottomNav', () => ({
  BottomNav: () => <nav data-testid="bottom-nav" />,
}));
vi.mock('@/lib/store/SelectedStoreContext', () => ({
  SelectedStoreProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
  useSelectedStore: () => ({ selectedStore: null, selectStore: vi.fn(), clearStore: vi.fn() }),
}));
vi.mock('@/components/store/StoreContextPill', () => ({
  StoreContextPill: () => <div data-testid="store-context-pill" />,
}));

import ClientLayout from '@/app/(client)/layout';

describe('ClientLayout', () => {
  it('rend la DesktopNav et la BottomNav', () => {
    render(<ClientLayout>page</ClientLayout>);
    expect(screen.getByTestId('desktop-nav')).toBeTruthy();
    expect(screen.getByTestId('bottom-nav')).toBeTruthy();
  });

  it('rend les children une seule fois', () => {
    const { container } = render(<ClientLayout><span data-testid="child">content</span></ClientLayout>);
    expect(container.querySelectorAll('[data-testid="child"]')).toHaveLength(1);
  });

  it('les children sont dans un <main>', () => {
    const { container } = render(<ClientLayout>page</ClientLayout>);
    expect(container.querySelector('main')).toBeTruthy();
    expect(container.querySelector('main')?.textContent).toContain('page');
  });

  it('contraint la colonne de contenu desktop pour éviter les débordements horizontaux', () => {
    const { container } = render(<ClientLayout>page</ClientLayout>);
    const contentColumn = container.querySelector('[data-testid="client-content-column"]');
    const main = container.querySelector('main');
    expect(contentColumn?.className).toContain('min-w-0');
    expect(main?.className).toContain('min-w-0');
  });

  it('rend la StoreContextPill dans le main', () => {
    render(<ClientLayout>page</ClientLayout>);
    expect(screen.getByTestId('store-context-pill')).toBeTruthy();
  });
});
```

- [ ] **Step 7.4 — Vérifier que les tests passent**

```bash
cd apps/frontend && npm run test:run -- src/tests/client.layout.test.tsx
```
Résultat attendu : 5 passed

- [ ] **Step 7.5 — Commit**

```bash
git add apps/frontend/src/app/\(client\)/layout.tsx \
        apps/frontend/src/components/layout/DesktopNav.tsx \
        apps/frontend/src/tests/client.layout.test.tsx
git commit -m "feat(store-context): wire SelectedStoreProvider, StoreContextPill, and DesktopNav store block"
```

---

### Task 8: Wiring — pages stores + QR

**Files:**
- Modify: `apps/frontend/src/app/(client)/stores/page.tsx`
- Modify: `apps/frontend/src/app/(client)/stores/[shopId]/page.tsx`
- Modify: `apps/frontend/src/app/(client)/stores/by-qr/[qrToken]/page.tsx`

- [ ] **Step 8.1 — Mettre à jour stores/page.tsx**

```tsx
// apps/frontend/src/app/(client)/stores/page.tsx
import Link from 'next/link';
import { TopBar } from '@/components/layout/TopBar';
import { StoreSearchCombobox } from '@/components/store/StoreSearchCombobox';
import { StoreSelectList } from '@/components/store/StoreSelectList';
import { listShops } from '@/lib/services';
import type { Shop } from '@/types';

export const dynamic = 'force-dynamic';

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
        subtitle="Scanner le QR code ou rechercher par nom"
        backHref="/"
      />
      <StoreSearchCombobox />
      <StoreSelectList shops={shops} />
      <p className="mt-4 text-center text-xs text-muted">
        Tu peux aussi{' '}
        <Link href="/" className="font-extrabold text-primary">
          scanner directement
        </Link>{' '}
        le QR à l&apos;entrée.
      </p>
    </>
  );
}
```

- [ ] **Step 8.2 — Mettre à jour stores/[shopId]/page.tsx**

Remplacer les deux blocs `<Link href=".../catalog">Commencer ma Kadhia</Link>` (desktop + mobile sticky) par `StartKadhiaCta` :

```tsx
// apps/frontend/src/app/(client)/stores/[shopId]/page.tsx
import { notFound } from 'next/navigation';
import { Hero } from '@/components/layout/Hero';
import { TopBar } from '@/components/layout/TopBar';
import { Card } from '@/components/ui/Card';
import { Summary, SummaryRow } from '@/components/ui/Summary';
import { StickyBottom } from '@/components/layout/StickyBottom';
import { StartKadhiaCta } from '@/components/store/StartKadhiaCta';
import { getShop } from '@/lib/services';

export default async function StoreDetailPage({
  params,
}: {
  params: { shopId: string };
}) {
  const shop = await getShop(params.shopId);
  if (!shop) notFound();

  const badgeText = shop.isActive
    ? `Ouverte · Retrait dès ${shop.nextPickupAt ?? '—'}`
    : 'Fermée';

  const shopForCta = { id: shop.id, name: shop.name, logoLetter: shop.logoLetter };

  return (
    <>
      <TopBar
        title={shop.name}
        subtitle={[shop.address, shop.city].filter(Boolean).join(' · ')}
        backHref="/"
      />
      <div className="md:grid md:grid-cols-[1.3fr_0.7fr] md:gap-5">
        <Hero
          badge={badgeText}
          title={shop.name}
          subtitle="Produits du quotidien, boissons, lait, pâtes, conserves, hygiène et snacks."
        />
        <div className="mt-4 md:mt-0 space-y-3">
          <div className="grid grid-cols-2 gap-2.5">
            <Card compact>
              <strong className="block text-sm">Horaires</strong>
              <span className="mt-1 block text-xs text-muted">
                {shop.opensAt && shop.closesAt ? `${shop.opensAt} — ${shop.closesAt}` : '—'}
              </span>
            </Card>
            <Card compact>
              <strong className="block text-sm">Distance</strong>
              <span className="mt-1 block text-xs text-muted">
                {shop.distanceKm != null ? `${shop.distanceKm} km` : '—'}
              </span>
            </Card>
          </div>
          <Card>
            <Summary>
              <SummaryRow label="Paiement" value="Sur place" />
              <SummaryRow label="Créneau" value={shop.nextPickupAt ? `Dès ${shop.nextPickupAt}` : '—'} />
              <SummaryRow label="Note" value={shop.rating?.toFixed(1) ?? '—'} />
            </Summary>
          </Card>
          <div className="hidden md:block">
            <StartKadhiaCta shop={shopForCta} />
          </div>
        </div>
      </div>
      <StickyBottom className="md:hidden">
        <StartKadhiaCta shop={shopForCta} />
      </StickyBottom>
    </>
  );
}
```

- [ ] **Step 8.3 — Mettre à jour by-qr/[qrToken]/page.tsx**

Ajouter `selectStore` appelé juste avant la redirection :

```tsx
// apps/frontend/src/app/(client)/stores/by-qr/[qrToken]/page.tsx
'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { getShopBySlug, recordStoreVisit } from '@/lib/services';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';

export default function ByQrPage({ params }: { params: { qrToken: string } }) {
  const router = useRouter();
  const { selectStore } = useSelectedStore();
  const [error, setError] = useState(false);

  useEffect(() => {
    void getShopBySlug(params.qrToken)
      .then((shop) => {
        if (!shop) { setError(true); return; }

        void recordStoreVisit(shop.id, 'qr_code').catch((err) => {
          const status = (err as { response?: { status?: number } }).response?.status;
          if (status !== 401 && status !== 403) {
            console.error('[store-qr] recordStoreVisit failed', { shopId: shop.id, err });
          }
        });

        selectStore({ id: shop.id, name: shop.name, logoLetter: shop.logoLetter });
        router.replace(`/stores/${shop.id}/catalog`);
      })
      .catch(() => setError(true));
  }, [params.qrToken, router, selectStore]);

  if (error) {
    return (
      <div className="flex min-h-screen items-center justify-center p-6 text-center">
        <div>
          <p className="text-sm text-muted">QR code non reconnu ou supérette indisponible.</p>
          <a href="/" className="mt-3 block text-sm font-semibold text-primary">
            Retour à l&apos;accueil
          </a>
        </div>
      </div>
    );
  }

  return (
    <div className="flex min-h-screen items-center justify-center">
      <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
    </div>
  );
}
```

- [ ] **Step 8.4 — Mettre à jour le test by-qr existant**

Le test `client.by-qr-page.test.tsx` doit mocker `useSelectedStore` :

```tsx
// apps/frontend/src/tests/client.by-qr-page.test.tsx
import { render, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const replace = vi.fn();
const selectStore = vi.fn();

vi.mock('next/navigation', () => ({
  useRouter: () => ({ replace }),
}));
vi.mock('@/lib/services', () => ({
  getShopBySlug: vi.fn(),
  recordStoreVisit: vi.fn(),
}));
vi.mock('@/lib/store/SelectedStoreContext', () => ({
  useSelectedStore: () => ({ selectStore, selectedStore: null, clearStore: vi.fn() }),
}));

import ByQrPage from '@/app/(client)/stores/by-qr/[qrToken]/page';
import { getShopBySlug, recordStoreVisit } from '@/lib/services';

describe('ByQrPage', () => {
  beforeEach(() => {
    replace.mockClear();
    selectStore.mockClear();
    vi.clearAllMocks();
  });

  it("redirige vers le catalogue et auto-sélectionne le store", async () => {
    vi.mocked(getShopBySlug).mockResolvedValue({
      id: 'store-1', name: 'Supérette El Amen', slug: 'superette-el-amen',
      city: 'Tunis', isActive: true, address: null, phone: null,
    });
    vi.mocked(recordStoreVisit).mockReturnValue(new Promise(() => {}));

    render(<ByQrPage params={{ qrToken: 'demo-superette-el-amen' }} />);

    await waitFor(() => {
      expect(replace).toHaveBeenCalledWith('/stores/store-1/catalog');
    });
    expect(selectStore).toHaveBeenCalledWith({
      id: 'store-1', name: 'Supérette El Amen', logoLetter: undefined,
    });
  });
});
```

- [ ] **Step 8.5 — Vérifier les tests**

```bash
cd apps/frontend && npm run test:run -- src/tests/client.by-qr-page.test.tsx
```
Résultat attendu : 1 passed

- [ ] **Step 8.6 — Commit**

```bash
git add apps/frontend/src/app/\(client\)/stores/page.tsx \
        apps/frontend/src/app/\(client\)/stores/\[shopId\]/page.tsx \
        apps/frontend/src/app/\(client\)/stores/by-qr/\[qrToken\]/page.tsx \
        apps/frontend/src/tests/client.by-qr-page.test.tsx
git commit -m "feat(store-context): wire StoreSelectList, StartKadhiaCta, and QR auto-select"
```

---

### Task 9: Nettoyage home page

**Files:**
- Modify: `apps/frontend/src/app/(client)/page.tsx`

- [ ] **Step 9.1 — Supprimer le bloc "en vedette" et simplifier la home**

Remplacer entièrement `apps/frontend/src/app/(client)/page.tsx` :

```tsx
// apps/frontend/src/app/(client)/page.tsx
import Link from 'next/link';
import { Hero } from '@/components/layout/Hero';
import { getButtonClassName } from '@/components/ui/Button';
import { StoreCard } from '@/components/store/StoreCard';
import { ActiveKadhiaBanner } from '@/components/store/ActiveKadhiaBanner';
import { listShops } from '@/lib/services';
import type { Shop } from '@/types';

export const dynamic = 'force-dynamic';

export default async function HomePage() {
  let shops: Shop[] = [];
  try {
    shops = await listShops();
  } catch {
    // backend unavailable in this env — show empty state
  }
  const recent = shops.slice(0, 3);

  return (
    <>
      <ActiveKadhiaBanner />

      <Hero
        badge="Supérettes de quartier"
        title="Ta Kadhia prête sans attendre"
        subtitle="Scanne le QR code d'une supérette ou trouve un magasin proche."
        actions={
          <>
            <Link
              href="/stores/by-qr-scan"
              className={getButtonClassName({ variant: 'secondary' })}
            >
              Scanner un QR code
            </Link>
            <Link href="/stores" className={getButtonClassName({ variant: 'ghost' })}>
              Chercher une supérette
            </Link>
          </>
        }
      />

      <section className="mt-5">
        <header className="mb-2.5 flex items-baseline justify-between">
          <h2 className="text-h3 font-extrabold m-0">Supérettes récentes</h2>
          <Link href="/stores" className="text-xs font-extrabold text-primary">
            Voir tout
          </Link>
        </header>
        <div className="grid gap-2.5 md:grid-cols-3">
          {recent.length === 0 ? (
            <p className="col-span-3 py-4 text-center text-sm text-muted">
              Aucune supérette disponible. Scanne un QR code à l&apos;entrée.
            </p>
          ) : (
            recent.map((s) => (
              <StoreCard key={s.id} shop={s} href={`/stores/${s.id}/catalog`} />
            ))
          )}
        </div>
      </section>
    </>
  );
}
```

- [ ] **Step 9.2 — Vérifier la suite de tests complète**

```bash
cd apps/frontend && npm run test:run 2>&1 | tail -20
```
Résultat attendu : toutes les suites passent (aucun FAIL)

- [ ] **Step 9.3 — Vérifier le lint**

```bash
cd apps/frontend && npm run lint 2>&1 | tail -20
```
Résultat attendu : no errors

- [ ] **Step 9.4 — Commit final**

```bash
git add apps/frontend/src/app/\(client\)/page.tsx
git commit -m "feat(store-context): remove 'en vedette' block from home page — closes #306"
```

---

## Résumé des commits

| # | Commit |
|---|---|
| 1 | `feat(store-context): add SelectedStoreContext + useSelectedStore hook` |
| 2 | `feat(store-context): add hasActiveKadhia localStorage utility` |
| 3 | `feat(store-context): add StoreSwitchWarning dialog` |
| 4 | `feat(store-context): add StoreContextPill component` |
| 5 | `feat(store-context): add StoreSelectList + extend StoreCard with selected prop` |
| 6 | `feat(store-context): add StartKadhiaCta with auto-select and switch warning` |
| 7 | `feat(store-context): wire SelectedStoreProvider, StoreContextPill, and DesktopNav store block` |
| 8 | `feat(store-context): wire StoreSelectList, StartKadhiaCta, and QR auto-select` |
| 9 | `feat(store-context): remove 'en vedette' block from home page — closes #306` |
