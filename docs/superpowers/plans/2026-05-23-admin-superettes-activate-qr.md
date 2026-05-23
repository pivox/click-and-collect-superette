# Admin Supérettes — Activate/Deactivate + QR Code — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire the four missing backend endpoints for superettes (activate, deactivate, get QR code, regenerate QR code) into the admin frontend with a toggle switch in the list and a QR section in the edit drawer.

**Architecture:** Service layer receives 4 new functions mirroring backend endpoints. The `SuperettesPage` table gains a toggle switch in the status column (optimistic update) and a "QR" button opening the existing `StoreDrawer`. `StoreDrawer` gains an `isActive` checkbox and a QR section at the bottom using `react-qr-code` for client-side SVG rendering.

**Tech Stack:** Next.js 14, React, TypeScript, axios (`apiClient`), Vitest + happy-dom for tests, `react-qr-code` (new dependency) for client-side QR generation.

---

## File Map

| File | Action | Responsibility |
|---|---|---|
| `apps/frontend/package.json` | Modify | Add `react-qr-code` dependency |
| `src/lib/types/admin/stores.types.ts` | Modify | Add `StoreQrCode` interface |
| `src/lib/services/admin/stores.service.ts` | Modify | Add `activateStore`, `deactivateStore`, `getStoreQrCode`, `regenerateStoreQrCode` |
| `src/tests/admin.stores.service.test.ts` | Create | Unit tests for the 4 new service functions |
| `src/app/admin/superettes/page.tsx` | Modify | Toggle switch in status column + "QR" button in actions |
| `src/components/admin/superettes/StoreDrawer.tsx` | Modify | `isActive` checkbox + QR section with confirm dialog |

---

## Task 1 — Install react-qr-code and add StoreQrCode type

**Files:**
- Modify: `apps/frontend/package.json`
- Modify: `src/lib/types/admin/stores.types.ts`

- [ ] **Step 1: Install react-qr-code**

```bash
cd apps/frontend && npm install react-qr-code
```

Expected: package added to `dependencies` in `package.json`.

- [ ] **Step 2: Add StoreQrCode interface to stores.types.ts**

In `src/lib/types/admin/stores.types.ts`, append after the last existing export:

```typescript
// Matches AdminStoreQrOutput from the backend
export interface StoreQrCode {
  store_id: string;
  store_name: string;
  slug: string;
  qr_code_token: string;
  target_url: string; // relative path: /api/stores/by-qr/{token}
}
```

- [ ] **Step 3: Verify TypeScript compiles**

```bash
cd apps/frontend && npx tsc --noEmit
```

Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add apps/frontend/package.json apps/frontend/package-lock.json src/lib/types/admin/stores.types.ts
git commit -m "feat(admin): install react-qr-code + add StoreQrCode type"
```

---

## Task 2 — Add service functions (TDD)

**Files:**
- Create: `src/tests/admin.stores.service.test.ts`
- Modify: `src/lib/services/admin/stores.service.ts`

- [ ] **Step 1: Write the failing tests**

Create `apps/frontend/src/tests/admin.stores.service.test.ts`:

```typescript
import { describe, it, expect, vi, beforeEach } from 'vitest';
import {
  activateStore,
  deactivateStore,
  getStoreQrCode,
  regenerateStoreQrCode,
} from '@/lib/services/admin/stores.service';
import { apiClient } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  apiClient: {
    patch: vi.fn(),
    get: vi.fn(),
    post: vi.fn(),
  },
}));

const mockPatch = vi.mocked(apiClient.patch);
const mockGet = vi.mocked(apiClient.get);
const mockPost = vi.mocked(apiClient.post);

const STORE_ID = 'store-uuid-1234';

const QR_RESPONSE = {
  store_id: STORE_ID,
  store_name: 'Ma Supérette',
  slug: 'ma-superette',
  qr_code_token: 'tok_abc123',
  target_url: '/api/stores/by-qr/tok_abc123',
};

beforeEach(() => {
  vi.clearAllMocks();
});

describe('activateStore', () => {
  it('sends PATCH to activate endpoint and returns void', async () => {
    mockPatch.mockResolvedValue({ data: undefined });
    await activateStore(STORE_ID);
    expect(mockPatch).toHaveBeenCalledWith(
      `/api/admin/stores/${STORE_ID}/activate`,
      {},
    );
  });
});

describe('deactivateStore', () => {
  it('sends PATCH to deactivate endpoint and returns void', async () => {
    mockPatch.mockResolvedValue({ data: undefined });
    await deactivateStore(STORE_ID);
    expect(mockPatch).toHaveBeenCalledWith(
      `/api/admin/stores/${STORE_ID}/deactivate`,
      {},
    );
  });
});

describe('getStoreQrCode', () => {
  it('sends GET to qr-code endpoint and returns StoreQrCode', async () => {
    mockGet.mockResolvedValue({ data: QR_RESPONSE });
    const result = await getStoreQrCode(STORE_ID);
    expect(mockGet).toHaveBeenCalledWith(`/api/admin/stores/${STORE_ID}/qr-code`);
    expect(result).toEqual(QR_RESPONSE);
  });
});

describe('regenerateStoreQrCode', () => {
  it('sends POST to regenerate-qr endpoint and returns updated StoreQrCode', async () => {
    const newQr = { ...QR_RESPONSE, qr_code_token: 'tok_new456' };
    mockPost.mockResolvedValue({ data: newQr });
    const result = await regenerateStoreQrCode(STORE_ID);
    expect(mockPost).toHaveBeenCalledWith(
      `/api/admin/stores/${STORE_ID}/regenerate-qr`,
      {},
    );
    expect(result.qr_code_token).toBe('tok_new456');
  });
});
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
cd apps/frontend && npx vitest run src/tests/admin.stores.service.test.ts
```

Expected: 4 failing tests — `activateStore is not a function` (or similar).

- [ ] **Step 3: Add the 4 functions to stores.service.ts**

In `apps/frontend/src/lib/services/admin/stores.service.ts`, append after the existing `archiveStore` function:

```typescript
export async function activateStore(id: string): Promise<void> {
  await apiClient.patch(`/api/admin/stores/${id}/activate`, {});
}

export async function deactivateStore(id: string): Promise<void> {
  await apiClient.patch(`/api/admin/stores/${id}/deactivate`, {});
}

export async function getStoreQrCode(id: string): Promise<StoreQrCode> {
  const { data } = await apiClient.get<StoreQrCode>(`/api/admin/stores/${id}/qr-code`);
  return data;
}

export async function regenerateStoreQrCode(id: string): Promise<StoreQrCode> {
  const { data } = await apiClient.post<StoreQrCode>(
    `/api/admin/stores/${id}/regenerate-qr`,
    {},
  );
  return data;
}
```

Also add `StoreQrCode` to the import from types at the top of the file:

```typescript
import type {
  Store,
  StoreListResponse,
  StoreFilters,
  CreateStorePayload,
  UpdateStorePayload,
  StoreQrCode,
} from '@/lib/types/admin/stores.types';
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
cd apps/frontend && npx vitest run src/tests/admin.stores.service.test.ts
```

Expected: 4 passing tests.

- [ ] **Step 5: Commit**

```bash
git add apps/frontend/src/tests/admin.stores.service.test.ts apps/frontend/src/lib/services/admin/stores.service.ts apps/frontend/src/lib/types/admin/stores.types.ts
git commit -m "feat(admin): add activateStore, deactivateStore, getStoreQrCode, regenerateStoreQrCode services"
```

---

## Task 3 — SuperettesPage: toggle switch + QR button

**Files:**
- Modify: `src/app/admin/superettes/page.tsx`

The page already imports `listStores` and `archiveStore`. Add the new imports and replace the status column render and actions column.

- [ ] **Step 1: Add imports at the top of the page file**

In `apps/frontend/src/app/admin/superettes/page.tsx`, add `activateStore` and `deactivateStore` to the existing service import:

```typescript
import {
  listStores,
  archiveStore,
  activateStore,
  deactivateStore,
} from '@/lib/services/admin/stores.service';
```

- [ ] **Step 2: Add handleToggleActive function inside the component**

After the existing `handleArchive` function, add:

```typescript
const handleToggleActive = async (row: Store) => {
  const prev = [...stores];
  setStores((current) =>
    current.map((s) => (s.id === row.id ? { ...s, is_active: !s.is_active } : s)),
  );
  try {
    if (row.is_active) {
      await deactivateStore(row.id);
    } else {
      await activateStore(row.id);
    }
  } catch {
    setStores(prev);
    setError('Impossible de modifier le statut de cette supérette.');
  }
};
```

- [ ] **Step 3: Replace the status column render**

Find the `is_active` column definition:

```typescript
{
  key: 'is_active',
  label: 'Statut',
  sortable: true,
  render: (row) => (
    <span
      className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
        row.archived_at
          ? 'bg-gray-100 text-gray-500'
          : row.is_active
            ? 'bg-green-100 text-green-700'
            : 'bg-status-cancel-bg text-status-cancel'
      }`}
    >
      {row.archived_at ? 'Archivée' : row.is_active ? 'Active' : 'Inactive'}
    </span>
  ),
},
```

Replace it with:

```typescript
{
  key: 'is_active',
  label: 'Statut',
  sortable: true,
  render: (row) =>
    row.archived_at ? (
      <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500">
        Archivée
      </span>
    ) : (
      <button
        type="button"
        onClick={() => void handleToggleActive(row)}
        aria-label={row.is_active ? 'Désactiver la supérette' : 'Activer la supérette'}
        className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 ${
          row.is_active ? 'bg-green-500' : 'bg-gray-300'
        }`}
      >
        <span
          className={`inline-block h-3.5 w-3.5 rounded-full bg-white shadow transition-transform ${
            row.is_active ? 'translate-x-4' : 'translate-x-0.5'
          }`}
        />
      </button>
    ),
},
```

- [ ] **Step 4: Add QR button to the actions column**

Find the actions column render. Add the "QR" button before the "Modifier" button:

```typescript
{
  key: 'actions',
  label: '',
  render: (row) => (
    <div className="flex justify-end gap-2">
      <button
        onClick={() => { setEditTarget(row); setDrawerOpen(true); }}
        className="text-xs text-muted hover:text-ink"
      >
        QR
      </button>
      <button
        onClick={() => { setEditTarget(row); setDrawerOpen(true); }}
        className="text-xs text-muted hover:text-ink"
      >
        ✏ Modifier
      </button>
      {!row.archived_at && (
        <button
          onClick={() => setArchiveTarget(row)}
          className="text-xs text-muted hover:text-danger"
        >
          ⊘ Archiver
        </button>
      )}
    </div>
  ),
},
```

> Note: both the "QR" and "Modifier" buttons open the same drawer. The QR section is visible by scrolling to the bottom of the drawer. They are intentionally identical in behavior (Approach A from the design spec).

- [ ] **Step 5: Run linter to catch issues**

```bash
cd apps/frontend && npm run lint
```

Expected: no errors.

- [ ] **Step 6: Commit**

```bash
git add apps/frontend/src/app/admin/superettes/page.tsx
git commit -m "feat(admin): toggle switch activate/deactivate + QR button dans la liste supérettes"
```

---

## Task 4 — StoreDrawer: isActive checkbox

**Files:**
- Modify: `src/components/admin/superettes/StoreDrawer.tsx`

- [ ] **Step 1: Add isActive state variable**

In `StoreDrawer.tsx`, add `isActive` state after the existing `coverUrl` state:

```typescript
const [isActive, setIsActive] = useState(true);
```

- [ ] **Step 2: Initialize isActive from store prop**

In the `useEffect` that resets form state when `store` or `open` changes, add `isActive` initialization. Find the block that sets `setName`, `setOwnerId`, etc., and add:

```typescript
if (store) {
  setName(store.name);
  setOwnerId(store.owner?.id ?? '');
  setAddress(store.address ?? '');
  setCity(store.city ?? '');
  setPhone(store.phone ?? '');
  setLogoUrl(store.logo_url ?? '');
  setCoverUrl(store.cover_url ?? '');
  setIsActive(store.is_active);    // add this line
} else {
  setName('');
  setOwnerId('');
  setAddress('');
  setCity('');
  setPhone('');
  setLogoUrl('');
  setCoverUrl('');
  setIsActive(true);               // add this line — new stores default to active
}
```

- [ ] **Step 3: Include isActive in the update payload**

In the `handleSubmit` function, inside the `if (store)` branch, add `isActive` to the `updateStore` call:

```typescript
await updateStore(store.id, {
  name: name.trim(),
  address: address.trim() || undefined,
  city: city.trim() || undefined,
  phone: phone.trim() || undefined,
  ownerId: ownerId || undefined,
  logoUrl: logoUrl.trim() !== '' ? logoUrl.trim() : null,
  coverUrl: coverUrl.trim() !== '' ? coverUrl.trim() : null,
  isActive,                        // add this line
});
```

- [ ] **Step 4: Add isActive checkbox to the form JSX**

In the JSX, inside the `{store && (...)}` block (after `coverUrl` field), add the checkbox before the closing `</>`:

```tsx
{store && (
  <>
    <div>
      <label className="mb-1 block text-sm font-semibold">
        URL logo <span className="font-normal text-muted">(max 2048 car.)</span>
      </label>
      <input type="url" value={logoUrl} onChange={(e) => setLogoUrl(e.target.value)} maxLength={2048} placeholder="https://…" className={inputClass} />
    </div>
    <div>
      <label className="mb-1 block text-sm font-semibold">
        URL cover <span className="font-normal text-muted">(max 2048 car.)</span>
      </label>
      <input type="url" value={coverUrl} onChange={(e) => setCoverUrl(e.target.value)} maxLength={2048} placeholder="https://…" className={inputClass} />
    </div>
    <div className="flex items-center gap-3">
      <input
        type="checkbox"
        id="store-is-active"
        checked={isActive}
        onChange={(e) => setIsActive(e.target.checked)}
        disabled={!!store.archived_at}
        className="h-4 w-4 rounded border-line accent-primary"
      />
      <label htmlFor="store-is-active" className="text-sm font-semibold">
        Supérette active
        {store.archived_at && (
          <span className="ml-2 font-normal text-muted">(archivée — non modifiable)</span>
        )}
      </label>
    </div>
  </>
)}
```

- [ ] **Step 5: Run linter**

```bash
cd apps/frontend && npm run lint
```

Expected: no errors.

- [ ] **Step 6: Commit**

```bash
git add apps/frontend/src/components/admin/superettes/StoreDrawer.tsx
git commit -m "feat(admin): checkbox isActive dans le drawer supérette"
```

---

## Task 5 — StoreDrawer: QR code section

**Files:**
- Modify: `src/components/admin/superettes/StoreDrawer.tsx`

- [ ] **Step 1: Add imports**

At the top of `StoreDrawer.tsx`, add:

```typescript
import QRCode from 'react-qr-code';
import { AdminConfirmDialog } from '@/components/admin/ui/AdminConfirmDialog';
import { getStoreQrCode, regenerateStoreQrCode } from '@/lib/services/admin/stores.service';
import type { StoreQrCode } from '@/lib/types/admin/stores.types';
```

- [ ] **Step 2: Add QR state variables**

After the existing state declarations, add:

```typescript
const [qrData, setQrData] = useState<StoreQrCode | null>(null);
const [isLoadingQr, setIsLoadingQr] = useState(false);
const [qrError, setQrError] = useState<string | null>(null);
const [isRegenerateOpen, setIsRegenerateOpen] = useState(false);
const [isRegenerating, setIsRegenerating] = useState(false);
```

- [ ] **Step 3: Add useEffect to load QR data**

After the existing `useEffect` blocks, add:

```typescript
useEffect(() => {
  if (!open || !store) {
    setQrData(null);
    setQrError(null);
    return;
  }
  setIsLoadingQr(true);
  setQrError(null);
  void getStoreQrCode(store.id)
    .then(setQrData)
    .catch(() => setQrError('Impossible de charger le QR code.'))
    .finally(() => setIsLoadingQr(false));
}, [open, store?.id]);
```

- [ ] **Step 4: Add handleRegenerateQr function**

After the `handleSubmit` function, add:

```typescript
const handleRegenerateQr = async () => {
  if (!store) return;
  setIsRegenerating(true);
  setQrError(null);
  try {
    const newQr = await regenerateStoreQrCode(store.id);
    setQrData(newQr);
    setIsRegenerateOpen(false);
  } catch {
    setQrError('Impossible de régénérer le QR code.');
  } finally {
    setIsRegenerating(false);
  }
};
```

- [ ] **Step 5: Add shareUrl helper**

After `const inputClass = ...`, add:

```typescript
const shareUrl = qrData
  ? `${process.env.NEXT_PUBLIC_APP_URL ?? ''}${qrData.target_url}`
  : '';
```

- [ ] **Step 6: Add QR section JSX at the bottom of the drawer form**

Inside `<AdminDrawer ...>`, after the closing `</div>` of the main `<div className="space-y-4">`, add the QR section. The complete drawer children should look like:

```tsx
<AdminDrawer ...>
  <div className="space-y-4">
    {/* existing form fields */}
  </div>

  {store && (
    <div className="mt-6 border-t border-line pt-5">
      <h3 className="mb-3 text-sm font-semibold text-ink">QR code de la supérette</h3>

      {isLoadingQr && (
        <div className="flex h-24 items-center justify-center text-sm text-muted">
          Chargement…
        </div>
      )}

      {qrError && (
        <div className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
          {qrError}
        </div>
      )}

      {!isLoadingQr && qrData && (
        <div className="flex flex-col items-center gap-4">
          <div className="rounded-lg border border-line bg-white p-3">
            <QRCode value={shareUrl || qrData.qr_code_token} size={180} />
          </div>

          <div className="w-full space-y-2">
            <div>
              <span className="mb-1 block text-xs font-semibold text-muted uppercase tracking-wide">Lien de partage</span>
              <div className="flex items-center gap-2">
                <span className="flex-1 truncate rounded bg-surface px-2 py-1 text-xs text-ink">
                  {shareUrl || qrData.target_url}
                </span>
                <button
                  type="button"
                  onClick={() => void navigator.clipboard.writeText(shareUrl || qrData.target_url)}
                  className="shrink-0 rounded px-2 py-1 text-xs text-muted hover:text-ink"
                >
                  Copier
                </button>
              </div>
            </div>

            <div>
              <span className="mb-1 block text-xs font-semibold text-muted uppercase tracking-wide">Token</span>
              <div className="flex items-center gap-2">
                <span className="flex-1 truncate rounded bg-surface px-2 py-1 font-mono text-xs text-ink">
                  {qrData.qr_code_token}
                </span>
                <button
                  type="button"
                  onClick={() => void navigator.clipboard.writeText(qrData.qr_code_token)}
                  className="shrink-0 rounded px-2 py-1 text-xs text-muted hover:text-ink"
                >
                  Copier
                </button>
              </div>
            </div>
          </div>

          <button
            type="button"
            onClick={() => setIsRegenerateOpen(true)}
            className="text-sm text-muted underline hover:text-danger"
          >
            Régénérer le QR
          </button>
        </div>
      )}
    </div>
  )}

  <AdminConfirmDialog
    open={isRegenerateOpen}
    onClose={() => setIsRegenerateOpen(false)}
    onConfirm={() => void handleRegenerateQr()}
    title="Régénérer le QR ?"
    message="L'ancien QR imprimé ne fonctionnera plus. Cette action est irréversible."
    confirmLabel={isRegenerating ? 'Régénération…' : 'Régénérer'}
    variant="warning"
  />
</AdminDrawer>
```

> Note: `bg-surface` may need to be `bg-gray-50` depending on the Tailwind config. Check `tailwind.config.ts` — if `surface` is not defined, use `bg-gray-50`.

- [ ] **Step 7: Check if bg-surface is defined in Tailwind config**

```bash
cd apps/frontend && grep -r "surface" tailwind.config.ts 2>/dev/null || grep -r "surface" tailwind.config.js 2>/dev/null
```

If `surface` is not defined, replace `bg-surface` with `bg-gray-50` in the QR section JSX above.

- [ ] **Step 8: Run linter**

```bash
cd apps/frontend && npm run lint
```

Expected: no errors.

- [ ] **Step 9: Run full test suite**

```bash
cd apps/frontend && npx vitest run
```

Expected: all tests pass including the 4 new service tests.

- [ ] **Step 10: Commit**

```bash
git add apps/frontend/src/components/admin/superettes/StoreDrawer.tsx
git commit -m "feat(admin): section QR code dans le drawer supérette — affichage, lien, token, régénération"
```

---

## Task 6 — Final verification

- [ ] **Step 1: Run the dev server**

```bash
cd apps/frontend && npm run dev
```

- [ ] **Step 2: Manual checks**

Navigate to `http://localhost:3000/admin/superettes` and verify:

1. **Toggle switch** — clicking the toggle on an active supérette calls `PATCH /api/admin/stores/{id}/deactivate` (check Network tab). The pill turns gray immediately (optimistic). On success it stays gray.
2. **Toggle revert** — if the API returns an error (temporarily break the endpoint), the pill reverts to its previous color.
3. **QR button** — clicking "QR" opens the drawer in edit mode. Scrolling to the bottom shows the QR section with a spinner, then the QR image, the share link, and the token.
4. **Copy buttons** — clicking "Copier" next to the link writes the URL to clipboard.
5. **Regenerate** — clicking "Régénérer le QR" opens a confirm dialog. Confirming sends `POST /api/admin/stores/{id}/regenerate-qr` and updates the displayed QR.
6. **isActive checkbox** — opening the drawer for an active store shows the checkbox checked. Unchecking and saving changes `is_active` to false.
7. **Archived store** — the toggle is replaced by the static "Archivée" badge. The `isActive` checkbox in the drawer is disabled.

- [ ] **Step 3: Run TypeScript check**

```bash
cd apps/frontend && npx tsc --noEmit
```

Expected: no errors.

- [ ] **Step 4: Final commit if any cleanup needed**

```bash
git add -p
git commit -m "fix(admin): ajustements post-vérification supérettes activate/qr"
```

---

## Environment Variable Note

The QR share URL is built as `process.env.NEXT_PUBLIC_APP_URL + qrData.target_url` (e.g. `https://api.kadhia.tn/api/stores/by-qr/{token}`). If `NEXT_PUBLIC_APP_URL` is not set in `.env.local`, the displayed URL will be relative (e.g. `/api/stores/by-qr/{token}`) but the QR will still be generated. Define the variable before deploying to production.
