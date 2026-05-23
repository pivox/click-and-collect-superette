# Admin Référentiel Produits — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implémenter les 4 sous-pages du Référentiel produits dans le backoffice admin (Catégories, Marques, Produits, Propositions) avec sidebar expandable, drawers create/edit, et expansion inline pour la modération des propositions.

**Architecture:** Composants partagés (`AdminTable`, `AdminDrawer`, `AdminConfirmDialog`) + hook `useSort` client-side + 4 services admin wrappant `apiClient`. Chaque sous-page compose les primitives et appelle son service dédié. La sidebar existante est mise à jour pour supporter les sous-items expandables.

**Tech Stack:** Next.js 14 · React 18 · TypeScript · Tailwind CSS · Vitest + React Testing Library · axios (via `apiClient`)

---

## Fichiers créés / modifiés

```
CRÉÉS
src/lib/types/admin/referentiel.types.ts
src/lib/hooks/useSort.ts
src/lib/services/admin/categories.service.ts
src/lib/services/admin/brands.service.ts
src/lib/services/admin/product-references.service.ts
src/lib/services/admin/proposals.service.ts
src/components/admin/ui/AdminTable.tsx
src/components/admin/ui/AdminDrawer.tsx
src/components/admin/ui/AdminConfirmDialog.tsx
src/components/admin/referentiel/categories/CategoryDrawer.tsx
src/components/admin/referentiel/marques/BrandDrawer.tsx
src/components/admin/referentiel/produits/ProductReferenceDrawer.tsx
src/components/admin/referentiel/propositions/ProposalRow.tsx
src/app/admin/referentiel/layout.tsx
src/app/admin/referentiel/categories/page.tsx
src/app/admin/referentiel/marques/page.tsx
src/app/admin/referentiel/produits/page.tsx
src/app/admin/referentiel/propositions/page.tsx
src/tests/hooks/useSort.test.ts

MODIFIÉS
src/components/admin/AdminSidebar.tsx
```

---

## Task 1 — TypeScript types

**Files:**
- Create: `src/lib/types/admin/referentiel.types.ts`

- [ ] **Step 1: Créer le fichier de types**

```ts
// src/lib/types/admin/referentiel.types.ts

// API responses use snake_case (backend @SerializedName annotations)
// Request payloads use camelCase (PHP property names, no @SerializedName on inputs)

export interface Category {
  id: string;
  name_fr: string;
  name_ar: string | null;
  slug: string;
  is_active: boolean;
  sort_order: number;
  parent_id: string | null;
  created_at: string;
  updated_at: string;
}

export interface CategoryListResponse {
  id: string;
  items: Category[];
  page: number;
  limit: number;
  total: number;
}

export interface CreateCategoryPayload {
  nameFr: string;
  nameAr?: string;
  slug?: string;
}

export interface UpdateCategoryPayload {
  nameFr?: string;
  nameAr?: string;
  isActive?: boolean;
}

export interface Brand {
  id: string;
  canonical_name: string;
  slug: string;
  aliases: string[];
  country: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface BrandListResponse {
  id: string;
  items: Brand[];
  page: number;
  limit: number;
  total: number;
}

export interface CreateBrandPayload {
  canonicalName: string;
  slug?: string;
  aliases?: string[];
  country?: string;
}

export interface UpdateBrandPayload {
  canonicalName?: string;
  slug?: string;
  aliases?: string[];
  country?: string;
  isActive?: boolean;
}

export interface ProductReference {
  id: string;
  name_fr: string;
  name_ar: string | null;
  variant_fr: string | null;
  variant_ar: string | null;
  brand_id: string;
  brand_name: string;
  category_id: string;
  category_name_fr: string;
  category_name_ar: string | null;
  unit: string;
  volume: string | null;
  barcode: string | null;
  aliases: string[];
  country: string;
  status: string;
  created_at: string;
  updated_at: string;
}

export interface ProductReferenceListResponse {
  id: string;
  items: ProductReference[];
  page: number;
  limit: number;
  total: number;
}

export interface ProductReferenceFilters {
  q?: string;
  brand?: string;
  category?: string;
  status?: string;
  page?: number;
  limit?: number;
}

export interface CreateProductReferencePayload {
  nameFr: string;
  nameAr?: string;
  variantFr?: string;
  variantAr?: string;
  brandId: string;
  categoryId: string;
  unit: string;
  volume?: string;
  barcode?: string;
  aliases?: string[];
  country?: string;
  status?: string;
}

export interface UpdateProductReferencePayload {
  nameFr?: string;
  nameAr?: string;
  variantFr?: string;
  variantAr?: string;
  brandId?: string;
  categoryId?: string;
  unit?: string;
  volume?: string;
  barcode?: string;
  aliases?: string[];
  country?: string;
  status?: string;
}

export interface Proposal {
  id: string;
  name_fr: string;
  name_ar: string | null;
  brand_name: string | null;
  category: string;
  status: string;
  rejection_reason: string | null;
  created_at: string;
  proposed_by: string;
  created_product_reference_id: string | null;
}

export interface ApproveProposalPayload {
  productReferenceId?: string;
  canonicalData?: {
    nameFr: string;
    brandId: string;
    categoryId: string;
  };
}
```

- [ ] **Step 2: Commit**

```bash
git add src/lib/types/admin/referentiel.types.ts
git commit -m "feat(admin/referentiel): TypeScript types pour les 4 entités"
```

---

## Task 2 — Services admin

**Files:**
- Create: `src/lib/services/admin/categories.service.ts`
- Create: `src/lib/services/admin/brands.service.ts`
- Create: `src/lib/services/admin/product-references.service.ts`
- Create: `src/lib/services/admin/proposals.service.ts`

- [ ] **Step 1: Créer `categories.service.ts`**

```ts
// src/lib/services/admin/categories.service.ts
import { apiClient } from '@/lib/api';
import type {
  CategoryListResponse,
  Category,
  CreateCategoryPayload,
  UpdateCategoryPayload,
} from '@/lib/types/admin/referentiel.types';

export async function listCategories(page = 1, limit = 20): Promise<CategoryListResponse> {
  const { data } = await apiClient.get<CategoryListResponse>('/api/admin/categories', {
    params: { page, limit },
  });
  return data;
}

export async function createCategory(payload: CreateCategoryPayload): Promise<Category> {
  const { data } = await apiClient.post<Category>('/api/admin/categories', payload);
  return data;
}

export async function updateCategory(id: string, payload: UpdateCategoryPayload): Promise<Category> {
  const { data } = await apiClient.patch<Category>(`/api/admin/categories/${id}`, payload);
  return data;
}

export async function deleteCategory(id: string): Promise<void> {
  await apiClient.delete(`/api/admin/categories/${id}`);
}
```

- [ ] **Step 2: Créer `brands.service.ts`**

```ts
// src/lib/services/admin/brands.service.ts
import { apiClient } from '@/lib/api';
import type {
  BrandListResponse,
  Brand,
  CreateBrandPayload,
  UpdateBrandPayload,
} from '@/lib/types/admin/referentiel.types';

export async function listBrands(page = 1, limit = 20): Promise<BrandListResponse> {
  const { data } = await apiClient.get<BrandListResponse>('/api/admin/brands', {
    params: { page, limit },
  });
  return data;
}

export async function createBrand(payload: CreateBrandPayload): Promise<Brand> {
  const { data } = await apiClient.post<Brand>('/api/admin/brands', payload);
  return data;
}

export async function updateBrand(id: string, payload: UpdateBrandPayload): Promise<Brand> {
  const { data } = await apiClient.patch<Brand>(`/api/admin/brands/${id}`, payload);
  return data;
}

export async function deleteBrand(id: string): Promise<void> {
  await apiClient.delete(`/api/admin/brands/${id}`);
}
```

- [ ] **Step 3: Créer `product-references.service.ts`**

```ts
// src/lib/services/admin/product-references.service.ts
import { apiClient } from '@/lib/api';
import type {
  ProductReferenceListResponse,
  ProductReference,
  ProductReferenceFilters,
  CreateProductReferencePayload,
  UpdateProductReferencePayload,
} from '@/lib/types/admin/referentiel.types';

export async function listProductReferences(
  filters: ProductReferenceFilters = {},
): Promise<ProductReferenceListResponse> {
  const { data } = await apiClient.get<ProductReferenceListResponse>(
    '/api/admin/product-references',
    {
      params: {
        page: filters.page ?? 1,
        limit: filters.limit ?? 20,
        ...(filters.q ? { q: filters.q } : {}),
        ...(filters.brand ? { brand: filters.brand } : {}),
        ...(filters.category ? { category: filters.category } : {}),
        ...(filters.status ? { status: filters.status } : {}),
      },
    },
  );
  return data;
}

export async function createProductReference(
  payload: CreateProductReferencePayload,
): Promise<ProductReference> {
  const { data } = await apiClient.post<ProductReference>(
    '/api/admin/product-references',
    payload,
  );
  return data;
}

export async function updateProductReference(
  id: string,
  payload: UpdateProductReferencePayload,
): Promise<ProductReference> {
  const { data } = await apiClient.patch<ProductReference>(
    `/api/admin/product-references/${id}`,
    payload,
  );
  return data;
}

export async function archiveProductReference(id: string): Promise<ProductReference> {
  const { data } = await apiClient.patch<ProductReference>(
    `/api/admin/product-references/${id}/archive`,
  );
  return data;
}
```

- [ ] **Step 4: Créer `proposals.service.ts`**

Note: `GET /api/admin/product-proposals` retourne un tableau direct (pas d'enveloppe paginée — TODO backend).

```ts
// src/lib/services/admin/proposals.service.ts
import { apiClient } from '@/lib/api';
import type { Proposal, ApproveProposalPayload } from '@/lib/types/admin/referentiel.types';

export async function listProposals(status?: string, page = 1): Promise<Proposal[]> {
  const { data } = await apiClient.get<Proposal[]>('/api/admin/product-proposals', {
    params: {
      page,
      limit: 20,
      ...(status ? { status } : {}),
    },
  });
  return data;
}

export async function approveProposal(
  id: string,
  payload: ApproveProposalPayload,
): Promise<void> {
  await apiClient.patch(`/api/admin/product-proposals/${id}/approve`, payload);
}

export async function rejectProposal(id: string, reason: string): Promise<void> {
  await apiClient.patch(`/api/admin/product-proposals/${id}/reject`, { reason });
}
```

- [ ] **Step 5: Commit**

```bash
git add src/lib/services/admin/
git commit -m "feat(admin/referentiel): services categories, brands, product-references, proposals"
```

---

## Task 3 — Hook `useSort` (TDD)

**Files:**
- Create: `src/lib/hooks/useSort.ts`
- Test: `src/tests/hooks/useSort.test.ts`

- [ ] **Step 1: Écrire les tests en premier**

```ts
// src/tests/hooks/useSort.test.ts
import { renderHook, act } from '@testing-library/react';
import { useSort } from '@/lib/hooks/useSort';

const items = [
  { id: '1', name: 'Banane', count: 10 },
  { id: '2', name: 'Abricot', count: 5 },
  { id: '3', name: 'Cerise', count: 20 },
];

describe('useSort', () => {
  it('retourne les items dans l\'ordre original sans tri', () => {
    const { result } = renderHook(() => useSort(items));
    expect(result.current.sorted.map((i) => i.id)).toEqual(['1', '2', '3']);
    expect(result.current.sortKey).toBeNull();
  });

  it('trie en ordre ascendant au premier clic', () => {
    const { result } = renderHook(() => useSort(items));
    act(() => result.current.toggleSort('name'));
    expect(result.current.sorted.map((i) => i.id)).toEqual(['2', '1', '3']);
    expect(result.current.sortDir).toBe('asc');
  });

  it('bascule en ordre descendant au deuxième clic sur la même colonne', () => {
    const { result } = renderHook(() => useSort(items));
    act(() => result.current.toggleSort('name'));
    act(() => result.current.toggleSort('name'));
    expect(result.current.sorted.map((i) => i.id)).toEqual(['3', '1', '2']);
    expect(result.current.sortDir).toBe('desc');
  });

  it('repart en asc et change la clé quand on clique sur une autre colonne', () => {
    const { result } = renderHook(() => useSort(items));
    act(() => result.current.toggleSort('name'));
    act(() => result.current.toggleSort('name')); // desc
    act(() => result.current.toggleSort('count')); // nouvelle clé → asc
    expect(result.current.sortDir).toBe('asc');
    expect(result.current.sortKey).toBe('count');
    expect(result.current.sorted.map((i) => i.id)).toEqual(['2', '1', '3']); // 5, 10, 20
  });

  it('place les valeurs null en fin de liste', () => {
    const withNull = [
      { id: '1', name: 'Banane' as string | null },
      { id: '2', name: null },
      { id: '3', name: 'Abricot' as string | null },
    ];
    const { result } = renderHook(() => useSort(withNull));
    act(() => result.current.toggleSort('name'));
    expect(result.current.sorted.map((i) => i.id)).toEqual(['3', '1', '2']);
  });
});
```

- [ ] **Step 2: Vérifier que les tests échouent**

```bash
cd apps/frontend && npx vitest run src/tests/hooks/useSort.test.ts
```

Résultat attendu : FAIL — `Cannot find module '@/lib/hooks/useSort'`

- [ ] **Step 3: Implémenter `useSort.ts`**

```ts
// src/lib/hooks/useSort.ts
import { useState, useMemo } from 'react';

type SortDir = 'asc' | 'desc';

interface UseSortResult<T> {
  sorted: T[];
  sortKey: keyof T | null;
  sortDir: SortDir;
  toggleSort: (key: keyof T) => void;
}

export function useSort<T>(items: T[]): UseSortResult<T> {
  const [sortKey, setSortKey] = useState<keyof T | null>(null);
  const [sortDir, setSortDir] = useState<SortDir>('asc');

  const toggleSort = (key: keyof T) => {
    if (sortKey === key) {
      setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortKey(key);
      setSortDir('asc');
    }
  };

  const sorted = useMemo(() => {
    if (!sortKey) return items;
    return [...items].sort((a, b) => {
      const av = a[sortKey];
      const bv = b[sortKey];
      if (av == null) return 1;
      if (bv == null) return -1;
      const cmp = String(av).localeCompare(String(bv), undefined, {
        numeric: true,
        sensitivity: 'base',
      });
      return sortDir === 'asc' ? cmp : -cmp;
    });
  }, [items, sortKey, sortDir]);

  return { sorted, sortKey, sortDir, toggleSort };
}
```

- [ ] **Step 4: Vérifier que les tests passent**

```bash
cd apps/frontend && npx vitest run src/tests/hooks/useSort.test.ts
```

Résultat attendu : 5 tests PASS

- [ ] **Step 5: Commit**

```bash
git add src/lib/hooks/useSort.ts src/tests/hooks/useSort.test.ts
git commit -m "feat(admin/referentiel): hook useSort avec tests"
```

---

## Task 4 — Composant `AdminTable`

**Files:**
- Create: `src/components/admin/ui/AdminTable.tsx`

- [ ] **Step 1: Créer `AdminTable.tsx`**

```tsx
// src/components/admin/ui/AdminTable.tsx
'use client';
import { cn } from '@/lib/cn';
import { Button } from '@/components/ui/Button';

export interface Column<T> {
  key: string;
  label: string;
  sortable?: boolean;
  render?: (row: T) => React.ReactNode;
}

interface Pagination {
  page: number;
  total: number;
  limit: number;
  onPageChange: (page: number) => void;
}

interface AdminTableProps<T extends { id: string }> {
  columns: Column<T>[];
  data: T[];
  isLoading?: boolean;
  emptyMessage?: string;
  emptyAction?: { label: string; onClick: () => void };
  pagination?: Pagination;
  sortKey?: string | null;
  sortDir?: 'asc' | 'desc';
  onSort?: (key: string) => void;
}

export function AdminTable<T extends { id: string }>({
  columns,
  data,
  isLoading,
  emptyMessage = 'Aucun résultat',
  emptyAction,
  pagination,
  sortKey,
  sortDir,
  onSort,
}: AdminTableProps<T>) {
  const pageCount = pagination ? Math.ceil(pagination.total / pagination.limit) : 1;

  return (
    <div className="rounded-xl border border-line bg-card overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="border-b border-line bg-soft">
            <tr>
              {columns.map((col) => (
                <th
                  key={col.key}
                  onClick={col.sortable && onSort ? () => onSort(col.key) : undefined}
                  className={cn(
                    'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted',
                    col.sortable && 'cursor-pointer select-none hover:text-ink',
                  )}
                >
                  {col.label}
                  {col.sortable && sortKey === col.key && (
                    <span className="ml-1">{sortDir === 'asc' ? '↑' : '↓'}</span>
                  )}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {isLoading ? (
              Array.from({ length: 5 }).map((_, i) => (
                <tr key={i}>
                  {columns.map((col) => (
                    <td key={col.key} className="px-4 py-3">
                      <div className="h-4 w-3/4 animate-pulse rounded bg-soft" />
                    </td>
                  ))}
                </tr>
              ))
            ) : data.length === 0 ? (
              <tr>
                <td colSpan={columns.length} className="px-4 py-12 text-center">
                  <p className="text-sm text-muted">{emptyMessage}</p>
                  {emptyAction && (
                    <Button
                      variant="ghost"
                      size="md"
                      className="mt-3"
                      onClick={emptyAction.onClick}
                    >
                      {emptyAction.label}
                    </Button>
                  )}
                </td>
              </tr>
            ) : (
              data.map((row) => (
                <tr key={row.id} className="hover:bg-soft/50">
                  {columns.map((col) => (
                    <td key={col.key} className="px-4 py-3">
                      {col.render
                        ? col.render(row)
                        : String((row as Record<string, unknown>)[col.key] ?? '')}
                    </td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
      {pagination && (
        <div className="flex items-center justify-between border-t border-line px-4 py-3">
          <span className="text-xs text-muted">
            {pagination.total} résultat{pagination.total !== 1 ? 's' : ''}
          </span>
          <div className="flex items-center gap-2">
            <Button
              variant="ghost"
              size="md"
              disabled={pagination.page <= 1}
              onClick={() => pagination.onPageChange(pagination.page - 1)}
            >
              ← Précédent
            </Button>
            <span className="text-xs text-muted">
              {pagination.page} / {Math.max(1, pageCount)}
            </span>
            <Button
              variant="ghost"
              size="md"
              disabled={pagination.page >= pageCount}
              onClick={() => pagination.onPageChange(pagination.page + 1)}
            >
              Suivant →
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
```

- [ ] **Step 2: Commit**

```bash
git add src/components/admin/ui/AdminTable.tsx
git commit -m "feat(admin/ui): composant AdminTable générique"
```

---

## Task 5 — Composant `AdminDrawer`

**Files:**
- Create: `src/components/admin/ui/AdminDrawer.tsx`

- [ ] **Step 1: Créer `AdminDrawer.tsx`**

```tsx
// src/components/admin/ui/AdminDrawer.tsx
'use client';
import { useEffect } from 'react';
import { cn } from '@/lib/cn';
import { Button } from '@/components/ui/Button';

interface AdminDrawerProps {
  open: boolean;
  onClose: () => void;
  title: string;
  onSubmit: () => void;
  isSubmitting?: boolean;
  children: React.ReactNode;
  size?: 'md' | 'lg';
}

export function AdminDrawer({
  open,
  onClose,
  title,
  onSubmit,
  isSubmitting,
  children,
  size = 'md',
}: AdminDrawerProps) {
  useEffect(() => {
    if (!open) return;
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex justify-end">
      <div className="absolute inset-0 bg-black/40" onClick={onClose} />
      <div
        className={cn(
          'relative flex h-full flex-col bg-card shadow-floating',
          size === 'md' ? 'w-full max-w-md' : 'w-full max-w-xl',
        )}
      >
        <div className="flex items-center justify-between border-b border-line px-6 py-4">
          <h2 className="font-black text-ink">{title}</h2>
          <button
            onClick={onClose}
            className="rounded p-1 text-muted hover:bg-soft hover:text-ink"
          >
            ✕
          </button>
        </div>
        <div className="flex-1 overflow-y-auto px-6 py-5">{children}</div>
        <div className="flex gap-3 border-t border-line px-6 py-4">
          <Button onClick={onSubmit} disabled={isSubmitting} full>
            {isSubmitting ? 'Enregistrement…' : 'Enregistrer'}
          </Button>
          <Button variant="ghost" onClick={onClose} disabled={isSubmitting} full>
            Annuler
          </Button>
        </div>
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Commit**

```bash
git add src/components/admin/ui/AdminDrawer.tsx
git commit -m "feat(admin/ui): composant AdminDrawer slide-over"
```

---

## Task 6 — Composant `AdminConfirmDialog`

**Files:**
- Create: `src/components/admin/ui/AdminConfirmDialog.tsx`

- [ ] **Step 1: Créer `AdminConfirmDialog.tsx`**

```tsx
// src/components/admin/ui/AdminConfirmDialog.tsx
'use client';
import { Button } from '@/components/ui/Button';

interface AdminConfirmDialogProps {
  open: boolean;
  onClose: () => void;
  onConfirm: () => void;
  title: string;
  message: string;
  confirmLabel?: string;
  variant?: 'danger' | 'warning';
}

export function AdminConfirmDialog({
  open,
  onClose,
  onConfirm,
  title,
  message,
  confirmLabel = 'Confirmer',
  variant = 'danger',
}: AdminConfirmDialogProps) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      <div className="absolute inset-0 bg-black/40" onClick={onClose} />
      <div className="relative w-full max-w-sm rounded-xl bg-card p-6 shadow-floating">
        <h3 className="mb-2 font-black text-ink">{title}</h3>
        <p className="mb-6 text-sm text-muted">{message}</p>
        <div className="flex gap-3">
          <Button variant={variant === 'danger' ? 'danger' : 'primary'} onClick={onConfirm} full>
            {confirmLabel}
          </Button>
          <Button variant="ghost" onClick={onClose} full>
            Annuler
          </Button>
        </div>
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Commit**

```bash
git add src/components/admin/ui/AdminConfirmDialog.tsx
git commit -m "feat(admin/ui): composant AdminConfirmDialog"
```

---

## Task 7 — Mise à jour `AdminSidebar`

**Files:**
- Modify: `src/components/admin/AdminSidebar.tsx`

- [ ] **Step 1: Remplacer le contenu de `AdminSidebar.tsx`**

```tsx
// src/components/admin/AdminSidebar.tsx
'use client';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { cn } from '@/lib/cn';

type SubItem = { href: string; label: string };
type NavItem = { href: string; label: string; icon: string; children?: SubItem[] };

const NAV_ITEMS: NavItem[] = [
  { href: '/admin/dashboard', label: 'Tableau de bord', icon: '▦' },
  { href: '/admin/marchands', label: 'Marchands', icon: '👤' },
  { href: '/admin/superettes', label: 'Supérettes', icon: '🏪' },
  {
    href: '/admin/referentiel/produits',
    label: 'Référentiel produits',
    icon: '📦',
    children: [
      { href: '/admin/referentiel/categories', label: 'Catégories' },
      { href: '/admin/referentiel/marques', label: 'Marques' },
      { href: '/admin/referentiel/produits', label: 'Produits' },
      { href: '/admin/referentiel/propositions', label: 'Propositions' },
    ],
  },
  { href: '/admin/audit', label: 'Audit logs', icon: '📋' },
];

export function AdminSidebar() {
  const pathname = usePathname();

  return (
    <nav className="flex w-60 shrink-0 flex-col bg-[#1a1f1b] text-white/70">
      <div className="px-5 py-6">
        <span className="text-xs font-extrabold uppercase tracking-widest text-primary">
          Kadhia Admin
        </span>
      </div>
      <ul className="flex-1 space-y-0.5 px-3 pb-6">
        {NAV_ITEMS.map((item) => {
          const isActive = item.children
            ? pathname.startsWith('/admin/referentiel')
            : pathname === item.href || pathname.startsWith(item.href + '/');
          const isExpanded = !!item.children && pathname.startsWith('/admin/referentiel');
          return (
            <li key={item.href}>
              <Link
                href={item.href}
                className={cn(
                  'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors',
                  isActive
                    ? 'bg-white/10 font-semibold text-white'
                    : 'hover:bg-white/5 hover:text-white',
                )}
              >
                <span className="text-base leading-none">{item.icon}</span>
                {item.label}
              </Link>
              {isExpanded && item.children && (
                <ul className="mt-0.5 space-y-0.5 pl-9">
                  {item.children.map((child) => {
                    const isChildActive =
                      pathname === child.href || pathname.startsWith(child.href + '/');
                    return (
                      <li key={child.href}>
                        <Link
                          href={child.href}
                          className={cn(
                            'block rounded-lg px-3 py-2 text-xs transition-colors',
                            isChildActive
                              ? 'bg-white/10 font-semibold text-white'
                              : 'hover:bg-white/5 hover:text-white',
                          )}
                        >
                          {child.label}
                        </Link>
                      </li>
                    );
                  })}
                </ul>
              )}
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
```

- [ ] **Step 2: Vérifier que le lint passe**

```bash
cd apps/frontend && npm run lint
```

Résultat attendu : 0 erreur

- [ ] **Step 3: Commit**

```bash
git add src/components/admin/AdminSidebar.tsx
git commit -m "feat(admin): sidebar expandable avec sous-items Référentiel"
```

---

## Task 8 — Routes et layout Référentiel

**Files:**
- Create: `src/app/admin/referentiel/layout.tsx`
- Create: `src/app/admin/referentiel/categories/page.tsx` (stub)
- Create: `src/app/admin/referentiel/marques/page.tsx` (stub)
- Create: `src/app/admin/referentiel/produits/page.tsx` (stub)
- Create: `src/app/admin/referentiel/propositions/page.tsx` (stub)

- [ ] **Step 1: Créer le layout wrapper**

```tsx
// src/app/admin/referentiel/layout.tsx
export default function ReferentielLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
```

- [ ] **Step 2: Créer les stubs de pages**

```tsx
// src/app/admin/referentiel/categories/page.tsx
export default function CategoriesPage() {
  return <div><h1 className="text-h1 font-black">Catégories</h1></div>;
}
```

```tsx
// src/app/admin/referentiel/marques/page.tsx
export default function MarquesPage() {
  return <div><h1 className="text-h1 font-black">Marques</h1></div>;
}
```

```tsx
// src/app/admin/referentiel/produits/page.tsx
export default function ProduitsPage() {
  return <div><h1 className="text-h1 font-black">Produits référentiel</h1></div>;
}
```

```tsx
// src/app/admin/referentiel/propositions/page.tsx
export default function PropositionsPage() {
  return <div><h1 className="text-h1 font-black">Propositions</h1></div>;
}
```

- [ ] **Step 3: Vérifier le build**

```bash
cd apps/frontend && npm run build
```

Résultat attendu : build OK, 4 nouvelles routes `/admin/referentiel/*`

- [ ] **Step 4: Commit**

```bash
git add src/app/admin/referentiel/
git commit -m "feat(admin/referentiel): routes + layout stub (4 sous-pages)"
```

---

## Task 9 — Page Catégories

**Files:**
- Create: `src/components/admin/referentiel/categories/CategoryDrawer.tsx`
- Modify: `src/app/admin/referentiel/categories/page.tsx`

- [ ] **Step 1: Créer `CategoryDrawer.tsx`**

```tsx
// src/components/admin/referentiel/categories/CategoryDrawer.tsx
'use client';
import { useState, useEffect } from 'react';
import { AdminDrawer } from '@/components/admin/ui/AdminDrawer';
import { createCategory, updateCategory } from '@/lib/services/admin/categories.service';
import type { Category } from '@/lib/types/admin/referentiel.types';

interface CategoryDrawerProps {
  open: boolean;
  onClose: () => void;
  category: Category | null;
  onSaved: () => void;
}

export function CategoryDrawer({ open, onClose, category, onSaved }: CategoryDrawerProps) {
  const [nameFr, setNameFr] = useState('');
  const [nameAr, setNameAr] = useState('');
  const [slug, setSlug] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (category) {
      setNameFr(category.name_fr);
      setNameAr(category.name_ar ?? '');
      setIsActive(category.is_active);
    } else {
      setNameFr('');
      setNameAr('');
      setSlug('');
      setIsActive(true);
    }
    setError(null);
  }, [category, open]);

  const handleSubmit = async () => {
    if (!nameFr.trim()) { setError('Le nom FR est obligatoire.'); return; }
    setIsSubmitting(true);
    setError(null);
    try {
      if (category) {
        await updateCategory(category.id, {
          nameFr: nameFr.trim(),
          nameAr: nameAr.trim() || undefined,
          isActive,
        });
      } else {
        await createCategory({
          nameFr: nameFr.trim(),
          nameAr: nameAr.trim() || undefined,
          slug: slug.trim() || undefined,
        });
      }
      onSaved();
    } catch {
      setError('Une erreur est survenue. Vérifiez les données.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <AdminDrawer
      open={open}
      onClose={onClose}
      title={category ? 'Modifier la catégorie' : 'Nouvelle catégorie'}
      onSubmit={handleSubmit}
      isSubmitting={isSubmitting}
    >
      <div className="space-y-4">
        {error && (
          <div className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
            {error}
          </div>
        )}
        <div>
          <label className="mb-1 block text-sm font-semibold">Nom FR *</label>
          <input
            type="text"
            value={nameFr}
            onChange={(e) => setNameFr(e.target.value)}
            maxLength={160}
            className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>
        <div>
          <label className="mb-1 block text-sm font-semibold">Nom AR</label>
          <input
            type="text"
            value={nameAr}
            onChange={(e) => setNameAr(e.target.value)}
            maxLength={160}
            dir="rtl"
            className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>
        {!category && (
          <div>
            <label className="mb-1 block text-sm font-semibold">
              Slug{' '}
              <span className="font-normal text-muted">(auto-généré si vide)</span>
            </label>
            <input
              type="text"
              value={slug}
              onChange={(e) => setSlug(e.target.value)}
              maxLength={180}
              placeholder="produits-laitiers"
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>
        )}
        {category && (
          <div className="flex items-center gap-3">
            <label className="text-sm font-semibold">Actif</label>
            <button
              type="button"
              onClick={() => setIsActive((v) => !v)}
              className={`relative h-6 w-11 rounded-full transition-colors ${isActive ? 'bg-primary' : 'bg-line'}`}
            >
              <span
                className={`absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform ${isActive ? 'translate-x-5' : 'translate-x-0.5'}`}
              />
            </button>
            <span className="text-sm text-muted">{isActive ? 'Oui' : 'Non'}</span>
          </div>
        )}
      </div>
    </AdminDrawer>
  );
}
```

- [ ] **Step 2: Implémenter la page Catégories complète**

```tsx
// src/app/admin/referentiel/categories/page.tsx
'use client';
import { useState, useEffect } from 'react';
import { AdminTable, type Column } from '@/components/admin/ui/AdminTable';
import { AdminConfirmDialog } from '@/components/admin/ui/AdminConfirmDialog';
import { CategoryDrawer } from '@/components/admin/referentiel/categories/CategoryDrawer';
import { Button } from '@/components/ui/Button';
import { useSort } from '@/lib/hooks/useSort';
import { listCategories, deleteCategory } from '@/lib/services/admin/categories.service';
import type { Category } from '@/lib/types/admin/referentiel.types';

export default function CategoriesPage() {
  const [categories, setCategories] = useState<Category[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState('');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editTarget, setEditTarget] = useState<Category | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Category | null>(null);

  const filtered = categories.filter((c) =>
    search
      ? c.name_fr.toLowerCase().includes(search.toLowerCase()) ||
        (c.name_ar?.toLowerCase().includes(search.toLowerCase()) ?? false)
      : true,
  );
  const { sorted, sortKey, sortDir, toggleSort } = useSort(filtered);

  const load = async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await listCategories(page, 20);
      setCategories(data.items);
      setTotal(data.total);
    } catch {
      setError('Impossible de charger les catégories.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => { void load(); }, [page]);

  const handleDelete = async () => {
    if (!deleteTarget) return;
    try {
      await deleteCategory(deleteTarget.id);
      setDeleteTarget(null);
      void load();
    } catch {
      setError('Impossible de supprimer cette catégorie.');
      setDeleteTarget(null);
    }
  };

  const columns: Column<Category>[] = [
    { key: 'name_fr', label: 'Nom FR', sortable: true },
    { key: 'name_ar', label: 'Nom AR' },
    { key: 'slug', label: 'Slug' },
    { key: 'sort_order', label: 'Ordre', sortable: true },
    {
      key: 'is_active',
      label: 'Actif',
      sortable: true,
      render: (row) => (
        <span
          className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
            row.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
          }`}
        >
          {row.is_active ? 'Actif' : 'Inactif'}
        </span>
      ),
    },
    {
      key: 'actions',
      label: '',
      render: (row) => (
        <div className="flex justify-end gap-2">
          <button
            onClick={() => { setEditTarget(row); setDrawerOpen(true); }}
            className="text-xs text-muted hover:text-ink"
          >
            ✏ Modifier
          </button>
          <button
            onClick={() => setDeleteTarget(row)}
            className="text-xs text-danger hover:brightness-90"
          >
            🗑 Supprimer
          </button>
        </div>
      ),
    },
  ];

  return (
    <div>
      <div className="mb-5 flex items-center justify-between">
        <h1 className="text-h1 font-black">Catégories</h1>
        <Button size="md" onClick={() => { setEditTarget(null); setDrawerOpen(true); }}>
          + Nouvelle catégorie
        </Button>
      </div>
      <div className="mb-4">
        <input
          type="text"
          placeholder="Rechercher une catégorie…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-full max-w-sm rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        />
      </div>
      {error && (
        <div className="mb-4 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          {error}
        </div>
      )}
      <AdminTable
        columns={columns}
        data={sorted}
        isLoading={isLoading}
        emptyMessage="Aucune catégorie trouvée."
        emptyAction={{
          label: '+ Créer la première catégorie',
          onClick: () => { setEditTarget(null); setDrawerOpen(true); },
        }}
        pagination={{ page, total, limit: 20, onPageChange: setPage }}
        sortKey={sortKey as string | null}
        sortDir={sortDir}
        onSort={(key) => toggleSort(key as keyof Category)}
      />
      <CategoryDrawer
        open={drawerOpen}
        onClose={() => { setDrawerOpen(false); setEditTarget(null); }}
        category={editTarget}
        onSaved={() => { setDrawerOpen(false); setEditTarget(null); void load(); }}
      />
      <AdminConfirmDialog
        open={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title="Supprimer la catégorie"
        message={`Supprimer "${deleteTarget?.name_fr}" ? Cette action est irréversible.`}
        confirmLabel="Supprimer"
        variant="danger"
      />
    </div>
  );
}
```

- [ ] **Step 3: Vérifier le lint**

```bash
cd apps/frontend && npm run lint
```

Résultat attendu : 0 erreur

- [ ] **Step 4: Commit**

```bash
git add src/components/admin/referentiel/categories/ src/app/admin/referentiel/categories/
git commit -m "feat(admin/referentiel): page Catégories CRUD"
```

---

## Task 10 — Page Marques

**Files:**
- Create: `src/components/admin/referentiel/marques/BrandDrawer.tsx`
- Modify: `src/app/admin/referentiel/marques/page.tsx`

- [ ] **Step 1: Créer `BrandDrawer.tsx`**

```tsx
// src/components/admin/referentiel/marques/BrandDrawer.tsx
'use client';
import { useState, useEffect } from 'react';
import { AdminDrawer } from '@/components/admin/ui/AdminDrawer';
import { createBrand, updateBrand } from '@/lib/services/admin/brands.service';
import type { Brand } from '@/lib/types/admin/referentiel.types';

function TagInput({ tags, onChange }: { tags: string[]; onChange: (t: string[]) => void }) {
  const [input, setInput] = useState('');
  const add = () => {
    const v = input.trim();
    if (v && !tags.includes(v)) onChange([...tags, v]);
    setInput('');
  };
  return (
    <div className="flex min-h-[42px] w-full flex-wrap gap-1 rounded-md border border-line px-2 py-1 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
      {tags.map((t) => (
        <span key={t} className="flex items-center gap-1 rounded bg-soft px-2 py-0.5 text-xs">
          {t}
          <button
            type="button"
            onClick={() => onChange(tags.filter((x) => x !== t))}
            className="text-muted hover:text-danger"
          >
            ✕
          </button>
        </span>
      ))}
      <input
        type="text"
        value={input}
        onChange={(e) => setInput(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); add(); }
        }}
        onBlur={add}
        placeholder="Ajouter alias…"
        className="min-w-[120px] flex-1 bg-transparent text-sm outline-none"
      />
    </div>
  );
}

interface BrandDrawerProps {
  open: boolean;
  onClose: () => void;
  brand: Brand | null;
  onSaved: () => void;
}

export function BrandDrawer({ open, onClose, brand, onSaved }: BrandDrawerProps) {
  const [canonicalName, setCanonicalName] = useState('');
  const [slug, setSlug] = useState('');
  const [aliases, setAliases] = useState<string[]>([]);
  const [country, setCountry] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (brand) {
      setCanonicalName(brand.canonical_name);
      setSlug(brand.slug);
      setAliases(brand.aliases);
      setCountry(brand.country ?? '');
      setIsActive(brand.is_active);
    } else {
      setCanonicalName('');
      setSlug('');
      setAliases([]);
      setCountry('');
      setIsActive(true);
    }
    setError(null);
  }, [brand, open]);

  const handleSubmit = async () => {
    if (!canonicalName.trim()) { setError('Le nom canonique est obligatoire.'); return; }
    setIsSubmitting(true);
    setError(null);
    try {
      if (brand) {
        await updateBrand(brand.id, {
          canonicalName: canonicalName.trim(),
          slug: slug.trim() || undefined,
          aliases,
          country: country.trim() || undefined,
          isActive,
        });
      } else {
        await createBrand({
          canonicalName: canonicalName.trim(),
          slug: slug.trim() || undefined,
          aliases: aliases.length ? aliases : undefined,
          country: country.trim() || undefined,
        });
      }
      onSaved();
    } catch {
      setError('Une erreur est survenue. Vérifiez les données.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <AdminDrawer
      open={open}
      onClose={onClose}
      title={brand ? 'Modifier la marque' : 'Nouvelle marque'}
      onSubmit={handleSubmit}
      isSubmitting={isSubmitting}
    >
      <div className="space-y-4">
        {error && (
          <div className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
            {error}
          </div>
        )}
        <div>
          <label className="mb-1 block text-sm font-semibold">Nom canonique *</label>
          <input
            type="text"
            value={canonicalName}
            onChange={(e) => setCanonicalName(e.target.value)}
            maxLength={160}
            className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>
        <div>
          <label className="mb-1 block text-sm font-semibold">
            Slug <span className="font-normal text-muted">(auto-généré si vide)</span>
          </label>
          <input
            type="text"
            value={slug}
            onChange={(e) => setSlug(e.target.value)}
            maxLength={180}
            placeholder="ben-yedder"
            className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>
        <div>
          <label className="mb-1 block text-sm font-semibold">Pays (ISO 2 lettres)</label>
          <input
            type="text"
            value={country}
            onChange={(e) => setCountry(e.target.value.toUpperCase())}
            maxLength={2}
            placeholder="TN"
            className="w-20 rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>
        <div>
          <label className="mb-1 block text-sm font-semibold">Aliases</label>
          <p className="mb-1 text-xs text-muted">Appuyez sur Entrée ou virgule pour ajouter</p>
          <TagInput tags={aliases} onChange={setAliases} />
        </div>
        {brand && (
          <div className="flex items-center gap-3">
            <label className="text-sm font-semibold">Actif</label>
            <button
              type="button"
              onClick={() => setIsActive((v) => !v)}
              className={`relative h-6 w-11 rounded-full transition-colors ${isActive ? 'bg-primary' : 'bg-line'}`}
            >
              <span
                className={`absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform ${isActive ? 'translate-x-5' : 'translate-x-0.5'}`}
              />
            </button>
            <span className="text-sm text-muted">{isActive ? 'Oui' : 'Non'}</span>
          </div>
        )}
      </div>
    </AdminDrawer>
  );
}
```

- [ ] **Step 2: Implémenter la page Marques**

```tsx
// src/app/admin/referentiel/marques/page.tsx
'use client';
import { useState, useEffect } from 'react';
import { AdminTable, type Column } from '@/components/admin/ui/AdminTable';
import { AdminConfirmDialog } from '@/components/admin/ui/AdminConfirmDialog';
import { BrandDrawer } from '@/components/admin/referentiel/marques/BrandDrawer';
import { Button } from '@/components/ui/Button';
import { useSort } from '@/lib/hooks/useSort';
import { listBrands, deleteBrand } from '@/lib/services/admin/brands.service';
import type { Brand } from '@/lib/types/admin/referentiel.types';

export default function MarquesPage() {
  const [brands, setBrands] = useState<Brand[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState('');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editTarget, setEditTarget] = useState<Brand | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Brand | null>(null);

  const filtered = brands.filter((b) =>
    search ? b.canonical_name.toLowerCase().includes(search.toLowerCase()) : true,
  );
  const { sorted, sortKey, sortDir, toggleSort } = useSort(filtered);

  const load = async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await listBrands(page, 20);
      setBrands(data.items);
      setTotal(data.total);
    } catch {
      setError('Impossible de charger les marques.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => { void load(); }, [page]);

  const handleDelete = async () => {
    if (!deleteTarget) return;
    try {
      await deleteBrand(deleteTarget.id);
      setDeleteTarget(null);
      void load();
    } catch {
      setError('Impossible de supprimer cette marque.');
      setDeleteTarget(null);
    }
  };

  const columns: Column<Brand>[] = [
    { key: 'canonical_name', label: 'Nom canonique', sortable: true },
    {
      key: 'aliases',
      label: 'Aliases',
      render: (row) => (
        <div className="flex flex-wrap gap-1">
          {row.aliases.slice(0, 3).map((a) => (
            <span key={a} className="rounded bg-soft px-1.5 py-0.5 text-xs">{a}</span>
          ))}
          {row.aliases.length > 3 && (
            <span className="text-xs text-muted">+{row.aliases.length - 3}</span>
          )}
        </div>
      ),
    },
    { key: 'country', label: 'Pays', sortable: true },
    {
      key: 'is_active',
      label: 'Actif',
      sortable: true,
      render: (row) => (
        <span
          className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
            row.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
          }`}
        >
          {row.is_active ? 'Actif' : 'Inactif'}
        </span>
      ),
    },
    {
      key: 'actions',
      label: '',
      render: (row) => (
        <div className="flex justify-end gap-2">
          <button
            onClick={() => { setEditTarget(row); setDrawerOpen(true); }}
            className="text-xs text-muted hover:text-ink"
          >
            ✏ Modifier
          </button>
          <button
            onClick={() => setDeleteTarget(row)}
            className="text-xs text-danger hover:brightness-90"
          >
            🗑 Supprimer
          </button>
        </div>
      ),
    },
  ];

  return (
    <div>
      <div className="mb-5 flex items-center justify-between">
        <h1 className="text-h1 font-black">Marques</h1>
        <Button size="md" onClick={() => { setEditTarget(null); setDrawerOpen(true); }}>
          + Nouvelle marque
        </Button>
      </div>
      <div className="mb-4">
        <input
          type="text"
          placeholder="Rechercher une marque…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-full max-w-sm rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        />
      </div>
      {error && (
        <div className="mb-4 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          {error}
        </div>
      )}
      <AdminTable
        columns={columns}
        data={sorted}
        isLoading={isLoading}
        emptyMessage="Aucune marque trouvée."
        emptyAction={{
          label: '+ Créer la première marque',
          onClick: () => { setEditTarget(null); setDrawerOpen(true); },
        }}
        pagination={{ page, total, limit: 20, onPageChange: setPage }}
        sortKey={sortKey as string | null}
        sortDir={sortDir}
        onSort={(key) => toggleSort(key as keyof Brand)}
      />
      <BrandDrawer
        open={drawerOpen}
        onClose={() => { setDrawerOpen(false); setEditTarget(null); }}
        brand={editTarget}
        onSaved={() => { setDrawerOpen(false); setEditTarget(null); void load(); }}
      />
      <AdminConfirmDialog
        open={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title="Supprimer la marque"
        message={`Supprimer "${deleteTarget?.canonical_name}" ? Cette action est irréversible.`}
        confirmLabel="Supprimer"
        variant="danger"
      />
    </div>
  );
}
```

- [ ] **Step 3: Lint + commit**

```bash
cd apps/frontend && npm run lint
git add src/components/admin/referentiel/marques/ src/app/admin/referentiel/marques/
git commit -m "feat(admin/referentiel): page Marques CRUD"
```

---

## Task 11 — Page Produits

**Files:**
- Create: `src/components/admin/referentiel/produits/ProductReferenceDrawer.tsx`
- Modify: `src/app/admin/referentiel/produits/page.tsx`

- [ ] **Step 1: Créer `ProductReferenceDrawer.tsx`**

```tsx
// src/components/admin/referentiel/produits/ProductReferenceDrawer.tsx
'use client';
import { useState, useEffect } from 'react';
import { AdminDrawer } from '@/components/admin/ui/AdminDrawer';
import {
  createProductReference,
  updateProductReference,
} from '@/lib/services/admin/product-references.service';
import { listBrands } from '@/lib/services/admin/brands.service';
import { listCategories } from '@/lib/services/admin/categories.service';
import type { ProductReference, Brand, Category } from '@/lib/types/admin/referentiel.types';

const UNITS = ['litre', 'millilitre', 'kilogramme', 'gramme', 'piece', 'paquet'] as const;
const STATUSES = ['draft', 'pending_review', 'approved', 'rejected'] as const;

function TagInput({ tags, onChange }: { tags: string[]; onChange: (t: string[]) => void }) {
  const [input, setInput] = useState('');
  const add = () => {
    const v = input.trim();
    if (v && !tags.includes(v)) onChange([...tags, v]);
    setInput('');
  };
  return (
    <div className="flex min-h-[42px] w-full flex-wrap gap-1 rounded-md border border-line px-2 py-1 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
      {tags.map((t) => (
        <span key={t} className="flex items-center gap-1 rounded bg-soft px-2 py-0.5 text-xs">
          {t}
          <button
            type="button"
            onClick={() => onChange(tags.filter((x) => x !== t))}
            className="text-muted hover:text-danger"
          >
            ✕
          </button>
        </span>
      ))}
      <input
        type="text"
        value={input}
        onChange={(e) => setInput(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); add(); }
        }}
        onBlur={add}
        placeholder="Ajouter alias…"
        className="min-w-[100px] flex-1 bg-transparent text-sm outline-none"
      />
    </div>
  );
}

interface ProductReferenceDrawerProps {
  open: boolean;
  onClose: () => void;
  product: ProductReference | null;
  onSaved: () => void;
}

type FormState = {
  nameFr: string; nameAr: string; variantFr: string; variantAr: string;
  brandId: string; categoryId: string; unit: string; volume: string;
  barcode: string; country: string; status: string; aliases: string[];
};

const EMPTY_FORM: FormState = {
  nameFr: '', nameAr: '', variantFr: '', variantAr: '',
  brandId: '', categoryId: '', unit: '', volume: '',
  barcode: '', country: 'TN', status: 'draft', aliases: [],
};

export function ProductReferenceDrawer({
  open, onClose, product, onSaved,
}: ProductReferenceDrawerProps) {
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [brands, setBrands] = useState<Brand[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    void Promise.all([listBrands(1, 50), listCategories(1, 50)]).then(([b, c]) => {
      setBrands(b.items);
      setCategories(c.items);
    });
  }, []);

  useEffect(() => {
    if (product) {
      setForm({
        nameFr: product.name_fr,
        nameAr: product.name_ar ?? '',
        variantFr: product.variant_fr ?? '',
        variantAr: product.variant_ar ?? '',
        brandId: product.brand_id,
        categoryId: product.category_id,
        unit: product.unit,
        volume: product.volume ?? '',
        barcode: product.barcode ?? '',
        country: product.country,
        status: product.status === 'archived' ? 'draft' : product.status,
        aliases: product.aliases,
      });
    } else {
      setForm(EMPTY_FORM);
    }
    setError(null);
  }, [product, open]);

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) =>
    setForm((f) => ({ ...f, [key]: value }));

  const handleSubmit = async () => {
    if (!form.nameFr.trim() || !form.brandId || !form.categoryId || !form.unit) {
      setError('Nom FR, Marque, Catégorie et Unité sont obligatoires.');
      return;
    }
    setIsSubmitting(true);
    setError(null);
    try {
      const payload = {
        nameFr: form.nameFr.trim(),
        nameAr: form.nameAr.trim() || undefined,
        variantFr: form.variantFr.trim() || undefined,
        variantAr: form.variantAr.trim() || undefined,
        brandId: form.brandId,
        categoryId: form.categoryId,
        unit: form.unit,
        volume: form.volume.trim() || undefined,
        barcode: form.barcode.trim() || undefined,
        country: form.country.trim() || undefined,
        status: form.status,
        aliases: form.aliases.length ? form.aliases : undefined,
      };
      if (product) {
        await updateProductReference(product.id, payload);
      } else {
        await createProductReference(payload);
      }
      onSaved();
    } catch {
      setError('Une erreur est survenue. Vérifiez les données.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const field = (
    label: string,
    key: keyof FormState,
    opts?: { required?: boolean; maxLength?: number; dir?: string; placeholder?: string },
  ) => (
    <div>
      <label className="mb-1 block text-sm font-semibold">
        {label} {opts?.required && <span className="text-danger">*</span>}
      </label>
      <input
        type="text"
        value={form[key] as string}
        onChange={(e) => set(key, e.target.value as FormState[typeof key])}
        maxLength={opts?.maxLength}
        dir={opts?.dir}
        placeholder={opts?.placeholder}
        className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
      />
    </div>
  );

  return (
    <AdminDrawer
      open={open}
      onClose={onClose}
      title={product ? 'Modifier le produit' : 'Nouveau produit'}
      onSubmit={handleSubmit}
      isSubmitting={isSubmitting}
      size="lg"
    >
      <div className="space-y-4">
        {error && (
          <div className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
            {error}
          </div>
        )}
        <div className="grid grid-cols-2 gap-3">
          {field('Nom FR', 'nameFr', { required: true, maxLength: 255 })}
          {field('Nom AR', 'nameAr', { maxLength: 255, dir: 'rtl' })}
          {field('Variante FR', 'variantFr', { maxLength: 160 })}
          {field('Variante AR', 'variantAr', { maxLength: 160, dir: 'rtl' })}
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="mb-1 block text-sm font-semibold">
              Marque <span className="text-danger">*</span>
            </label>
            <select
              value={form.brandId}
              onChange={(e) => set('brandId', e.target.value)}
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
            >
              <option value="">Sélectionner…</option>
              {brands.map((b) => <option key={b.id} value={b.id}>{b.canonical_name}</option>)}
            </select>
          </div>
          <div>
            <label className="mb-1 block text-sm font-semibold">
              Catégorie <span className="text-danger">*</span>
            </label>
            <select
              value={form.categoryId}
              onChange={(e) => set('categoryId', e.target.value)}
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
            >
              <option value="">Sélectionner…</option>
              {categories.map((c) => <option key={c.id} value={c.id}>{c.name_fr}</option>)}
            </select>
          </div>
          <div>
            <label className="mb-1 block text-sm font-semibold">
              Unité <span className="text-danger">*</span>
            </label>
            <select
              value={form.unit}
              onChange={(e) => set('unit', e.target.value)}
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
            >
              <option value="">Sélectionner…</option>
              {UNITS.map((u) => <option key={u} value={u}>{u}</option>)}
            </select>
          </div>
          {field('Volume', 'volume', { placeholder: 'ex. 1, 500ml' })}
          {field('Code-barres', 'barcode', { maxLength: 64 })}
          {field('Pays', 'country', { maxLength: 2, placeholder: 'TN' })}
        </div>
        <div>
          <label className="mb-1 block text-sm font-semibold">Statut</label>
          <select
            value={form.status}
            onChange={(e) => set('status', e.target.value)}
            className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
          >
            {STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
          </select>
        </div>
        <div>
          <label className="mb-1 block text-sm font-semibold">Aliases</label>
          <TagInput tags={form.aliases} onChange={(t) => set('aliases', t)} />
        </div>
      </div>
    </AdminDrawer>
  );
}
```

- [ ] **Step 2: Implémenter la page Produits**

```tsx
// src/app/admin/referentiel/produits/page.tsx
'use client';
import { useState, useEffect, useCallback } from 'react';
import { AdminTable, type Column } from '@/components/admin/ui/AdminTable';
import { AdminConfirmDialog } from '@/components/admin/ui/AdminConfirmDialog';
import { ProductReferenceDrawer } from '@/components/admin/referentiel/produits/ProductReferenceDrawer';
import { Button } from '@/components/ui/Button';
import { useSort } from '@/lib/hooks/useSort';
import {
  listProductReferences,
  archiveProductReference,
} from '@/lib/services/admin/product-references.service';
import { listBrands } from '@/lib/services/admin/brands.service';
import { listCategories } from '@/lib/services/admin/categories.service';
import type { ProductReference, Brand, Category } from '@/lib/types/admin/referentiel.types';
import { cn } from '@/lib/cn';

const STATUS_STYLES: Record<string, string> = {
  approved: 'bg-green-100 text-green-700',
  pending_review: 'bg-yellow-100 text-yellow-700',
  draft: 'bg-gray-100 text-gray-600',
  rejected: 'bg-red-100 text-red-700',
  archived: 'bg-gray-50 text-gray-400',
};

export default function ProduitsPage() {
  const [products, setProducts] = useState<ProductReference[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [q, setQ] = useState('');
  const [debouncedQ, setDebouncedQ] = useState('');
  const [brandFilter, setBrandFilter] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [filterBrands, setFilterBrands] = useState<Brand[]>([]);
  const [filterCategories, setFilterCategories] = useState<Category[]>([]);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editTarget, setEditTarget] = useState<ProductReference | null>(null);
  const [archiveTarget, setArchiveTarget] = useState<ProductReference | null>(null);

  const { sorted, sortKey, sortDir, toggleSort } = useSort(products);

  useEffect(() => {
    void Promise.all([listBrands(1, 50), listCategories(1, 50)]).then(([b, c]) => {
      setFilterBrands(b.items);
      setFilterCategories(c.items);
    });
  }, []);

  useEffect(() => {
    const t = setTimeout(() => setDebouncedQ(q), 400);
    return () => clearTimeout(t);
  }, [q]);

  const load = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await listProductReferences({
        q: debouncedQ || undefined,
        brand: brandFilter || undefined,
        category: categoryFilter || undefined,
        status: statusFilter || undefined,
        page,
        limit: 20,
      });
      setProducts(data.items);
      setTotal(data.total);
    } catch {
      setError('Impossible de charger les produits.');
    } finally {
      setIsLoading(false);
    }
  }, [page, debouncedQ, brandFilter, categoryFilter, statusFilter]);

  useEffect(() => { void load(); }, [load]);
  useEffect(() => { setPage(1); }, [debouncedQ, brandFilter, categoryFilter, statusFilter]);

  const handleArchive = async () => {
    if (!archiveTarget) return;
    try {
      await archiveProductReference(archiveTarget.id);
      setArchiveTarget(null);
      void load();
    } catch {
      setError("Impossible d'archiver ce produit.");
      setArchiveTarget(null);
    }
  };

  const columns: Column<ProductReference>[] = [
    {
      key: 'name_fr',
      label: 'Produit',
      sortable: true,
      render: (row) => (
        <div>
          <div className={cn('font-medium', row.status === 'archived' && 'text-muted')}>
            {row.name_fr}
          </div>
          {row.variant_fr && <div className="text-xs text-muted">{row.variant_fr}</div>}
        </div>
      ),
    },
    { key: 'brand_name', label: 'Marque', sortable: true },
    { key: 'category_name_fr', label: 'Catégorie', sortable: true },
    { key: 'unit', label: 'Unité' },
    {
      key: 'status',
      label: 'Statut',
      sortable: true,
      render: (row) => (
        <span
          className={cn(
            'rounded-full px-2 py-0.5 text-xs font-semibold',
            STATUS_STYLES[row.status] ?? 'bg-gray-100 text-gray-600',
          )}
        >
          {row.status}
        </span>
      ),
    },
    {
      key: 'actions',
      label: '',
      render: (row) =>
        row.status !== 'archived' ? (
          <div className="flex justify-end gap-2">
            <button
              onClick={() => { setEditTarget(row); setDrawerOpen(true); }}
              className="text-xs text-muted hover:text-ink"
            >
              ✏
            </button>
            <button
              onClick={() => setArchiveTarget(row)}
              className="text-xs text-muted hover:text-danger"
            >
              ⊘
            </button>
          </div>
        ) : null,
    },
  ];

  return (
    <div>
      <div className="mb-5 flex items-center justify-between">
        <h1 className="text-h1 font-black">Produits référentiel</h1>
        <Button size="md" onClick={() => { setEditTarget(null); setDrawerOpen(true); }}>
          + Nouveau produit
        </Button>
      </div>
      <div className="mb-4 flex flex-wrap gap-3">
        <input
          type="text"
          placeholder="Rechercher (nom, code-barres)…"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          className="w-full max-w-xs rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        />
        <select
          value={brandFilter}
          onChange={(e) => setBrandFilter(e.target.value)}
          className="rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
        >
          <option value="">Toutes les marques</option>
          {filterBrands.map((b) => <option key={b.id} value={b.id}>{b.canonical_name}</option>)}
        </select>
        <select
          value={categoryFilter}
          onChange={(e) => setCategoryFilter(e.target.value)}
          className="rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
        >
          <option value="">Toutes les catégories</option>
          {filterCategories.map((c) => <option key={c.id} value={c.id}>{c.name_fr}</option>)}
        </select>
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
        >
          <option value="">Tous les statuts</option>
          <option value="draft">draft</option>
          <option value="pending_review">pending_review</option>
          <option value="approved">approved</option>
          <option value="rejected">rejected</option>
          <option value="archived">archived</option>
        </select>
      </div>
      {error && (
        <div className="mb-4 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          {error}
        </div>
      )}
      <AdminTable
        columns={columns}
        data={sorted}
        isLoading={isLoading}
        emptyMessage="Aucun produit trouvé."
        emptyAction={{
          label: '+ Créer le premier produit',
          onClick: () => { setEditTarget(null); setDrawerOpen(true); },
        }}
        pagination={{ page, total, limit: 20, onPageChange: setPage }}
        sortKey={sortKey as string | null}
        sortDir={sortDir}
        onSort={(key) => toggleSort(key as keyof ProductReference)}
      />
      <ProductReferenceDrawer
        open={drawerOpen}
        onClose={() => { setDrawerOpen(false); setEditTarget(null); }}
        product={editTarget}
        onSaved={() => { setDrawerOpen(false); setEditTarget(null); void load(); }}
      />
      <AdminConfirmDialog
        open={!!archiveTarget}
        onClose={() => setArchiveTarget(null)}
        onConfirm={handleArchive}
        title="Archiver le produit"
        message={`Archiver "${archiveTarget?.name_fr}" ? Le produit ne sera plus modifiable.`}
        confirmLabel="Archiver"
        variant="warning"
      />
    </div>
  );
}
```

- [ ] **Step 3: Lint + commit**

```bash
cd apps/frontend && npm run lint
git add src/components/admin/referentiel/produits/ src/app/admin/referentiel/produits/
git commit -m "feat(admin/referentiel): page Produits avec filtres server-side et archive"
```

---

## Task 12 — Page Propositions

**Files:**
- Create: `src/components/admin/referentiel/propositions/ProposalRow.tsx`
- Modify: `src/app/admin/referentiel/propositions/page.tsx`

- [ ] **Step 1: Créer `ProposalRow.tsx`**

```tsx
// src/components/admin/referentiel/propositions/ProposalRow.tsx
'use client';
import { useState, useEffect } from 'react';
import { approveProposal, rejectProposal } from '@/lib/services/admin/proposals.service';
import { listProductReferences } from '@/lib/services/admin/product-references.service';
import { listBrands } from '@/lib/services/admin/brands.service';
import { listCategories } from '@/lib/services/admin/categories.service';
import type { Proposal, ProductReference, Brand, Category } from '@/lib/types/admin/referentiel.types';
import { cn } from '@/lib/cn';

type ExpansionMode = 'approve' | 'reject';
type ApproveMode = 'link' | 'create';

interface ProposalRowProps {
  proposal: Proposal;
  isExpanded: ExpansionMode | null;
  onToggle: (id: string, mode: ExpansionMode | null) => void;
  onProcessed: () => void;
}

export function ProposalRow({ proposal, isExpanded, onToggle, onProcessed }: ProposalRowProps) {
  const [approveMode, setApproveMode] = useState<ApproveMode>('link');
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<ProductReference[]>([]);
  const [selectedRef, setSelectedRef] = useState<ProductReference | null>(null);
  const [isSearching, setIsSearching] = useState(false);
  const [newNameFr, setNewNameFr] = useState('');
  const [newBrandId, setNewBrandId] = useState('');
  const [newCategoryId, setNewCategoryId] = useState('');
  const [brands, setBrands] = useState<Brand[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [rejectReason, setRejectReason] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (isExpanded !== 'approve' || approveMode !== 'create') return;
    void Promise.all([listBrands(1, 50), listCategories(1, 50)]).then(([b, c]) => {
      setBrands(b.items);
      setCategories(c.items);
    });
  }, [isExpanded, approveMode]);

  useEffect(() => {
    if (!searchQuery || searchQuery.length < 2) { setSearchResults([]); return; }
    const t = setTimeout(async () => {
      setIsSearching(true);
      try {
        const data = await listProductReferences({ q: searchQuery, limit: 10 });
        setSearchResults(data.items);
      } finally {
        setIsSearching(false);
      }
    }, 300);
    return () => clearTimeout(t);
  }, [searchQuery]);

  const reset = () => {
    setSearchQuery(''); setSelectedRef(null); setSearchResults([]);
    setNewNameFr(''); setNewBrandId(''); setNewCategoryId('');
    setRejectReason(''); setError(null); setApproveMode('link');
  };

  const handleApprove = async () => {
    setError(null);
    if (approveMode === 'link') {
      if (!selectedRef) { setError('Sélectionnez un produit existant.'); return; }
      setIsSubmitting(true);
      try {
        await approveProposal(proposal.id, { productReferenceId: selectedRef.id });
        reset(); onToggle(proposal.id, null); onProcessed();
      } catch (e) {
        const msg = String(e);
        setError(msg.includes('409') ? 'Cette proposition a déjà été traitée.' : 'Une erreur est survenue.');
      } finally { setIsSubmitting(false); }
    } else {
      if (!newNameFr.trim() || !newBrandId || !newCategoryId) {
        setError('Nom FR, Marque et Catégorie sont obligatoires.');
        return;
      }
      setIsSubmitting(true);
      try {
        await approveProposal(proposal.id, {
          canonicalData: { nameFr: newNameFr.trim(), brandId: newBrandId, categoryId: newCategoryId },
        });
        reset(); onToggle(proposal.id, null); onProcessed();
      } catch (e) {
        const msg = String(e);
        setError(msg.includes('409') ? 'Cette proposition a déjà été traitée.' : 'Une erreur est survenue.');
      } finally { setIsSubmitting(false); }
    }
  };

  const handleReject = async () => {
    if (!rejectReason.trim()) { setError('La raison est obligatoire.'); return; }
    setIsSubmitting(true);
    setError(null);
    try {
      await rejectProposal(proposal.id, rejectReason.trim());
      reset(); onToggle(proposal.id, null); onProcessed();
    } catch {
      setError('Une erreur est survenue.');
    } finally { setIsSubmitting(false); }
  };

  const isPending = proposal.status === 'pending';

  return (
    <>
      <tr className={cn('hover:bg-soft/50', isExpanded && 'bg-soft/30')}>
        <td className="px-4 py-3 font-medium text-ink">{proposal.name_fr}</td>
        <td className="px-4 py-3 text-sm text-muted">{proposal.brand_name ?? '—'}</td>
        <td className="px-4 py-3 text-sm text-muted">{proposal.category}</td>
        <td className="px-4 py-3 text-xs text-muted">{proposal.proposed_by}</td>
        <td className="px-4 py-3 text-xs text-muted">
          {new Date(proposal.created_at).toLocaleDateString('fr-FR')}
        </td>
        <td className="px-4 py-3">
          {isPending && (
            <div className="flex gap-2">
              <button
                onClick={() => { reset(); onToggle(proposal.id, isExpanded === 'approve' ? null : 'approve'); }}
                className="rounded bg-green-100 px-2 py-1 text-xs font-semibold text-green-700 hover:bg-green-200"
              >
                ✓
              </button>
              <button
                onClick={() => { reset(); onToggle(proposal.id, isExpanded === 'reject' ? null : 'reject'); }}
                className="rounded bg-red-100 px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-200"
              >
                ✗
              </button>
            </div>
          )}
        </td>
      </tr>

      {isExpanded === 'approve' && (
        <tr>
          <td
            colSpan={6}
            className="border-l-4 border-green-400 bg-green-50 px-4 py-3"
          >
            <p className="mb-2 text-xs font-semibold text-green-700">
              ↳ Approuver — lier à un produit existant ou créer nouveau
            </p>
            <div className="mb-3 flex w-fit overflow-hidden rounded-md border border-line">
              <button
                onClick={() => setApproveMode('link')}
                className={cn('px-3 py-1.5 text-xs', approveMode === 'link' ? 'bg-primary text-white' : 'bg-white text-muted')}
              >
                Lier existant
              </button>
              <button
                onClick={() => setApproveMode('create')}
                className={cn('px-3 py-1.5 text-xs', approveMode === 'create' ? 'bg-primary text-white' : 'bg-white text-muted')}
              >
                Créer nouveau
              </button>
            </div>

            {approveMode === 'link' ? (
              <div className="flex items-start gap-3">
                <div className="relative max-w-sm flex-1">
                  <input
                    type="text"
                    value={selectedRef ? selectedRef.name_fr : searchQuery}
                    onChange={(e) => { setSelectedRef(null); setSearchQuery(e.target.value); }}
                    placeholder="Rechercher un produit canonique…"
                    className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
                  />
                  {isSearching && (
                    <span className="absolute right-3 top-2.5 text-xs text-muted">…</span>
                  )}
                  {searchResults.length > 0 && !selectedRef && (
                    <ul className="absolute z-10 mt-1 w-full rounded-md border border-line bg-card shadow-floating">
                      {searchResults.map((r) => (
                        <li
                          key={r.id}
                          onClick={() => { setSelectedRef(r); setSearchResults([]); setSearchQuery(''); }}
                          className="cursor-pointer px-3 py-2 text-sm hover:bg-soft"
                        >
                          {r.name_fr}
                          {r.variant_fr && <span className="text-muted"> ({r.variant_fr})</span>}
                          <span className="ml-1 text-xs text-muted">— {r.brand_name}</span>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
                <button
                  onClick={handleApprove}
                  disabled={isSubmitting}
                  className="rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50"
                >
                  {isSubmitting ? '…' : 'Confirmer'}
                </button>
              </div>
            ) : (
              <div className="flex flex-wrap items-start gap-3">
                <input
                  type="text"
                  value={newNameFr}
                  onChange={(e) => setNewNameFr(e.target.value)}
                  placeholder="Nom canonique FR *"
                  className="rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
                />
                <select
                  value={newBrandId}
                  onChange={(e) => setNewBrandId(e.target.value)}
                  className="rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
                >
                  <option value="">Marque *</option>
                  {brands.map((b) => <option key={b.id} value={b.id}>{b.canonical_name}</option>)}
                </select>
                <select
                  value={newCategoryId}
                  onChange={(e) => setNewCategoryId(e.target.value)}
                  className="rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
                >
                  <option value="">Catégorie *</option>
                  {categories.map((c) => <option key={c.id} value={c.id}>{c.name_fr}</option>)}
                </select>
                <button
                  onClick={handleApprove}
                  disabled={isSubmitting}
                  className="rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50"
                >
                  {isSubmitting ? '…' : 'Confirmer'}
                </button>
              </div>
            )}
            {error && <p className="mt-2 text-xs text-danger">{error}</p>}
          </td>
        </tr>
      )}

      {isExpanded === 'reject' && (
        <tr>
          <td colSpan={6} className="border-l-4 border-red-400 bg-red-50 px-4 py-3">
            <p className="mb-2 text-xs font-semibold text-red-700">↳ Raison du rejet *</p>
            <div className="flex items-start gap-3">
              <textarea
                value={rejectReason}
                onChange={(e) => setRejectReason(e.target.value)}
                rows={2}
                placeholder="Expliquer le motif du rejet…"
                className="max-w-sm flex-1 rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
              />
              <button
                onClick={handleReject}
                disabled={isSubmitting}
                className="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
              >
                {isSubmitting ? '…' : 'Confirmer le rejet'}
              </button>
            </div>
            {error && <p className="mt-2 text-xs text-danger">{error}</p>}
          </td>
        </tr>
      )}
    </>
  );
}
```

- [ ] **Step 2: Implémenter la page Propositions**

```tsx
// src/app/admin/referentiel/propositions/page.tsx
'use client';
import { useState, useEffect } from 'react';
import { ProposalRow } from '@/components/admin/referentiel/propositions/ProposalRow';
import { useSort } from '@/lib/hooks/useSort';
import { listProposals } from '@/lib/services/admin/proposals.service';
import type { Proposal } from '@/lib/types/admin/referentiel.types';
import { cn } from '@/lib/cn';

type StatusFilter = 'pending' | 'approved' | 'rejected';
type ExpansionMode = 'approve' | 'reject';

const STATUS_LABELS: Record<StatusFilter, string> = {
  pending: 'En attente',
  approved: 'Approuvé',
  rejected: 'Rejeté',
};

export default function PropositionsPage() {
  const [proposals, setProposals] = useState<Proposal[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('pending');
  const [search, setSearch] = useState('');
  const [expandedId, setExpandedId] = useState<string | null>(null);
  const [expandedMode, setExpandedMode] = useState<ExpansionMode | null>(null);

  const filtered = proposals.filter((p) =>
    search ? p.name_fr.toLowerCase().includes(search.toLowerCase()) : true,
  );
  const { sorted, sortKey, sortDir, toggleSort } = useSort(filtered);

  const load = async () => {
    setIsLoading(true);
    setError(null);
    try {
      setProposals(await listProposals(statusFilter));
    } catch {
      setError('Impossible de charger les propositions.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => { setExpandedId(null); setExpandedMode(null); void load(); }, [statusFilter]);

  const handleToggle = (id: string, mode: ExpansionMode | null) => {
    if (mode === null) { setExpandedId(null); setExpandedMode(null); return; }
    setExpandedId(id);
    setExpandedMode(mode);
  };

  const SortTh = ({ col, label }: { col: keyof Proposal; label: string }) => (
    <th
      onClick={() => toggleSort(col)}
      className="cursor-pointer select-none px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted hover:text-ink"
    >
      {label}
      {sortKey === col && <span className="ml-1">{sortDir === 'asc' ? '↑' : '↓'}</span>}
    </th>
  );

  return (
    <div>
      <h1 className="mb-5 text-h1 font-black">Propositions</h1>
      <div className="mb-4 flex flex-wrap items-center gap-4">
        <div className="flex gap-2">
          {(['pending', 'approved', 'rejected'] as StatusFilter[]).map((s) => (
            <button
              key={s}
              onClick={() => setStatusFilter(s)}
              className={cn(
                'rounded-full px-3 py-1 text-xs font-semibold transition-colors',
                statusFilter === s
                  ? 'bg-primary text-white'
                  : 'bg-soft text-muted hover:bg-line',
              )}
            >
              {STATUS_LABELS[s]}
            </button>
          ))}
        </div>
        <input
          type="text"
          placeholder="Rechercher par nom…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        />
      </div>
      {error && (
        <div className="mb-4 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          {error}
        </div>
      )}
      <div className="rounded-xl border border-line bg-card overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="border-b border-line bg-soft">
              <tr>
                <SortTh col="name_fr" label="Nom proposé" />
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted">Marque</th>
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted">Catégorie</th>
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted">Proposé par</th>
                <SortTh col="created_at" label="Date" />
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {isLoading ? (
                Array.from({ length: 5 }).map((_, i) => (
                  <tr key={i}>
                    {Array.from({ length: 6 }).map((_, j) => (
                      <td key={j} className="px-4 py-3">
                        <div className="h-4 w-3/4 animate-pulse rounded bg-soft" />
                      </td>
                    ))}
                  </tr>
                ))
              ) : sorted.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-4 py-12 text-center text-sm text-muted">
                    Aucune proposition trouvée.
                  </td>
                </tr>
              ) : (
                sorted.map((p) => (
                  <ProposalRow
                    key={p.id}
                    proposal={p}
                    isExpanded={expandedId === p.id ? expandedMode : null}
                    onToggle={handleToggle}
                    onProcessed={() => void load()}
                  />
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
```

- [ ] **Step 3: Lint complet**

```bash
cd apps/frontend && npm run lint
```

Résultat attendu : 0 erreur

- [ ] **Step 4: Tests unitaires**

```bash
cd apps/frontend && npx vitest run
```

Résultat attendu : tous les tests existants PASS (au minimum les 7 tests d'auth + les 5 tests useSort)

- [ ] **Step 5: Commit final**

```bash
git add src/components/admin/referentiel/propositions/ src/app/admin/referentiel/propositions/
git commit -m "feat(admin/referentiel): page Propositions avec approve/reject inline"
```

---

## Task 13 — Build final et vérification

- [ ] **Step 1: Build complet**

```bash
cd apps/frontend && npm run build
```

Résultat attendu : build réussi, aucune erreur TypeScript

- [ ] **Step 2: Vérification manuelle des routes**

Démarrer le serveur de dev :
```bash
cd apps/frontend && npm run dev
```

Vérifier manuellement (backend doit tourner sur port 8000) :
1. `/admin/login` → page de connexion accessible
2. Connexion admin → redirect vers `/admin/dashboard`
3. Sidebar → "Référentiel produits" est visible mais ne montre pas les sous-items sur `/admin/dashboard`
4. Cliquer "Référentiel produits" → navigue vers `/admin/referentiel/produits`, les 4 sous-items apparaissent dans la sidebar
5. `/admin/referentiel/categories` → tableau avec skeleton au chargement, puis données (ou liste vide)
6. Cliquer "+ Nouvelle catégorie" → drawer slide-over depuis la droite
7. Remplir le formulaire → enregistrer → catégorie apparaît dans le tableau
8. Cliquer "✏ Modifier" → drawer pre-rempli avec toggle Actif
9. Cliquer "🗑 Supprimer" → dialog de confirmation
10. `/admin/referentiel/marques` → même flux, avec champ aliases (tag input)
11. `/admin/referentiel/produits` → filtres recherche + dropdowns brand/category/status fonctionnels
12. `/admin/referentiel/propositions` → pills statut, cliquer ✓ → expansion inline avec toggle Lier/Créer

- [ ] **Step 3: Commit de clôture**

```bash
git add -A
git commit -m "feat(admin/referentiel): section référentiel produits complète (catégories, marques, produits, propositions)"
```
