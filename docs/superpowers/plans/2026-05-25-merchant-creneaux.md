# Merchant Créneaux Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implémenter la page `/merchant/creneaux` permettant au marchand de gérer ses règles récurrentes, ses créneaux ponctuels et ses fermetures exceptionnelles.

**Architecture:** Vue principale avec défilement horizontal par jour (DayStrip), liste de créneaux filtrés côté client, sections accordéon pour les règles récurrentes et les fermetures, bannière de génération post-création de règle. Trois services indépendants consomment les endpoints merchant déjà livrés côté backend.

**Tech Stack:** Next.js 14, React, TypeScript, Vitest + React Testing Library, axios (apiClient), Tailwind CSS, lucide-react.

---

## Fichiers à créer / modifier

```
CREATE  src/lib/types/merchant-slots.types.ts
CREATE  src/lib/services/merchant-slot-rules.service.ts
CREATE  src/lib/services/merchant-slots.service.ts
CREATE  src/lib/services/merchant-closures.service.ts
CREATE  src/components/merchant/creneaux/DayStrip.tsx
CREATE  src/components/merchant/creneaux/SlotCard.tsx
CREATE  src/components/merchant/creneaux/SlotCreateModal.tsx
CREATE  src/components/merchant/creneaux/RuleAccordion.tsx
CREATE  src/components/merchant/creneaux/RuleForm.tsx
CREATE  src/components/merchant/creneaux/GenerateBanner.tsx
CREATE  src/components/merchant/creneaux/ClosureAccordion.tsx
CREATE  src/components/merchant/creneaux/ClosureForm.tsx
CREATE  src/app/merchant/creneaux/page.tsx
CREATE  src/tests/merchant.slot-rules.service.test.ts
CREATE  src/tests/merchant.slots.service.test.ts
CREATE  src/tests/merchant.closures.service.test.ts
CREATE  src/tests/merchant.creneaux.test.tsx
MODIFY  src/components/merchant/MerchantShell.tsx  (Créneaux: DISABLED_NAV → ACTIVE_NAV)
```

---

## Task 1 : Types frontend

**Files:**
- Create: `src/lib/types/merchant-slots.types.ts`

- [ ] **Step 1 : Créer le fichier de types**

```typescript
// src/lib/types/merchant-slots.types.ts

export interface MerchantPickupSlotRule {
  id: string;
  weekday: number; // 1 = lundi, 7 = dimanche
  start_time: string; // "HH:MM"
  end_time: string; // "HH:MM"
  capacity: number;
  is_active: boolean;
}

export interface MerchantPickupSlotRuleCollection {
  total: number;
  items: MerchantPickupSlotRule[];
}

export interface MerchantPickupSlot {
  id: string;
  starts_at: string; // ISO 8601
  ends_at: string;
  capacity: number;
  booked_count: number;
  is_active: boolean;
}

export interface MerchantExceptionalClosure {
  id: string;
  starts_at: string;
  ends_at: string;
  reason: string | null;
  is_active: boolean;
}

export interface MerchantExceptionalClosureCollection {
  total: number;
  items: MerchantExceptionalClosure[];
}

export interface CreateSlotRulePayload {
  weekday: number;
  start_time: string;
  end_time: string;
  capacity: number;
}

export interface PatchSlotRulePayload {
  weekday?: number;
  start_time?: string;
  end_time?: string;
  capacity?: number;
  is_active?: boolean;
}

export interface CreateSlotPayload {
  starts_at: string;
  ends_at: string;
  capacity: number;
}

export interface PatchSlotPayload {
  capacity?: number;
  is_active?: boolean;
}

export interface CreateClosurePayload {
  starts_at: string;
  ends_at: string;
  reason?: string;
}

export interface PatchClosurePayload {
  starts_at?: string;
  ends_at?: string;
  reason?: string | null;
  is_active?: boolean;
}

export interface GenerateSlotsResult {
  store_id: string;
  generated_count: number;
  skipped_existing_count: number;
  skipped_closure_count: number;
  horizon_start: string;
  horizon_end: string;
}
```

- [ ] **Step 2 : Vérifier la compilation**

```bash
cd apps/frontend && npx tsc --noEmit
```
Attendu : aucune erreur.

- [ ] **Step 3 : Committer**

```bash
git add apps/frontend/src/lib/types/merchant-slots.types.ts
git commit -m "feat(merchant/creneaux): add slot, rule and closure types"
```

---

## Task 2 : Service des règles récurrentes

**Files:**
- Create: `src/lib/services/merchant-slot-rules.service.ts`
- Create: `src/tests/merchant.slot-rules.service.test.ts`

**Contrat API :**
- `GET  /api/merchant/stores/{id}/pickup-slot-rules` → `{ total, items: MerchantPickupSlotRule[] }`
- `POST /api/merchant/stores/{id}/pickup-slot-rules` → 201, retourne `MerchantPickupSlotRule`
- `PATCH /api/merchant/stores/{id}/pickup-slot-rules/{ruleId}` → 200, retourne `MerchantPickupSlotRule`
- `DELETE /api/merchant/stores/{id}/pickup-slot-rules/{ruleId}` → 204, corps vide
- `POST /api/merchant/stores/{id}/pickup-slot-rules/generate` → 200, retourne `GenerateSlotsResult`

- [ ] **Step 1 : Écrire les tests en premier**

```typescript
// src/tests/merchant.slot-rules.service.test.ts
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  listMerchantSlotRules,
  createMerchantSlotRule,
  patchMerchantSlotRule,
  deleteMerchantSlotRule,
  generateMerchantSlots,
} from '@/lib/services/merchant-slot-rules.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

const STORE_ID = '11111111-1111-4111-8111-111111111111';
const RULE_ID = '22222222-2222-4222-8222-222222222222';

const rule = {
  id: RULE_ID,
  weekday: 3,
  start_time: '17:00',
  end_time: '19:00',
  capacity: 6,
  is_active: true,
};

describe('merchant slot rules service', () => {
  beforeEach(() => { vi.clearAllMocks(); });

  it('lists rules for a store', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { total: 1, items: [rule] },
    });

    const result = await listMerchantSlotRules(STORE_ID);

    expect(apiClient.get).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/pickup-slot-rules`,
    );
    expect(result.total).toBe(1);
    expect(result.items[0].weekday).toBe(3);
  });

  it('creates a rule', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: rule });

    const result = await createMerchantSlotRule(STORE_ID, {
      weekday: 3,
      start_time: '17:00',
      end_time: '19:00',
      capacity: 6,
    });

    expect(apiClient.post).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/pickup-slot-rules`,
      { weekday: 3, start_time: '17:00', end_time: '19:00', capacity: 6 },
    );
    expect(result.id).toBe(RULE_ID);
  });

  it('patches a rule', async () => {
    vi.mocked(apiClient.patch).mockResolvedValue({ data: { ...rule, capacity: 8 } });

    const result = await patchMerchantSlotRule(STORE_ID, RULE_ID, { capacity: 8 });

    expect(apiClient.patch).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/pickup-slot-rules/${RULE_ID}`,
      { capacity: 8 },
    );
    expect(result.capacity).toBe(8);
  });

  it('deletes a rule', async () => {
    vi.mocked(apiClient.delete).mockResolvedValue({ data: undefined });

    await deleteMerchantSlotRule(STORE_ID, RULE_ID);

    expect(apiClient.delete).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/pickup-slot-rules/${RULE_ID}`,
    );
  });

  it('generates slots and returns the result', async () => {
    const generated = {
      store_id: STORE_ID,
      generated_count: 12,
      skipped_existing_count: 0,
      skipped_closure_count: 0,
      horizon_start: '2026-05-25',
      horizon_end: '2026-06-22',
    };
    vi.mocked(apiClient.post).mockResolvedValue({ data: generated });

    const result = await generateMerchantSlots(STORE_ID);

    expect(apiClient.post).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/pickup-slot-rules/generate`,
      {},
    );
    expect(result.generated_count).toBe(12);
  });
});
```

- [ ] **Step 2 : Lancer les tests — vérifier qu'ils échouent**

```bash
cd apps/frontend && npm run test:run -- src/tests/merchant.slot-rules.service.test.ts
```
Attendu : FAIL (module not found).

- [ ] **Step 3 : Implémenter le service**

```typescript
// src/lib/services/merchant-slot-rules.service.ts
import { apiClient } from '@/lib/api';
import type {
  CreateSlotRulePayload,
  GenerateSlotsResult,
  MerchantPickupSlotRule,
  MerchantPickupSlotRuleCollection,
  PatchSlotRulePayload,
} from '@/lib/types/merchant-slots.types';

export async function listMerchantSlotRules(
  storeId: string,
): Promise<MerchantPickupSlotRuleCollection> {
  const { data } = await apiClient.get<MerchantPickupSlotRuleCollection>(
    `/api/merchant/stores/${storeId}/pickup-slot-rules`,
  );
  return data;
}

export async function createMerchantSlotRule(
  storeId: string,
  payload: CreateSlotRulePayload,
): Promise<MerchantPickupSlotRule> {
  const { data } = await apiClient.post<MerchantPickupSlotRule>(
    `/api/merchant/stores/${storeId}/pickup-slot-rules`,
    payload,
  );
  return data;
}

export async function patchMerchantSlotRule(
  storeId: string,
  ruleId: string,
  payload: PatchSlotRulePayload,
): Promise<MerchantPickupSlotRule> {
  const { data } = await apiClient.patch<MerchantPickupSlotRule>(
    `/api/merchant/stores/${storeId}/pickup-slot-rules/${ruleId}`,
    payload,
  );
  return data;
}

export async function deleteMerchantSlotRule(
  storeId: string,
  ruleId: string,
): Promise<void> {
  await apiClient.delete(
    `/api/merchant/stores/${storeId}/pickup-slot-rules/${ruleId}`,
  );
}

export async function generateMerchantSlots(
  storeId: string,
): Promise<GenerateSlotsResult> {
  const { data } = await apiClient.post<GenerateSlotsResult>(
    `/api/merchant/stores/${storeId}/pickup-slot-rules/generate`,
    {},
  );
  return data;
}
```

- [ ] **Step 4 : Lancer les tests — vérifier qu'ils passent**

```bash
cd apps/frontend && npm run test:run -- src/tests/merchant.slot-rules.service.test.ts
```
Attendu : 5 tests PASS.

- [ ] **Step 5 : Committer**

```bash
git add apps/frontend/src/lib/services/merchant-slot-rules.service.ts \
        apps/frontend/src/tests/merchant.slot-rules.service.test.ts
git commit -m "feat(merchant/creneaux): slot rules service"
```

---

## Task 3 : Service des créneaux ponctuels

**Files:**
- Create: `src/lib/services/merchant-slots.service.ts`
- Create: `src/tests/merchant.slots.service.test.ts`

**Contrat API :**
- `GET  /api/merchant/stores/{id}/pickup-slots` → `MerchantPickupSlot[]` (tableau direct)
- `POST /api/merchant/stores/{id}/pickup-slots` → 201, corps vide (`output: false`)
- `PATCH /api/merchant/stores/{id}/pickup-slots/{slotId}` → 200, corps vide
- `DELETE /api/merchant/stores/{id}/pickup-slots/{slotId}` → 204, corps vide

- [ ] **Step 1 : Écrire les tests**

```typescript
// src/tests/merchant.slots.service.test.ts
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  listMerchantSlots,
  createMerchantSlot,
  patchMerchantSlot,
  deleteMerchantSlot,
} from '@/lib/services/merchant-slots.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

const STORE_ID = '11111111-1111-4111-8111-111111111111';
const SLOT_ID = '33333333-3333-4333-8333-333333333333';

const slot = {
  id: SLOT_ID,
  starts_at: '2026-05-28T17:00:00+01:00',
  ends_at: '2026-05-28T18:00:00+01:00',
  capacity: 6,
  booked_count: 2,
  is_active: true,
};

describe('merchant slots service', () => {
  beforeEach(() => { vi.clearAllMocks(); });

  it('lists slots for a store', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({ data: [slot] });

    const result = await listMerchantSlots(STORE_ID);

    expect(apiClient.get).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/pickup-slots`,
    );
    expect(result).toHaveLength(1);
    expect(result[0].booked_count).toBe(2);
  });

  it('creates a slot', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: undefined });

    await createMerchantSlot(STORE_ID, {
      starts_at: '2026-05-28T17:00:00+01:00',
      ends_at: '2026-05-28T18:00:00+01:00',
      capacity: 6,
    });

    expect(apiClient.post).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/pickup-slots`,
      {
        starts_at: '2026-05-28T17:00:00+01:00',
        ends_at: '2026-05-28T18:00:00+01:00',
        capacity: 6,
      },
    );
  });

  it('patches a slot capacity', async () => {
    vi.mocked(apiClient.patch).mockResolvedValue({ data: undefined });

    await patchMerchantSlot(STORE_ID, SLOT_ID, { capacity: 10 });

    expect(apiClient.patch).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/pickup-slots/${SLOT_ID}`,
      { capacity: 10 },
    );
  });

  it('deletes a slot', async () => {
    vi.mocked(apiClient.delete).mockResolvedValue({ data: undefined });

    await deleteMerchantSlot(STORE_ID, SLOT_ID);

    expect(apiClient.delete).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/pickup-slots/${SLOT_ID}`,
    );
  });
});
```

- [ ] **Step 2 : Lancer les tests — vérifier qu'ils échouent**

```bash
cd apps/frontend && npm run test:run -- src/tests/merchant.slots.service.test.ts
```
Attendu : FAIL.

- [ ] **Step 3 : Implémenter le service**

```typescript
// src/lib/services/merchant-slots.service.ts
import { apiClient } from '@/lib/api';
import type {
  CreateSlotPayload,
  MerchantPickupSlot,
  PatchSlotPayload,
} from '@/lib/types/merchant-slots.types';

export async function listMerchantSlots(storeId: string): Promise<MerchantPickupSlot[]> {
  const { data } = await apiClient.get<MerchantPickupSlot[]>(
    `/api/merchant/stores/${storeId}/pickup-slots`,
  );
  return data;
}

export async function createMerchantSlot(
  storeId: string,
  payload: CreateSlotPayload,
): Promise<void> {
  await apiClient.post(`/api/merchant/stores/${storeId}/pickup-slots`, payload);
}

export async function patchMerchantSlot(
  storeId: string,
  slotId: string,
  payload: PatchSlotPayload,
): Promise<void> {
  await apiClient.patch(
    `/api/merchant/stores/${storeId}/pickup-slots/${slotId}`,
    payload,
  );
}

export async function deleteMerchantSlot(storeId: string, slotId: string): Promise<void> {
  await apiClient.delete(`/api/merchant/stores/${storeId}/pickup-slots/${slotId}`);
}
```

- [ ] **Step 4 : Lancer les tests — vérifier qu'ils passent**

```bash
cd apps/frontend && npm run test:run -- src/tests/merchant.slots.service.test.ts
```
Attendu : 4 tests PASS.

- [ ] **Step 5 : Committer**

```bash
git add apps/frontend/src/lib/services/merchant-slots.service.ts \
        apps/frontend/src/tests/merchant.slots.service.test.ts
git commit -m "feat(merchant/creneaux): slots service"
```

---

## Task 4 : Service des fermetures exceptionnelles

**Files:**
- Create: `src/lib/services/merchant-closures.service.ts`
- Create: `src/tests/merchant.closures.service.test.ts`

**Contrat API :**
- `GET  /api/merchant/stores/{id}/exceptional-closures` → `{ total, items: MerchantExceptionalClosure[] }`
- `POST /api/merchant/stores/{id}/exceptional-closures` → 201, retourne `MerchantExceptionalClosure`
- `PATCH /api/merchant/stores/{id}/exceptional-closures/{closureId}` → 200, retourne `MerchantExceptionalClosure`
- `DELETE /api/merchant/stores/{id}/exceptional-closures/{closureId}` → 204

- [ ] **Step 1 : Écrire les tests**

```typescript
// src/tests/merchant.closures.service.test.ts
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  listMerchantClosures,
  createMerchantClosure,
  patchMerchantClosure,
  deleteMerchantClosure,
} from '@/lib/services/merchant-closures.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

const STORE_ID = '11111111-1111-4111-8111-111111111111';
const CLOSURE_ID = '44444444-4444-4444-8444-444444444444';

const closure = {
  id: CLOSURE_ID,
  starts_at: '2026-06-01T00:00:00+01:00',
  ends_at: '2026-06-01T23:59:00+01:00',
  reason: 'Aïd el-Fitr',
  is_active: true,
};

describe('merchant closures service', () => {
  beforeEach(() => { vi.clearAllMocks(); });

  it('lists closures for a store', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { total: 1, items: [closure] },
    });

    const result = await listMerchantClosures(STORE_ID);

    expect(apiClient.get).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/exceptional-closures`,
    );
    expect(result.total).toBe(1);
    expect(result.items[0].reason).toBe('Aïd el-Fitr');
  });

  it('creates a closure', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: closure });

    const result = await createMerchantClosure(STORE_ID, {
      starts_at: '2026-06-01T00:00:00+01:00',
      ends_at: '2026-06-01T23:59:00+01:00',
      reason: 'Aïd el-Fitr',
    });

    expect(apiClient.post).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/exceptional-closures`,
      {
        starts_at: '2026-06-01T00:00:00+01:00',
        ends_at: '2026-06-01T23:59:00+01:00',
        reason: 'Aïd el-Fitr',
      },
    );
    expect(result.id).toBe(CLOSURE_ID);
  });

  it('patches a closure reason', async () => {
    vi.mocked(apiClient.patch).mockResolvedValue({
      data: { ...closure, reason: 'Congé annuel' },
    });

    const result = await patchMerchantClosure(STORE_ID, CLOSURE_ID, {
      reason: 'Congé annuel',
    });

    expect(apiClient.patch).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/exceptional-closures/${CLOSURE_ID}`,
      { reason: 'Congé annuel' },
    );
    expect(result.reason).toBe('Congé annuel');
  });

  it('deletes a closure', async () => {
    vi.mocked(apiClient.delete).mockResolvedValue({ data: undefined });

    await deleteMerchantClosure(STORE_ID, CLOSURE_ID);

    expect(apiClient.delete).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/exceptional-closures/${CLOSURE_ID}`,
    );
  });
});
```

- [ ] **Step 2 : Lancer les tests — vérifier qu'ils échouent**

```bash
cd apps/frontend && npm run test:run -- src/tests/merchant.closures.service.test.ts
```
Attendu : FAIL.

- [ ] **Step 3 : Implémenter le service**

```typescript
// src/lib/services/merchant-closures.service.ts
import { apiClient } from '@/lib/api';
import type {
  CreateClosurePayload,
  MerchantExceptionalClosure,
  MerchantExceptionalClosureCollection,
  PatchClosurePayload,
} from '@/lib/types/merchant-slots.types';

export async function listMerchantClosures(
  storeId: string,
): Promise<MerchantExceptionalClosureCollection> {
  const { data } = await apiClient.get<MerchantExceptionalClosureCollection>(
    `/api/merchant/stores/${storeId}/exceptional-closures`,
  );
  return data;
}

export async function createMerchantClosure(
  storeId: string,
  payload: CreateClosurePayload,
): Promise<MerchantExceptionalClosure> {
  const { data } = await apiClient.post<MerchantExceptionalClosure>(
    `/api/merchant/stores/${storeId}/exceptional-closures`,
    payload,
  );
  return data;
}

export async function patchMerchantClosure(
  storeId: string,
  closureId: string,
  payload: PatchClosurePayload,
): Promise<MerchantExceptionalClosure> {
  const { data } = await apiClient.patch<MerchantExceptionalClosure>(
    `/api/merchant/stores/${storeId}/exceptional-closures/${closureId}`,
    payload,
  );
  return data;
}

export async function deleteMerchantClosure(
  storeId: string,
  closureId: string,
): Promise<void> {
  await apiClient.delete(
    `/api/merchant/stores/${storeId}/exceptional-closures/${closureId}`,
  );
}
```

- [ ] **Step 4 : Lancer les tests — vérifier qu'ils passent**

```bash
cd apps/frontend && npm run test:run -- src/tests/merchant.closures.service.test.ts
```
Attendu : 4 tests PASS.

- [ ] **Step 5 : Committer**

```bash
git add apps/frontend/src/lib/services/merchant-closures.service.ts \
        apps/frontend/src/tests/merchant.closures.service.test.ts
git commit -m "feat(merchant/creneaux): closures service"
```

---

## Task 5 : Composant DayStrip

**Files:**
- Create: `src/components/merchant/creneaux/DayStrip.tsx`

- [ ] **Step 1 : Créer le composant**

```tsx
// src/components/merchant/creneaux/DayStrip.tsx
'use client';

import { cn } from '@/lib/cn';
import type { MerchantPickupSlot, MerchantExceptionalClosure } from '@/lib/types/merchant-slots.types';

export interface DayStripProps {
  days: Date[];
  selectedDate: Date;
  slots: MerchantPickupSlot[];
  closures: MerchantExceptionalClosure[];
  onSelectDate: (date: Date) => void;
}

function isSameDay(a: Date, b: Date): boolean {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  );
}

function hasClosure(date: Date, closures: MerchantExceptionalClosure[]): boolean {
  return closures.some((c) => {
    const start = new Date(c.starts_at);
    const end = new Date(c.ends_at);
    return c.is_active && date >= start && date <= end;
  });
}

function slotCountForDay(date: Date, slots: MerchantPickupSlot[]): number {
  return slots.filter(
    (s) => s.is_active && isSameDay(new Date(s.starts_at), date),
  ).length;
}

function formatDayLabel(date: Date): string {
  const weekday = date
    .toLocaleDateString('fr-FR', { weekday: 'short' })
    .replace('.', '');
  const day = date.getDate();
  return `${weekday.charAt(0).toUpperCase()}${weekday.slice(1)} ${day}`;
}

export function DayStrip({
  days,
  selectedDate,
  slots,
  closures,
  onSelectDate,
}: DayStripProps) {
  return (
    <div className="flex gap-2 overflow-x-auto pb-2" role="list" aria-label="Jours">
      {days.map((date) => {
        const isSelected = isSameDay(date, selectedDate);
        const count = slotCountForDay(date, slots);
        const closed = hasClosure(date, closures);

        return (
          <button
            key={date.toISOString()}
            role="listitem"
            type="button"
            onClick={() => onSelectDate(date)}
            aria-pressed={isSelected}
            className={cn(
              'relative flex min-w-[64px] flex-col items-center rounded-lg border px-2 py-2.5 text-sm transition-colors',
              isSelected
                ? 'border-primary bg-[#eff8f1] font-bold text-primary'
                : 'border-line bg-card text-ink hover:bg-soft',
            )}
          >
            <span className="text-xs">{formatDayLabel(date)}</span>
            {count > 0 && (
              <span
                aria-label={`${count} créneau${count > 1 ? 'x' : ''}`}
                className="mt-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary text-[11px] font-black text-white"
              >
                {count}
              </span>
            )}
            {closed && (
              <span
                aria-label="Fermeture exceptionnelle"
                className="absolute right-1 top-1 h-2 w-2 rounded-full bg-danger"
              />
            )}
          </button>
        );
      })}
    </div>
  );
}
```

- [ ] **Step 2 : Vérifier la compilation**

```bash
cd apps/frontend && npx tsc --noEmit
```
Attendu : aucune erreur.

- [ ] **Step 3 : Committer**

```bash
git add apps/frontend/src/components/merchant/creneaux/DayStrip.tsx
git commit -m "feat(merchant/creneaux): DayStrip component"
```

---

## Task 6 : Composant SlotCard

**Files:**
- Create: `src/components/merchant/creneaux/SlotCard.tsx`

- [ ] **Step 1 : Créer le composant**

```tsx
// src/components/merchant/creneaux/SlotCard.tsx
'use client';

import { useState } from 'react';
import { Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { cn } from '@/lib/cn';
import { formatTime } from '@/lib/format';
import type { MerchantPickupSlot } from '@/lib/types/merchant-slots.types';

export interface SlotCardProps {
  slot: MerchantPickupSlot;
  onPatch: (slotId: string, payload: { capacity?: number; is_active?: boolean }) => Promise<void>;
  onDelete: (slotId: string) => Promise<void>;
}

export function SlotCard({ slot, onPatch, onDelete }: SlotCardProps) {
  const [editingCapacity, setEditingCapacity] = useState(false);
  const [capacity, setCapacity] = useState(String(slot.capacity));
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const remaining = slot.capacity - slot.booked_count;
  const isFull = remaining <= 0;

  async function handleSaveCapacity() {
    const val = parseInt(capacity, 10);
    if (!val || val <= 0) return;
    setSaving(true);
    try {
      await onPatch(slot.id, { capacity: val });
      setEditingCapacity(false);
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete() {
    if (slot.booked_count > 0) {
      setDeleteError('Ce créneau a des réservations, impossible de le supprimer.');
      return;
    }
    await onDelete(slot.id);
  }

  return (
    <div
      className={cn(
        'rounded-lg border bg-card p-3 shadow-card',
        !slot.is_active && 'opacity-60',
      )}
    >
      <div className="flex items-start justify-between gap-2">
        <div>
          <p className="text-sm font-bold">
            {formatTime(slot.starts_at)}–{formatTime(slot.ends_at)}
          </p>
          <p className="mt-0.5 text-xs text-muted">
            {slot.booked_count}/{slot.capacity} réservé
            {slot.booked_count > 1 ? 's' : ''}
          </p>
        </div>
        <div className="flex items-center gap-1.5">
          {isFull && (
            <span className="rounded-full bg-danger/10 px-2 py-0.5 text-[11px] font-bold text-danger">
              Complet
            </span>
          )}
          {!slot.is_active && (
            <span className="rounded-full bg-soft px-2 py-0.5 text-[11px] font-bold text-muted">
              Inactif
            </span>
          )}
          <button
            type="button"
            aria-label="Supprimer ce créneau"
            onClick={handleDelete}
            className="rounded p-1 text-muted hover:bg-soft hover:text-danger"
          >
            <Trash2 className="h-3.5 w-3.5" />
          </button>
        </div>
      </div>

      {deleteError && (
        <p role="alert" className="mt-2 text-xs text-danger">
          {deleteError}
        </p>
      )}

      <div className="mt-2 flex items-center gap-2">
        {editingCapacity ? (
          <>
            <input
              type="number"
              min={1}
              value={capacity}
              onChange={(e) => setCapacity(e.target.value)}
              className="w-16 rounded border border-line px-2 py-1 text-sm"
              aria-label="Capacité"
            />
            <Button size="md" onClick={handleSaveCapacity} disabled={saving}>
              {saving ? '…' : 'OK'}
            </Button>
            <Button
              size="md"
              variant="ghost"
              onClick={() => {
                setCapacity(String(slot.capacity));
                setEditingCapacity(false);
              }}
            >
              Annuler
            </Button>
          </>
        ) : (
          <button
            type="button"
            onClick={() => setEditingCapacity(true)}
            className="text-xs text-primary hover:underline"
          >
            Modifier capacité
          </button>
        )}
      </div>
    </div>
  );
}
```

- [ ] **Step 2 : Vérifier la compilation**

```bash
cd apps/frontend && npx tsc --noEmit
```
Attendu : aucune erreur.

- [ ] **Step 3 : Committer**

```bash
git add apps/frontend/src/components/merchant/creneaux/SlotCard.tsx
git commit -m "feat(merchant/creneaux): SlotCard component"
```

---

## Task 7 : Composant SlotCreateModal

**Files:**
- Create: `src/components/merchant/creneaux/SlotCreateModal.tsx`

- [ ] **Step 1 : Créer le composant**

```tsx
// src/components/merchant/creneaux/SlotCreateModal.tsx
'use client';

import { useState } from 'react';
import { X } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import type { CreateSlotPayload } from '@/lib/types/merchant-slots.types';

export interface SlotCreateModalProps {
  initialDate: Date;
  onSubmit: (payload: CreateSlotPayload) => Promise<void>;
  onClose: () => void;
}

function toDatetimeLocal(date: Date, time: string): string {
  const d = new Date(date);
  const [h, m] = time.split(':').map(Number);
  d.setHours(h, m, 0, 0);
  return d.toISOString();
}

export function SlotCreateModal({
  initialDate,
  onSubmit,
  onClose,
}: SlotCreateModalProps) {
  const pad = (n: number) => String(n).padStart(2, '0');
  const defaultDate = `${initialDate.getFullYear()}-${pad(initialDate.getMonth() + 1)}-${pad(initialDate.getDate())}`;

  const [date, setDate] = useState(defaultDate);
  const [startTime, setStartTime] = useState('17:00');
  const [endTime, setEndTime] = useState('18:00');
  const [capacity, setCapacity] = useState('6');
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (startTime >= endTime) {
      setError("L'heure de fin doit être après l'heure de début.");
      return;
    }
    const cap = parseInt(capacity, 10);
    if (!cap || cap <= 0) {
      setError('La capacité doit être un nombre positif.');
      return;
    }
    setError(null);
    setSaving(true);
    try {
      const selectedDate = new Date(date);
      await onSubmit({
        starts_at: toDatetimeLocal(selectedDate, startTime),
        ends_at: toDatetimeLocal(selectedDate, endTime),
        capacity: cap,
      });
      onClose();
    } catch {
      setError("Impossible de créer le créneau. Vérifiez les données et réessayez.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label="Créer un créneau ponctuel"
      className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center"
    >
      <div className="w-full max-w-md rounded-t-2xl bg-card p-5 shadow-xl sm:rounded-2xl">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-h3 font-black">Nouveau créneau</h2>
          <button
            type="button"
            aria-label="Fermer"
            onClick={onClose}
            className="rounded p-1 hover:bg-soft"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-3">
          <div>
            <label className="mb-1 block text-xs font-bold text-muted" htmlFor="slot-date">
              Date
            </label>
            <input
              id="slot-date"
              type="date"
              value={date}
              onChange={(e) => setDate(e.target.value)}
              required
              className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none"
            />
          </div>
          <div className="flex gap-3">
            <div className="flex-1">
              <label className="mb-1 block text-xs font-bold text-muted" htmlFor="slot-start">
                Heure début
              </label>
              <input
                id="slot-start"
                type="time"
                value={startTime}
                onChange={(e) => setStartTime(e.target.value)}
                required
                className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none"
              />
            </div>
            <div className="flex-1">
              <label className="mb-1 block text-xs font-bold text-muted" htmlFor="slot-end">
                Heure fin
              </label>
              <input
                id="slot-end"
                type="time"
                value={endTime}
                onChange={(e) => setEndTime(e.target.value)}
                required
                className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none"
              />
            </div>
          </div>
          <div>
            <label className="mb-1 block text-xs font-bold text-muted" htmlFor="slot-cap">
              Capacité (nb. commandes max)
            </label>
            <input
              id="slot-cap"
              type="number"
              min={1}
              value={capacity}
              onChange={(e) => setCapacity(e.target.value)}
              required
              className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none"
            />
          </div>

          {error && <p role="alert" className="text-xs text-danger">{error}</p>}

          <Button full type="submit" disabled={saving}>
            {saving ? 'Création…' : 'Créer le créneau'}
          </Button>
        </form>
      </div>
    </div>
  );
}
```

- [ ] **Step 2 : Vérifier la compilation**

```bash
cd apps/frontend && npx tsc --noEmit
```
Attendu : aucune erreur.

- [ ] **Step 3 : Committer**

```bash
git add apps/frontend/src/components/merchant/creneaux/SlotCreateModal.tsx
git commit -m "feat(merchant/creneaux): SlotCreateModal component"
```

---

## Task 8 : RuleForm et RuleAccordion

**Files:**
- Create: `src/components/merchant/creneaux/RuleForm.tsx`
- Create: `src/components/merchant/creneaux/RuleAccordion.tsx`

- [ ] **Step 1 : Créer RuleForm**

```tsx
// src/components/merchant/creneaux/RuleForm.tsx
'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import type { CreateSlotRulePayload } from '@/lib/types/merchant-slots.types';

const WEEKDAYS = [
  { value: 1, label: 'Lundi' },
  { value: 2, label: 'Mardi' },
  { value: 3, label: 'Mercredi' },
  { value: 4, label: 'Jeudi' },
  { value: 5, label: 'Vendredi' },
  { value: 6, label: 'Samedi' },
  { value: 7, label: 'Dimanche' },
];

export interface RuleFormProps {
  onSubmit: (payload: CreateSlotRulePayload) => Promise<void>;
  onCancel: () => void;
}

export function RuleForm({ onSubmit, onCancel }: RuleFormProps) {
  const [weekday, setWeekday] = useState('1');
  const [startTime, setStartTime] = useState('17:00');
  const [endTime, setEndTime] = useState('19:00');
  const [capacity, setCapacity] = useState('6');
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (startTime >= endTime) {
      setError("L'heure de fin doit être après l'heure de début.");
      return;
    }
    const cap = parseInt(capacity, 10);
    if (!cap || cap <= 0) {
      setError('La capacité doit être un nombre positif.');
      return;
    }
    setError(null);
    setSaving(true);
    try {
      await onSubmit({
        weekday: parseInt(weekday, 10),
        start_time: startTime,
        end_time: endTime,
        capacity: cap,
      });
    } catch {
      setError("Impossible de créer la règle. Vérifiez les données et réessayez.");
      setSaving(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="mt-3 space-y-3 rounded-lg border border-line bg-soft p-3">
      <div>
        <label className="mb-1 block text-xs font-bold text-muted" htmlFor="rule-weekday">
          Jour de la semaine
        </label>
        <select
          id="rule-weekday"
          value={weekday}
          onChange={(e) => setWeekday(e.target.value)}
          className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none"
        >
          {WEEKDAYS.map((d) => (
            <option key={d.value} value={d.value}>
              {d.label}
            </option>
          ))}
        </select>
      </div>
      <div className="flex gap-3">
        <div className="flex-1">
          <label className="mb-1 block text-xs font-bold text-muted" htmlFor="rule-start">
            Heure début
          </label>
          <input
            id="rule-start"
            type="time"
            value={startTime}
            onChange={(e) => setStartTime(e.target.value)}
            required
            className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none"
          />
        </div>
        <div className="flex-1">
          <label className="mb-1 block text-xs font-bold text-muted" htmlFor="rule-end">
            Heure fin
          </label>
          <input
            id="rule-end"
            type="time"
            value={endTime}
            onChange={(e) => setEndTime(e.target.value)}
            required
            className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none"
          />
        </div>
      </div>
      <div>
        <label className="mb-1 block text-xs font-bold text-muted" htmlFor="rule-cap">
          Capacité (nb. commandes max)
        </label>
        <input
          id="rule-cap"
          type="number"
          min={1}
          value={capacity}
          onChange={(e) => setCapacity(e.target.value)}
          required
          className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none"
        />
      </div>
      {error && <p role="alert" className="text-xs text-danger">{error}</p>}
      <div className="flex gap-2">
        <Button type="submit" disabled={saving}>
          {saving ? 'Création…' : 'Ajouter la règle'}
        </Button>
        <Button type="button" variant="ghost" onClick={onCancel}>
          Annuler
        </Button>
      </div>
    </form>
  );
}
```

- [ ] **Step 2 : Créer RuleAccordion**

```tsx
// src/components/merchant/creneaux/RuleAccordion.tsx
'use client';

import { useState } from 'react';
import { ChevronDown, ChevronUp, Plus, Trash2 } from 'lucide-react';
import { RuleForm } from './RuleForm';
import type { CreateSlotRulePayload, MerchantPickupSlotRule } from '@/lib/types/merchant-slots.types';

const WEEKDAY_LABELS: Record<number, string> = {
  1: 'Lundi', 2: 'Mardi', 3: 'Mercredi', 4: 'Jeudi',
  5: 'Vendredi', 6: 'Samedi', 7: 'Dimanche',
};

export interface RuleAccordionProps {
  rules: MerchantPickupSlotRule[];
  onCreateRule: (payload: CreateSlotRulePayload) => Promise<void>;
  onDeleteRule: (ruleId: string) => Promise<void>;
}

export function RuleAccordion({ rules, onCreateRule, onDeleteRule }: RuleAccordionProps) {
  const [open, setOpen] = useState(rules.length === 0);
  const [showForm, setShowForm] = useState(false);
  const [deletingId, setDeletingId] = useState<string | null>(null);
  const [confirmId, setConfirmId] = useState<string | null>(null);

  async function handleCreate(payload: CreateSlotRulePayload) {
    await onCreateRule(payload);
    setShowForm(false);
  }

  async function handleDelete(ruleId: string) {
    setDeletingId(ruleId);
    try {
      await onDeleteRule(ruleId);
    } finally {
      setDeletingId(null);
      setConfirmId(null);
    }
  }

  return (
    <section className="rounded-lg border border-line bg-card">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="flex w-full items-center justify-between px-4 py-3 text-left"
        aria-expanded={open}
      >
        <span className="font-bold">Règles récurrentes</span>
        {open ? <ChevronUp className="h-4 w-4 text-muted" /> : <ChevronDown className="h-4 w-4 text-muted" />}
      </button>

      {open && (
        <div className="border-t border-line px-4 pb-4 pt-3">
          {rules.length === 0 && !showForm && (
            <p className="mb-3 text-sm text-muted">
              Aucune règle — les règles définissent les créneaux récurrents de votre supérette.
            </p>
          )}

          <ul className="space-y-2">
            {rules.map((rule) => (
              <li key={rule.id} className="flex items-center justify-between rounded-lg bg-soft px-3 py-2 text-sm">
                <span>
                  <strong>{WEEKDAY_LABELS[rule.weekday]}</strong>{' '}
                  {rule.start_time}–{rule.end_time} · capacité {rule.capacity}
                  {!rule.is_active && (
                    <span className="ml-2 text-xs text-muted">(inactive)</span>
                  )}
                </span>
                {confirmId === rule.id ? (
                  <span className="flex items-center gap-2 text-xs">
                    Supprimer ?
                    <button
                      type="button"
                      onClick={() => handleDelete(rule.id)}
                      disabled={deletingId === rule.id}
                      className="font-bold text-danger hover:underline"
                    >
                      {deletingId === rule.id ? '…' : 'Oui'}
                    </button>
                    <button
                      type="button"
                      onClick={() => setConfirmId(null)}
                      className="text-muted hover:underline"
                    >
                      Non
                    </button>
                  </span>
                ) : (
                  <button
                    type="button"
                    aria-label={`Supprimer la règle ${WEEKDAY_LABELS[rule.weekday]} ${rule.start_time}`}
                    onClick={() => setConfirmId(rule.id)}
                    className="rounded p-1 text-muted hover:bg-soft hover:text-danger"
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                  </button>
                )}
              </li>
            ))}
          </ul>

          {showForm ? (
            <RuleForm
              onSubmit={handleCreate}
              onCancel={() => setShowForm(false)}
            />
          ) : (
            <button
              type="button"
              onClick={() => setShowForm(true)}
              className="mt-3 flex items-center gap-1.5 text-sm font-bold text-primary hover:underline"
            >
              <Plus className="h-4 w-4" />
              Nouvelle règle
            </button>
          )}
        </div>
      )}
    </section>
  );
}
```

- [ ] **Step 3 : Vérifier la compilation**

```bash
cd apps/frontend && npx tsc --noEmit
```
Attendu : aucune erreur.

- [ ] **Step 4 : Committer**

```bash
git add apps/frontend/src/components/merchant/creneaux/RuleForm.tsx \
        apps/frontend/src/components/merchant/creneaux/RuleAccordion.tsx
git commit -m "feat(merchant/creneaux): RuleForm and RuleAccordion components"
```

---

## Task 9 : Composant GenerateBanner

**Files:**
- Create: `src/components/merchant/creneaux/GenerateBanner.tsx`

- [ ] **Step 1 : Créer le composant**

```tsx
// src/components/merchant/creneaux/GenerateBanner.tsx
'use client';

import { useState } from 'react';
import { Zap } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import type { GenerateSlotsResult } from '@/lib/types/merchant-slots.types';

export interface GenerateBannerProps {
  onGenerate: () => Promise<GenerateSlotsResult>;
  onDismiss: () => void;
}

export function GenerateBanner({ onGenerate, onDismiss }: GenerateBannerProps) {
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<GenerateSlotsResult | null>(null);
  const [error, setError] = useState<string | null>(null);

  const now = new Date();
  const horizon = new Date(now);
  horizon.setDate(horizon.getDate() + 28);
  const fmt = (d: Date) =>
    d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' });

  async function handleGenerate() {
    setLoading(true);
    setError(null);
    try {
      const data = await onGenerate();
      setResult(data);
    } catch {
      setError('La génération a échoué. Réessayez.');
    } finally {
      setLoading(false);
    }
  }

  if (result) {
    return (
      <div
        role="status"
        className="flex items-center justify-between rounded-lg border border-primary/30 bg-[#eff8f1] px-4 py-3 text-sm"
      >
        <span className="font-bold text-primary">
          {result.generated_count} créneau{result.generated_count > 1 ? 'x' : ''} généré
          {result.generated_count > 1 ? 's' : ''}.
        </span>
        <button
          type="button"
          onClick={onDismiss}
          className="ml-4 text-xs text-muted hover:underline"
        >
          Fermer
        </button>
      </div>
    );
  }

  return (
    <div className="rounded-lg border border-secondary bg-[#fff8ed] px-4 py-3">
      <div className="flex items-start gap-3">
        <Zap className="mt-0.5 h-4 w-4 shrink-0 text-[#a06000]" aria-hidden="true" />
        <div className="flex-1">
          <p className="text-sm font-bold text-ink">
            Règle créée. Générer les créneaux pour les 4 prochaines semaines ?
          </p>
          <p className="mt-0.5 text-xs text-muted">
            Période : du {fmt(now)} au {fmt(horizon)}
          </p>
          {error && <p role="alert" className="mt-1 text-xs text-danger">{error}</p>}
          <div className="mt-3 flex items-center gap-2">
            <Button size="md" onClick={handleGenerate} disabled={loading}>
              {loading ? 'Génération…' : 'Générer'}
            </Button>
            <Button size="md" variant="ghost" onClick={onDismiss} disabled={loading}>
              Plus tard
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
```

- [ ] **Step 2 : Vérifier la compilation**

```bash
cd apps/frontend && npx tsc --noEmit
```
Attendu : aucune erreur.

- [ ] **Step 3 : Committer**

```bash
git add apps/frontend/src/components/merchant/creneaux/GenerateBanner.tsx
git commit -m "feat(merchant/creneaux): GenerateBanner component"
```

---

## Task 10 : ClosureForm et ClosureAccordion

**Files:**
- Create: `src/components/merchant/creneaux/ClosureForm.tsx`
- Create: `src/components/merchant/creneaux/ClosureAccordion.tsx`

- [ ] **Step 1 : Créer ClosureForm**

```tsx
// src/components/merchant/creneaux/ClosureForm.tsx
'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import type { CreateClosurePayload } from '@/lib/types/merchant-slots.types';

export interface ClosureFormProps {
  onSubmit: (payload: CreateClosurePayload) => Promise<void>;
  onCancel: () => void;
}

export function ClosureForm({ onSubmit, onCancel }: ClosureFormProps) {
  const [startsAt, setStartsAt] = useState('');
  const [endsAt, setEndsAt] = useState('');
  const [reason, setReason] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!startsAt || !endsAt) {
      setError('Les dates de début et de fin sont obligatoires.');
      return;
    }
    if (startsAt >= endsAt) {
      setError('La date de fin doit être après la date de début.');
      return;
    }
    setError(null);
    setSaving(true);
    try {
      await onSubmit({
        starts_at: new Date(startsAt).toISOString(),
        ends_at: new Date(endsAt).toISOString(),
        ...(reason.trim() ? { reason: reason.trim() } : {}),
      });
    } catch {
      setError("Impossible de créer la fermeture. Réessayez.");
      setSaving(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="mt-3 space-y-3 rounded-lg border border-line bg-soft p-3">
      <div className="flex gap-3">
        <div className="flex-1">
          <label className="mb-1 block text-xs font-bold text-muted" htmlFor="closure-start">
            Début
          </label>
          <input
            id="closure-start"
            type="datetime-local"
            value={startsAt}
            onChange={(e) => setStartsAt(e.target.value)}
            required
            className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none"
          />
        </div>
        <div className="flex-1">
          <label className="mb-1 block text-xs font-bold text-muted" htmlFor="closure-end">
            Fin
          </label>
          <input
            id="closure-end"
            type="datetime-local"
            value={endsAt}
            onChange={(e) => setEndsAt(e.target.value)}
            required
            className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none"
          />
        </div>
      </div>
      <div>
        <label className="mb-1 block text-xs font-bold text-muted" htmlFor="closure-reason">
          Raison (optionnelle)
        </label>
        <input
          id="closure-reason"
          type="text"
          maxLength={255}
          placeholder="ex. Aïd el-Fitr, congé annuel…"
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          className="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none placeholder:text-muted"
        />
      </div>
      {error && <p role="alert" className="text-xs text-danger">{error}</p>}
      <div className="flex gap-2">
        <Button type="submit" disabled={saving}>
          {saving ? 'Enregistrement…' : 'Ajouter la fermeture'}
        </Button>
        <Button type="button" variant="ghost" onClick={onCancel}>
          Annuler
        </Button>
      </div>
    </form>
  );
}
```

- [ ] **Step 2 : Créer ClosureAccordion**

```tsx
// src/components/merchant/creneaux/ClosureAccordion.tsx
'use client';

import { useState } from 'react';
import { ChevronDown, ChevronUp, Plus, Trash2 } from 'lucide-react';
import { ClosureForm } from './ClosureForm';
import type { CreateClosurePayload, MerchantExceptionalClosure } from '@/lib/types/merchant-slots.types';

function formatClosureRange(closure: MerchantExceptionalClosure): string {
  const fmt = (iso: string) =>
    new Date(iso).toLocaleString('fr-FR', {
      day: 'numeric',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    });
  return `${fmt(closure.starts_at)} → ${fmt(closure.ends_at)}`;
}

export interface ClosureAccordionProps {
  closures: MerchantExceptionalClosure[];
  onCreateClosure: (payload: CreateClosurePayload) => Promise<void>;
  onDeleteClosure: (closureId: string) => Promise<void>;
}

export function ClosureAccordion({
  closures,
  onCreateClosure,
  onDeleteClosure,
}: ClosureAccordionProps) {
  const [open, setOpen] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [deletingId, setDeletingId] = useState<string | null>(null);
  const [confirmId, setConfirmId] = useState<string | null>(null);

  async function handleCreate(payload: CreateClosurePayload) {
    await onCreateClosure(payload);
    setShowForm(false);
  }

  async function handleDelete(closureId: string) {
    setDeletingId(closureId);
    try {
      await onDeleteClosure(closureId);
    } finally {
      setDeletingId(null);
      setConfirmId(null);
    }
  }

  return (
    <section className="rounded-lg border border-line bg-card">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="flex w-full items-center justify-between px-4 py-3 text-left"
        aria-expanded={open}
      >
        <span className="font-bold">
          Fermetures exceptionnelles
          {closures.length > 0 && (
            <span className="ml-2 rounded-full bg-danger/10 px-2 py-0.5 text-xs font-black text-danger">
              {closures.length}
            </span>
          )}
        </span>
        {open ? <ChevronUp className="h-4 w-4 text-muted" /> : <ChevronDown className="h-4 w-4 text-muted" />}
      </button>

      {open && (
        <div className="border-t border-line px-4 pb-4 pt-3">
          {closures.length === 0 && !showForm && (
            <p className="mb-3 text-sm text-muted">
              Aucune fermeture exceptionnelle planifiée.
            </p>
          )}

          <ul className="space-y-2">
            {closures.map((closure) => (
              <li key={closure.id} className="flex items-start justify-between rounded-lg bg-soft px-3 py-2 text-sm">
                <div>
                  <p className="font-bold">{formatClosureRange(closure)}</p>
                  {closure.reason && (
                    <p className="text-xs text-muted">{closure.reason}</p>
                  )}
                </div>
                {confirmId === closure.id ? (
                  <span className="flex items-center gap-2 text-xs">
                    Supprimer ?
                    <button
                      type="button"
                      onClick={() => handleDelete(closure.id)}
                      disabled={deletingId === closure.id}
                      className="font-bold text-danger hover:underline"
                    >
                      {deletingId === closure.id ? '…' : 'Oui'}
                    </button>
                    <button
                      type="button"
                      onClick={() => setConfirmId(null)}
                      className="text-muted hover:underline"
                    >
                      Non
                    </button>
                  </span>
                ) : (
                  <button
                    type="button"
                    aria-label="Supprimer cette fermeture"
                    onClick={() => setConfirmId(closure.id)}
                    className="ml-2 shrink-0 rounded p-1 text-muted hover:bg-soft hover:text-danger"
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                  </button>
                )}
              </li>
            ))}
          </ul>

          {showForm ? (
            <ClosureForm
              onSubmit={handleCreate}
              onCancel={() => setShowForm(false)}
            />
          ) : (
            <button
              type="button"
              onClick={() => setShowForm(true)}
              className="mt-3 flex items-center gap-1.5 text-sm font-bold text-primary hover:underline"
            >
              <Plus className="h-4 w-4" />
              Ajouter une fermeture
            </button>
          )}
        </div>
      )}
    </section>
  );
}
```

- [ ] **Step 3 : Vérifier la compilation**

```bash
cd apps/frontend && npx tsc --noEmit
```
Attendu : aucune erreur.

- [ ] **Step 4 : Committer**

```bash
git add apps/frontend/src/components/merchant/creneaux/ClosureForm.tsx \
        apps/frontend/src/components/merchant/creneaux/ClosureAccordion.tsx
git commit -m "feat(merchant/creneaux): ClosureForm and ClosureAccordion components"
```

---

## Task 11 : Page principale `/merchant/creneaux`

**Files:**
- Create: `src/app/merchant/creneaux/page.tsx`

- [ ] **Step 1 : Créer la page**

```tsx
// src/app/merchant/creneaux/page.tsx
'use client';

import { useCallback, useEffect, useState } from 'react';
import { Plus } from 'lucide-react';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import { DayStrip } from '@/components/merchant/creneaux/DayStrip';
import { SlotCard } from '@/components/merchant/creneaux/SlotCard';
import { SlotCreateModal } from '@/components/merchant/creneaux/SlotCreateModal';
import { RuleAccordion } from '@/components/merchant/creneaux/RuleAccordion';
import { GenerateBanner } from '@/components/merchant/creneaux/GenerateBanner';
import { ClosureAccordion } from '@/components/merchant/creneaux/ClosureAccordion';
import {
  listMerchantSlotRules,
  createMerchantSlotRule,
  deleteMerchantSlotRule,
  generateMerchantSlots,
} from '@/lib/services/merchant-slot-rules.service';
import {
  listMerchantSlots,
  createMerchantSlot,
  patchMerchantSlot,
  deleteMerchantSlot,
} from '@/lib/services/merchant-slots.service';
import {
  listMerchantClosures,
  createMerchantClosure,
  deleteMerchantClosure,
} from '@/lib/services/merchant-closures.service';
import type {
  CreateClosurePayload,
  CreateSlotPayload,
  CreateSlotRulePayload,
  MerchantExceptionalClosure,
  MerchantPickupSlot,
  MerchantPickupSlotRule,
  PatchSlotPayload,
} from '@/lib/types/merchant-slots.types';

function buildDays(count = 14): Date[] {
  const days: Date[] = [];
  const base = new Date();
  base.setHours(0, 0, 0, 0);
  for (let i = 0; i < count; i++) {
    const d = new Date(base);
    d.setDate(d.getDate() + i);
    days.push(d);
  }
  return days;
}

function isSameDay(a: Date, b: Date): boolean {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  );
}

const DAYS = buildDays(14);

export default function MerchantCreneauxPage() {
  const { merchant } = useMerchantAuth();
  const storeId = merchant?.store.id ?? '';

  const [selectedDate, setSelectedDate] = useState<Date>(DAYS[0]);
  const [rules, setRules] = useState<MerchantPickupSlotRule[]>([]);
  const [slots, setSlots] = useState<MerchantPickupSlot[]>([]);
  const [closures, setClosures] = useState<MerchantExceptionalClosure[]>([]);
  const [showBanner, setShowBanner] = useState(false);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);

  const loadAll = useCallback(async () => {
    if (!storeId) return;
    setLoadError(null);
    try {
      const [rulesData, slotsData, closuresData] = await Promise.all([
        listMerchantSlotRules(storeId),
        listMerchantSlots(storeId),
        listMerchantClosures(storeId),
      ]);
      setRules(rulesData.items);
      setSlots(slotsData);
      setClosures(closuresData.items);
    } catch {
      setLoadError('Impossible de charger les données. Vérifiez votre connexion et réessayez.');
    }
  }, [storeId]);

  useEffect(() => {
    void loadAll();
  }, [loadAll]);

  const slotsForDay = slots.filter((s) =>
    isSameDay(new Date(s.starts_at), selectedDate),
  );

  async function handleCreateRule(payload: CreateSlotRulePayload) {
    await createMerchantSlotRule(storeId, payload);
    await loadAll();
    setShowBanner(true);
  }

  async function handleDeleteRule(ruleId: string) {
    await deleteMerchantSlotRule(storeId, ruleId);
    await loadAll();
  }

  async function handleGenerate() {
    const result = await generateMerchantSlots(storeId);
    await loadAll();
    return result;
  }

  async function handleCreateSlot(payload: CreateSlotPayload) {
    await createMerchantSlot(storeId, payload);
    await loadAll();
  }

  async function handlePatchSlot(slotId: string, payload: PatchSlotPayload) {
    await patchMerchantSlot(storeId, slotId, payload);
    await loadAll();
  }

  async function handleDeleteSlot(slotId: string) {
    await deleteMerchantSlot(storeId, slotId);
    await loadAll();
  }

  async function handleCreateClosure(payload: CreateClosurePayload) {
    await createMerchantClosure(storeId, payload);
    await loadAll();
  }

  async function handleDeleteClosure(closureId: string) {
    await deleteMerchantClosure(storeId, closureId);
    await loadAll();
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <h1 className="text-h2 font-black">Créneaux</h1>
        <button
          type="button"
          onClick={() => setShowCreateModal(true)}
          className="flex items-center gap-1.5 rounded-md bg-primary px-3 py-2 text-sm font-bold text-white hover:brightness-95"
        >
          <Plus className="h-4 w-4" aria-hidden="true" />
          Créneau ponctuel
        </button>
      </div>

      {loadError && (
        <div role="alert" className="rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
          {loadError}{' '}
          <button
            type="button"
            onClick={() => void loadAll()}
            className="font-bold underline"
          >
            Réessayer
          </button>
        </div>
      )}

      {showBanner && (
        <GenerateBanner
          onGenerate={handleGenerate}
          onDismiss={() => setShowBanner(false)}
        />
      )}

      <DayStrip
        days={DAYS}
        selectedDate={selectedDate}
        slots={slots}
        closures={closures}
        onSelectDate={setSelectedDate}
      />

      <section>
        {slotsForDay.length === 0 ? (
          <p className="text-sm text-muted">
            Aucun créneau ce jour. Ajoutez une règle récurrente ou un créneau ponctuel.
          </p>
        ) : (
          <ul className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {slotsForDay.map((slot) => (
              <li key={slot.id}>
                <SlotCard
                  slot={slot}
                  onPatch={handlePatchSlot}
                  onDelete={handleDeleteSlot}
                />
              </li>
            ))}
          </ul>
        )}
      </section>

      <RuleAccordion
        rules={rules}
        onCreateRule={handleCreateRule}
        onDeleteRule={handleDeleteRule}
      />

      <ClosureAccordion
        closures={closures}
        onCreateClosure={handleCreateClosure}
        onDeleteClosure={handleDeleteClosure}
      />

      {showCreateModal && (
        <SlotCreateModal
          initialDate={selectedDate}
          onSubmit={handleCreateSlot}
          onClose={() => setShowCreateModal(false)}
        />
      )}
    </div>
  );
}
```

- [ ] **Step 2 : Vérifier la compilation**

```bash
cd apps/frontend && npx tsc --noEmit
```
Attendu : aucune erreur.

- [ ] **Step 3 : Committer**

```bash
git add apps/frontend/src/app/merchant/creneaux/page.tsx
git commit -m "feat(merchant/creneaux): main page"
```

---

## Task 12 : Activer Créneaux dans MerchantShell

**Files:**
- Modify: `src/components/merchant/MerchantShell.tsx`

- [ ] **Step 1 : Déplacer l'entrée Créneaux**

Dans `src/components/merchant/MerchantShell.tsx`, trouver les deux tableaux :

```typescript
// Avant
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

Remplacer par :

```typescript
// Après
const ACTIVE_NAV = [
  { href: '/merchant', label: 'Dashboard', icon: BarChart3 },
  { href: '/merchant/commandes', label: 'Commandes', icon: ShoppingBasket },
  { href: '/merchant/catalogue', label: 'Catalogue', icon: Package },
  { href: '/merchant/retrait', label: 'Retrait', icon: QrCode },
  { href: '/merchant/creneaux', label: 'Créneaux', icon: CalendarClock },
  { href: '/merchant/notifications', label: 'Notifications', icon: Bell, badge: 'notifications' },
];

const DISABLED_NAV = [
  { label: 'Paramètres', icon: Settings },
];
```

- [ ] **Step 2 : Vérifier la compilation**

```bash
cd apps/frontend && npx tsc --noEmit
```
Attendu : aucune erreur.

- [ ] **Step 3 : Committer**

```bash
git add apps/frontend/src/components/merchant/MerchantShell.tsx
git commit -m "feat(merchant/creneaux): activate Créneaux nav entry"
```

---

## Task 13 : Tests composants et page

**Files:**
- Create: `src/tests/merchant.creneaux.test.tsx`

- [ ] **Step 1 : Écrire les tests**

```tsx
// src/tests/merchant.creneaux.test.tsx
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { DayStrip } from '@/components/merchant/creneaux/DayStrip';
import { SlotCard } from '@/components/merchant/creneaux/SlotCard';
import { RuleAccordion } from '@/components/merchant/creneaux/RuleAccordion';
import { GenerateBanner } from '@/components/merchant/creneaux/GenerateBanner';
import { ClosureAccordion } from '@/components/merchant/creneaux/ClosureAccordion';
import MerchantCreneauxPage from '@/app/merchant/creneaux/page';
import {
  listMerchantSlotRules,
  createMerchantSlotRule,
  deleteMerchantSlotRule,
  generateMerchantSlots,
} from '@/lib/services/merchant-slot-rules.service';
import {
  listMerchantSlots,
  createMerchantSlot,
  patchMerchantSlot,
  deleteMerchantSlot,
} from '@/lib/services/merchant-slots.service';
import {
  listMerchantClosures,
  createMerchantClosure,
  deleteMerchantClosure,
} from '@/lib/services/merchant-closures.service';
import type {
  MerchantPickupSlot,
  MerchantPickupSlotRule,
  MerchantExceptionalClosure,
} from '@/lib/types/merchant-slots.types';

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => ({
    merchant: { store: { id: 'store-1', name: 'Supérette Test', active: true } },
  }),
}));

vi.mock('@/lib/services/merchant-slot-rules.service', () => ({
  listMerchantSlotRules: vi.fn(),
  createMerchantSlotRule: vi.fn(),
  deleteMerchantSlotRule: vi.fn(),
  generateMerchantSlots: vi.fn(),
}));
vi.mock('@/lib/services/merchant-slots.service', () => ({
  listMerchantSlots: vi.fn(),
  createMerchantSlot: vi.fn(),
  patchMerchantSlot: vi.fn(),
  deleteMerchantSlot: vi.fn(),
}));
vi.mock('@/lib/services/merchant-closures.service', () => ({
  listMerchantClosures: vi.fn(),
  createMerchantClosure: vi.fn(),
  deleteMerchantClosure: vi.fn(),
}));

const today = new Date();
today.setHours(0, 0, 0, 0);

function makeSlot(overrides: Partial<MerchantPickupSlot> = {}): MerchantPickupSlot {
  const d = new Date(today);
  d.setHours(17, 0, 0, 0);
  const e = new Date(today);
  e.setHours(18, 0, 0, 0);
  return {
    id: 'slot-1',
    starts_at: d.toISOString(),
    ends_at: e.toISOString(),
    capacity: 6,
    booked_count: 2,
    is_active: true,
    ...overrides,
  };
}

const rule: MerchantPickupSlotRule = {
  id: 'rule-1',
  weekday: 3,
  start_time: '17:00',
  end_time: '19:00',
  capacity: 6,
  is_active: true,
};

const closure: MerchantExceptionalClosure = {
  id: 'closure-1',
  starts_at: new Date(today.getFullYear(), today.getMonth() + 1, 1).toISOString(),
  ends_at: new Date(today.getFullYear(), today.getMonth() + 1, 1, 23, 59).toISOString(),
  reason: 'Aïd el-Fitr',
  is_active: true,
};

function setupPageMocks() {
  vi.mocked(listMerchantSlotRules).mockResolvedValue({ total: 0, items: [] });
  vi.mocked(listMerchantSlots).mockResolvedValue([]);
  vi.mocked(listMerchantClosures).mockResolvedValue({ total: 0, items: [] });
}

// ─── DayStrip ───────────────────────────────────────────────────────────────

describe('DayStrip', () => {
  const days = [today, new Date(today.getTime() + 86400000)];

  it('renders all days', () => {
    render(
      React.createElement(DayStrip, {
        days,
        selectedDate: today,
        slots: [],
        closures: [],
        onSelectDate: vi.fn(),
      }),
    );
    expect(screen.getAllByRole('listitem')).toHaveLength(2);
  });

  it('marks selected day as pressed', () => {
    render(
      React.createElement(DayStrip, {
        days,
        selectedDate: today,
        slots: [],
        closures: [],
        onSelectDate: vi.fn(),
      }),
    );
    const items = screen.getAllByRole('listitem');
    expect(items[0]).toHaveAttribute('aria-pressed', 'true');
    expect(items[1]).toHaveAttribute('aria-pressed', 'false');
  });

  it('shows slot count badge for day with active slots', () => {
    const slot = makeSlot();
    render(
      React.createElement(DayStrip, {
        days,
        selectedDate: today,
        slots: [slot],
        closures: [],
        onSelectDate: vi.fn(),
      }),
    );
    expect(screen.getByLabelText('1 créneau')).toBeInTheDocument();
  });

  it('calls onSelectDate when a day is clicked', () => {
    const onSelect = vi.fn();
    render(
      React.createElement(DayStrip, {
        days,
        selectedDate: today,
        slots: [],
        closures: [],
        onSelectDate: onSelect,
      }),
    );
    fireEvent.click(screen.getAllByRole('listitem')[1]);
    expect(onSelect).toHaveBeenCalledWith(days[1]);
  });
});

// ─── SlotCard ────────────────────────────────────────────────────────────────

describe('SlotCard', () => {
  it('shows time range and reservation count', () => {
    const slot = makeSlot();
    render(React.createElement(SlotCard, { slot, onPatch: vi.fn(), onDelete: vi.fn() }));
    expect(screen.getByText(/2\/6 réservé/)).toBeInTheDocument();
  });

  it('shows Complet badge when fully booked', () => {
    const slot = makeSlot({ capacity: 4, booked_count: 4 });
    render(React.createElement(SlotCard, { slot, onPatch: vi.fn(), onDelete: vi.fn() }));
    expect(screen.getByText('Complet')).toBeInTheDocument();
  });

  it('shows Inactif badge when is_active is false', () => {
    const slot = makeSlot({ is_active: false });
    render(React.createElement(SlotCard, { slot, onPatch: vi.fn(), onDelete: vi.fn() }));
    expect(screen.getByText('Inactif')).toBeInTheDocument();
  });

  it('blocks deletion with error when slot has reservations', async () => {
    const onDelete = vi.fn();
    const slot = makeSlot({ booked_count: 2 });
    render(React.createElement(SlotCard, { slot, onPatch: vi.fn(), onDelete }));

    fireEvent.click(screen.getByLabelText('Supprimer ce créneau'));

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('réservations');
    });
    expect(onDelete).not.toHaveBeenCalled();
  });

  it('calls onDelete when slot has no reservations', async () => {
    const onDelete = vi.fn().mockResolvedValue(undefined);
    const slot = makeSlot({ booked_count: 0 });
    render(React.createElement(SlotCard, { slot, onPatch: vi.fn(), onDelete }));

    fireEvent.click(screen.getByLabelText('Supprimer ce créneau'));
    await waitFor(() => expect(onDelete).toHaveBeenCalledWith('slot-1'));
  });
});

// ─── RuleAccordion ───────────────────────────────────────────────────────────

describe('RuleAccordion', () => {
  it('is open by default when no rules exist', () => {
    render(
      React.createElement(RuleAccordion, {
        rules: [],
        onCreateRule: vi.fn(),
        onDeleteRule: vi.fn(),
      }),
    );
    expect(screen.getByText(/aucune règle/i)).toBeInTheDocument();
  });

  it('displays rule with weekday label', () => {
    render(
      React.createElement(RuleAccordion, {
        rules: [rule],
        onCreateRule: vi.fn(),
        onDeleteRule: vi.fn(),
      }),
    );
    fireEvent.click(screen.getByText('Règles récurrentes'));
    expect(screen.getByText(/Mercredi/)).toBeInTheDocument();
    expect(screen.getByText(/17:00–19:00/)).toBeInTheDocument();
  });

  it('shows delete confirmation before deleting', async () => {
    const onDelete = vi.fn().mockResolvedValue(undefined);
    render(
      React.createElement(RuleAccordion, {
        rules: [rule],
        onCreateRule: vi.fn(),
        onDeleteRule: onDelete,
      }),
    );
    fireEvent.click(screen.getByText('Règles récurrentes'));
    fireEvent.click(screen.getByLabelText(/Supprimer la règle/));
    expect(screen.getByText('Supprimer ?')).toBeInTheDocument();
    fireEvent.click(screen.getByText('Oui'));
    await waitFor(() => expect(onDelete).toHaveBeenCalledWith('rule-1'));
  });
});

// ─── GenerateBanner ──────────────────────────────────────────────────────────

describe('GenerateBanner', () => {
  it('renders the generate prompt', () => {
    render(
      React.createElement(GenerateBanner, {
        onGenerate: vi.fn(),
        onDismiss: vi.fn(),
      }),
    );
    expect(screen.getByText(/générer les créneaux/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /générer/i })).toBeInTheDocument();
  });

  it('calls onDismiss when Plus tard is clicked', () => {
    const onDismiss = vi.fn();
    render(
      React.createElement(GenerateBanner, {
        onGenerate: vi.fn(),
        onDismiss,
      }),
    );
    fireEvent.click(screen.getByRole('button', { name: /plus tard/i }));
    expect(onDismiss).toHaveBeenCalled();
  });

  it('shows success message after generation', async () => {
    const onGenerate = vi.fn().mockResolvedValue({
      store_id: 'store-1',
      generated_count: 12,
      skipped_existing_count: 0,
      skipped_closure_count: 0,
      horizon_start: '2026-05-25',
      horizon_end: '2026-06-22',
    });
    render(
      React.createElement(GenerateBanner, {
        onGenerate,
        onDismiss: vi.fn(),
      }),
    );
    fireEvent.click(screen.getByRole('button', { name: /générer/i }));
    await waitFor(() => {
      expect(screen.getByRole('status')).toHaveTextContent('12 créneaux générés');
    });
  });
});

// ─── ClosureAccordion ────────────────────────────────────────────────────────

describe('ClosureAccordion', () => {
  it('shows no closures message when empty', () => {
    render(
      React.createElement(ClosureAccordion, {
        closures: [],
        onCreateClosure: vi.fn(),
        onDeleteClosure: vi.fn(),
      }),
    );
    fireEvent.click(screen.getByText('Fermetures exceptionnelles'));
    expect(screen.getByText(/aucune fermeture/i)).toBeInTheDocument();
  });

  it('lists closure with reason', () => {
    render(
      React.createElement(ClosureAccordion, {
        closures: [closure],
        onCreateClosure: vi.fn(),
        onDeleteClosure: vi.fn(),
      }),
    );
    fireEvent.click(screen.getByText(/Fermetures exceptionnelles/));
    expect(screen.getByText('Aïd el-Fitr')).toBeInTheDocument();
  });

  it('confirms before deleting a closure', async () => {
    const onDelete = vi.fn().mockResolvedValue(undefined);
    render(
      React.createElement(ClosureAccordion, {
        closures: [closure],
        onCreateClosure: vi.fn(),
        onDeleteClosure: onDelete,
      }),
    );
    fireEvent.click(screen.getByText(/Fermetures exceptionnelles/));
    fireEvent.click(screen.getByLabelText('Supprimer cette fermeture'));
    expect(screen.getByText('Supprimer ?')).toBeInTheDocument();
    fireEvent.click(screen.getByText('Oui'));
    await waitFor(() => expect(onDelete).toHaveBeenCalledWith('closure-1'));
  });
});

// ─── MerchantCreneauxPage ────────────────────────────────────────────────────

describe('MerchantCreneauxPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setupPageMocks();
  });

  it('renders the page heading', async () => {
    render(React.createElement(MerchantCreneauxPage));
    await waitFor(() => {
      expect(screen.getByRole('heading', { name: 'Créneaux' })).toBeInTheDocument();
    });
  });

  it('shows slots for today filtered from all slots', async () => {
    const todaySlot = makeSlot();
    const tomorrow = new Date(today.getTime() + 86400000);
    tomorrow.setHours(17, 0, 0, 0);
    const tomorrowEnd = new Date(today.getTime() + 86400000);
    tomorrowEnd.setHours(18, 0, 0, 0);
    const tomorrowSlot = makeSlot({
      id: 'slot-2',
      starts_at: tomorrow.toISOString(),
      ends_at: tomorrowEnd.toISOString(),
    });

    vi.mocked(listMerchantSlots).mockResolvedValue([todaySlot, tomorrowSlot]);

    render(React.createElement(MerchantCreneauxPage));

    await waitFor(() => {
      expect(screen.queryAllByText(/17:00/)).toHaveLength(1);
    });
  });

  it('shows error alert when load fails', async () => {
    vi.mocked(listMerchantSlotRules).mockRejectedValue(new Error('network'));

    render(React.createElement(MerchantCreneauxPage));

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Impossible de charger');
    });
  });

  it('shows GenerateBanner after creating a rule', async () => {
    vi.mocked(createMerchantSlotRule).mockResolvedValue(rule);

    render(React.createElement(MerchantCreneauxPage));
    await waitFor(() => screen.getByRole('heading', { name: 'Créneaux' }));

    fireEvent.click(screen.getByText('Règles récurrentes'));
    fireEvent.click(screen.getByText('Nouvelle règle'));
    fireEvent.click(screen.getByRole('button', { name: /ajouter la règle/i }));

    await waitFor(() => {
      expect(screen.getByText(/générer les créneaux/i)).toBeInTheDocument();
    });
  });
});
```

- [ ] **Step 2 : Lancer les tests — vérifier qu'ils échouent**

```bash
cd apps/frontend && npm run test:run -- src/tests/merchant.creneaux.test.tsx
```
Attendu : FAIL (composants pas encore importables avec les mocks).

- [ ] **Step 3 : Lancer les tests — vérifier qu'ils passent**

Les composants et la page sont déjà créés depuis Task 5–11. Relancer :

```bash
cd apps/frontend && npm run test:run -- src/tests/merchant.creneaux.test.tsx
```
Attendu : tous les tests PASS.

- [ ] **Step 4 : Lancer la suite complète**

```bash
cd apps/frontend && npm run test:run
```
Attendu : tous les tests existants + nouveaux PASS.

- [ ] **Step 5 : Committer**

```bash
git add apps/frontend/src/tests/merchant.creneaux.test.tsx
git commit -m "test(merchant/creneaux): component and page tests"
```

---

## Task 14 : Vérification finale

- [ ] **Step 1 : TypeScript**

```bash
cd apps/frontend && npx tsc --noEmit
```
Attendu : aucune erreur.

- [ ] **Step 2 : Lint**

```bash
cd apps/frontend && npm run lint
```
Attendu : aucune erreur ou warning bloquant.

- [ ] **Step 3 : Build**

```bash
cd apps/frontend && npm run build
```
Attendu : build réussi, aucune erreur.

- [ ] **Step 4 : Committer le rapport final si ajustements nécessaires**

```bash
git add -p
git commit -m "fix(merchant/creneaux): tsc/lint/build cleanup"
```

---

## Auto-revue de la spec

**Couverture :**
- ✅ Règles récurrentes (CRUD + génération) → Tasks 2, 8, 9
- ✅ Créneaux ponctuels (liste + CRUD) → Tasks 3, 6, 7
- ✅ Fermetures exceptionnelles (CRUD) → Tasks 4, 10
- ✅ DayStrip avec badge + indicateur fermeture → Task 5
- ✅ GenerateBanner 2 étapes → Task 9
- ✅ MerchantShell activation → Task 12
- ✅ Filtrage créneaux côté client → Task 11 (`isSameDay`)
- ✅ Suppression bloquée si booked_count > 0 → Task 6

**Points d'attention pour l'implémenteur :**
- `GET /pickup-slots` retourne un **tableau direct** (pas `{ total, items }`).
- `GET /pickup-slot-rules` et `GET /exceptional-closures` retournent `{ total, items }`.
- `POST /pickup-slots` retourne un corps vide (201) — ne pas typer le retour.
- `POST /pickup-slot-rules/generate` retourne `{ store_id, generated_count, skipped_existing_count, skipped_closure_count, horizon_start, horizon_end }`.
- Après chaque mutation, appeler `loadAll()` pour rafraîchir toutes les sections.
