# Merchant Créneaux UX — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Améliorer l'UX de la page `/merchant/creneaux` : sélecteur multi-jours pour les règles récurrentes, bouton de génération permanent avec horizon 1 ou 3 mois, et warning global j+6 sur toutes les pages marchands.

**Architecture:** Trois changements indépendants — (1) frontend pur : chips multi-jours dans `RuleForm` ; (2) backend mineur + frontend : paramètre `horizon_months` sur l'endpoint `/generate` + section génération permanente dans `RuleAccordion` ; (3) frontend pur : `SlotCoverageWarning` monté dans `MerchantShell` qui appelle `listMerchantSlots` et alerte si j+6 est vide.

**Tech Stack:** PHP 8.4 / Symfony 7 / API Platform 4 / PHPUnit — Next.js 14 / React / Tailwind / Vitest + Testing Library

---

## Fichiers touchés

### Backend
| Fichier | Nature |
|---|---|
| `apps/backend/src/Dto/GenerateSlotsInput.php` | Créer |
| `apps/backend/src/ApiResource/PickupSlotRuleGenerationOutput.php` | Modifier `input:` |
| `apps/backend/src/Service/PickupSlotRuleGenerator.php` | Ajouter param `$horizonMonths` |
| `apps/backend/src/Processor/GenerateMerchantPickupSlotRulesProcessor.php` | Lire input |
| `apps/backend/tests/Functional/Api/MerchantPickupSlotRuleApiTest.php` | Mettre à jour counts + ajouter test |

### Frontend
| Fichier | Nature |
|---|---|
| `apps/frontend/src/lib/services/merchant-slot-rules.service.ts` | Ajouter param `horizonMonths` |
| `apps/frontend/src/tests/merchant.slot-rules.service.test.ts` | Mettre à jour `generateMerchantSlots` test |
| `apps/frontend/src/components/merchant/creneaux/RuleForm.tsx` | Chips multi-jours |
| `apps/frontend/src/components/merchant/creneaux/RuleAccordion.tsx` | Prop `onGenerate` + section génération |
| `apps/frontend/src/app/merchant/creneaux/page.tsx` | Retirer GenerateBanner, câbler `onGenerate` |
| `apps/frontend/src/tests/merchant.creneaux.test.tsx` | Mettre à jour RuleAccordion/page tests |
| `apps/frontend/src/components/merchant/SlotCoverageWarning.tsx` | Créer |
| `apps/frontend/src/components/merchant/MerchantShell.tsx` | Appel slots + warning |

---

## Task 1 : GenerateSlotsInput DTO (backend)

**Files:**
- Create: `apps/backend/src/Dto/GenerateSlotsInput.php`

- [ ] **Step 1 : Créer le DTO**

```php
<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class GenerateSlotsInput
{
    #[Assert\Choice([1, 3])]
    #[SerializedName('horizon_months')]
    public int $horizonMonths = 1;
}
```

- [ ] **Step 2 : Vérifier que le fichier est bien chargé (autoload)**

```bash
cd apps/backend && vendor/bin/phpstan analyse src/Dto/GenerateSlotsInput.php --memory-limit=512M
```

Expected: `[OK] No errors`

---

## Task 2 : ApiResource + Processor + Service (backend)

**Files:**
- Modify: `apps/backend/src/ApiResource/PickupSlotRuleGenerationOutput.php`
- Modify: `apps/backend/src/Processor/GenerateMerchantPickupSlotRulesProcessor.php`
- Modify: `apps/backend/src/Service/PickupSlotRuleGenerator.php`

- [ ] **Step 1 : Mettre à jour `PickupSlotRuleGenerationOutput` — `input: false` → `input: GenerateSlotsInput::class`**

Dans `apps/backend/src/ApiResource/PickupSlotRuleGenerationOutput.php`, remplacer la ligne :
```php
            input: false,
```
par :
```php
            input: GenerateSlotsInput::class,
```
Et ajouter l'import en haut du fichier après les autres `use` :
```php
use App\Dto\GenerateSlotsInput;
```

- [ ] **Step 2 : Mettre à jour `GenerateMerchantPickupSlotRulesProcessor`**

Remplacer la méthode `process` entière par :

```php
    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PickupSlotRuleGenerationOutput
    {
        if (!$data instanceof GenerateSlotsInput) {
            throw new \InvalidArgumentException('GenerateSlotsInput expected.');
        }

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $result = $this->pickupSlotRuleGenerator->generateForShop($shop, horizonMonths: $data->horizonMonths);

        return new PickupSlotRuleGenerationOutput(
            storeId: $shop->getId()->toRfc4122(),
            generatedCount: $result->generatedCount,
            skippedExistingCount: $result->skippedExistingCount,
            skippedClosureCount: $result->skippedClosureCount,
            horizonStart: $result->horizonStart->format(\DateTimeInterface::ATOM),
            horizonEnd: $result->horizonEnd->format(\DateTimeInterface::ATOM),
        );
    }
```

Et ajouter l'import manquant :
```php
use App\Dto\GenerateSlotsInput;
```

- [ ] **Step 3 : Mettre à jour `PickupSlotRuleGenerator::generateForShop()`**

Remplacer la signature et la ligne `$horizonEnd` :

```php
    public function generateForShop(Shop $shop, ?\DateTimeImmutable $now = null, int $horizonMonths = 1): PickupSlotRuleGenerationResult
    {
        $timezone = new \DateTimeZone(self::TIMEZONE);
        $now = ($now ?? new \DateTimeImmutable('now', $timezone))->setTimezone($timezone);
        $horizonStart = $now->setTime(0, 0, 0);
        $horizonEnd = $horizonStart->modify("+{$horizonMonths} months");
        // ... reste identique
```

Seules les deux lignes `public function generateForShop(...)` et `$horizonEnd = ...` changent. Le corps de la méthode est inchangé.

- [ ] **Step 4 : Vérifier PHPStan**

```bash
cd apps/backend && vendor/bin/phpstan analyse src/Dto/GenerateSlotsInput.php src/ApiResource/PickupSlotRuleGenerationOutput.php src/Processor/GenerateMerchantPickupSlotRulesProcessor.php src/Service/PickupSlotRuleGenerator.php --memory-limit=512M
```

Expected: `[OK] No errors`

---

## Task 3 : Mise à jour des tests backend

**Files:**
- Modify: `apps/backend/tests/Functional/Api/MerchantPickupSlotRuleApiTest.php`

**Contexte :** Le passage de `+4 weeks` à `+1 month` change le nombre d'occurrences d'un jour donné dans la fenêtre (4 ou 5 selon la date courante). Les tests qui assertent des counts fixes doivent être rendus dynamiques ou adaptés.

- [ ] **Step 1 : Écrire le test `testGenerateWithThreeMonthHorizonCreatesMoreSlotsThanOneMonth`**

Ajouter à la fin de la classe (avant `validRulePayload`) :

```php
    public function testGenerateWithThreeMonthHorizonCreatesMoreSlotsThanOneMonth(): void
    {
        $merchant = $this->createUser('merchant-slot-rule-generate-horizon@example.test', ['ROLE_MERCHANT']);
        $shop1 = $this->createShop($merchant);
        $shop3 = $this->createShop($merchant);
        $timezone = new \DateTimeZone('Africa/Tunis');
        $weekday = (int) (new \DateTimeImmutable('tomorrow', $timezone))->format('N');
        $this->createRule($shop1, $weekday, '09:00', '10:00', 6);
        $this->createRule($shop3, $weekday, '09:00', '10:00', 6);
        $path1 = \sprintf('/api/merchant/stores/%s/pickup-slot-rules/generate', $shop1->getId());
        $path3 = \sprintf('/api/merchant/stores/%s/pickup-slot-rules/generate', $shop3->getId());

        $response1 = $this->requestJson('POST', $path1, ['horizon_months' => 1], $merchant);
        $response3 = $this->requestJson('POST', $path3, ['horizon_months' => 3], $merchant);

        self::assertSame(200, $response1->getStatusCode());
        self::assertSame(200, $response3->getStatusCode());
        $count1 = $this->decodeJson($response1)['generated_count'];
        $count3 = $this->decodeJson($response3)['generated_count'];
        self::assertGreaterThanOrEqual(4, $count1);
        self::assertLessThanOrEqual(5, $count1);
        self::assertGreaterThan($count1, $count3);
        self::assertGreaterThanOrEqual(13, $count3);
    }

    public function testGenerateRejectsInvalidHorizonMonths(): void
    {
        $merchant = $this->createUser('merchant-slot-rule-generate-invalid-horizon@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $path = \sprintf('/api/merchant/stores/%s/pickup-slot-rules/generate', $shop->getId());

        $response2 = $this->requestJson('POST', $path, ['horizon_months' => 2], $merchant);
        $response0 = $this->requestJson('POST', $path, ['horizon_months' => 0], $merchant);

        self::assertSame(422, $response2->getStatusCode());
        self::assertSame(422, $response0->getStatusCode());
    }
```

- [ ] **Step 2 : Vérifier que les deux nouveaux tests échouent (avant fix)**

```bash
cd apps/backend && vendor/bin/phpunit tests/Functional/Api/MerchantPickupSlotRuleApiTest.php --filter "testGenerateWith|testGenerateRejects" --testdox 2>&1 | tail -20
```

Expected: les deux tests sont reconnus (peut passer ou échouer selon l'état actuel).

- [ ] **Step 3 : Mettre à jour `testGenerateCreatesFourWeeksOfPickupSlotsFromActiveRules`**

Ce test assertait `generated_count: 4` (4 semaines). Avec 1 mois, le count est 4 ou 5. Remplacer les lignes d'assertion du count :

```php
        // Remplacer :
        self::assertSame(4, $payload['generated_count']);
        // Par :
        self::assertGreaterThanOrEqual(4, $payload['generated_count']);
        self::assertLessThanOrEqual(5, $payload['generated_count']);
```

Et remplacer les assertions sur `$slots` count :
```php
        // Remplacer :
        self::assertCount(4, $slots);
        // Par :
        $expectedCount = $payload['generated_count'];
        self::assertCount($expectedCount, $slots);
```

- [ ] **Step 4 : Mettre à jour `testGenerateIsIdempotentAndDoesNotModifyExistingBookedSlot`**

Ajouter un helper de calcul du nombre d'occurrences attendu, puis l'utiliser pour les assertions :

```php
        // Après le setup, calculer le nombre total d'occurrences dans le mois
        $timezone = new \DateTimeZone('Africa/Tunis');
        $horizonStart = (new \DateTimeImmutable('now', $timezone))->setTime(0, 0, 0);
        $horizonEnd = $horizonStart->modify('+1 month');
        $totalOccurrences = 0;
        for ($d = $horizonStart; $d < $horizonEnd; $d = $d->modify('+1 day')) {
            if ((int) $d->format('N') === $weekday && $d->setTime(9, 0, 0) > new \DateTimeImmutable('now', $timezone)) {
                ++$totalOccurrences;
            }
        }

        // Remplacer les assertions fixes par :
        self::assertSame($totalOccurrences - 1, $firstPayload['generated_count']); // -1 car 1 existant
        self::assertSame(1, $firstPayload['skipped_existing_count']);
        self::assertSame(0, $firstPayload['skipped_closure_count']);
        self::assertSame(0, $secondPayload['generated_count']);
        self::assertSame($totalOccurrences, $secondPayload['skipped_existing_count']);
        self::assertSame($totalOccurrences, $this->entityManager->getRepository(PickupSlot::class)->count(['shop' => $shop]));
```

- [ ] **Step 5 : Mettre à jour `testGenerateSkipsOverlappingActiveExistingSlot`**

Même logique : remplacer les counts fixes par des assertions dynamiques :

```php
        // Après le setup, calculer $totalOccurrences (même pattern que Task 3 Step 4)
        // puis remplacer :
        self::assertSame($totalOccurrences - 1, $payload['generated_count']);
        self::assertSame(1, $payload['skipped_existing_count']);
        self::assertSame($totalOccurrences, $this->entityManager->getRepository(PickupSlot::class)->count(['shop' => $shop]));
```

- [ ] **Step 6 : Mettre à jour `testGeneratorDoesNotCreatePastSlotOrFifthOccurrenceWhenRuleMatchesToday`**

Ce test utilise une date hardcodée `2026-05-18` (un lundi). Avec `+1 month` (jusqu'au 2026-06-18), les lundis sont : 5/18, 5/25, 6/1, 6/8, 6/15 = 5 slots. Mettre à jour :

```php
        // Remplacer :
        self::assertSame(4, $result->generatedCount);
        // Par :
        self::assertSame(5, $result->generatedCount);

        // Remplacer :
        self::assertCount(4, $slots);
        // Par :
        self::assertCount(5, $slots);

        // Remplacer :
        self::assertSame('2026-06-08 09:00', $slots[3]->getStartsAt()->setTimezone($timezone)->format('Y-m-d H:i'));
        // Par :
        self::assertSame('2026-06-08 09:00', $slots[3]->getStartsAt()->setTimezone($timezone)->format('Y-m-d H:i'));
        self::assertSame('2026-06-15 09:00', $slots[4]->getStartsAt()->setTimezone($timezone)->format('Y-m-d H:i'));

        // Pour le deuxième shop (lateNowShop) :
        // $lateResult->generatedCount passe de 3 à 4 (5/25, 6/1, 6/8, 6/15 — 5/18 est dans le passé à 12:00)
        self::assertSame(4, $lateResult->generatedCount);
        self::assertCount(4, $lateSlots);
        self::assertSame('2026-05-25 09:00', $lateSlots[0]->getStartsAt()->setTimezone($timezone)->format('Y-m-d H:i'));
        self::assertSame('2026-06-15 09:00', $lateSlots[3]->getStartsAt()->setTimezone($timezone)->format('Y-m-d H:i'));
```

- [ ] **Step 7 : Mettre à jour les tests existants qui passent `[]` au lieu de `['horizon_months' => 1]`**

Dans tous les tests qui appellent l'endpoint `/generate` avec `[]`, remplacer par `['horizon_months' => 1]` :
- `testGenerateCreatesFourWeeksOfPickupSlotsFromActiveRules` (2 occurrences)
- `testGenerateIsIdempotentAndDoesNotModifyExistingBookedSlot` (2 occurrences : `$firstResponse` et `$secondResponse`)
- `testGenerateSkipsOverlappingActiveExistingSlot` (1 occurrence)

- [ ] **Step 8 : Lancer tous les tests du fichier**

```bash
cd apps/backend && vendor/bin/phpunit tests/Functional/Api/MerchantPickupSlotRuleApiTest.php --testdox 2>&1 | tail -30
```

Expected: tous les tests passent (aucune FAIL, les erreurs 403/404 dans les logs sont normales).

---

## Task 4 : Quality check backend + commit

- [ ] **Step 1 : PHPStan niveau 8**

```bash
cd apps/backend && vendor/bin/phpstan analyse --memory-limit=512M 2>&1 | tail -5
```

Expected: `[OK] No errors`

- [ ] **Step 2 : CS Fixer**

```bash
cd apps/backend && vendor/bin/php-cs-fixer fix --dry-run --diff 2>&1 | tail -10
```

Expected: `No changes to fixer were needed.` (sinon : `vendor/bin/php-cs-fixer fix` puis re-run dry-run)

- [ ] **Step 3 : Suite PHPUnit complète**

```bash
cd apps/backend && vendor/bin/phpunit --testdox 2>&1 | tail -15
```

Expected: `OK (N tests, M assertions)` sans FAIL ni ERROR.

- [ ] **Step 4 : Commit backend**

```bash
git add apps/backend/src/Dto/GenerateSlotsInput.php \
        apps/backend/src/ApiResource/PickupSlotRuleGenerationOutput.php \
        apps/backend/src/Service/PickupSlotRuleGenerator.php \
        apps/backend/src/Processor/GenerateMerchantPickupSlotRulesProcessor.php \
        apps/backend/tests/Functional/Api/MerchantPickupSlotRuleApiTest.php
git commit -m "feat(slots): paramètre horizon_months sur l'endpoint generate (1 ou 3 mois)"
```

---

## Task 5 : Service frontend + types (frontend)

**Files:**
- Modify: `apps/frontend/src/lib/services/merchant-slot-rules.service.ts`
- Modify: `apps/frontend/src/tests/merchant.slot-rules.service.test.ts`

- [ ] **Step 1 : Écrire le test mis à jour pour `generateMerchantSlots`**

Dans `merchant.slot-rules.service.test.ts`, remplacer le test `'generates slots and returns the result'` par :

```ts
  it('generates slots for 1 month by default', async () => {
    const generated = {
      store_id: STORE_ID,
      generated_count: 4,
      skipped_existing_count: 0,
      skipped_closure_count: 0,
      horizon_start: '2026-05-25T00:00:00+01:00',
      horizon_end: '2026-06-25T00:00:00+01:00',
    };
    vi.mocked(apiClient.post).mockResolvedValue({ data: generated });

    const result = await generateMerchantSlots(STORE_ID);

    expect(apiClient.post).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/pickup-slot-rules/generate`,
      { horizon_months: 1 },
    );
    expect(result.generated_count).toBe(4);
  });

  it('generates slots for 3 months when specified', async () => {
    const generated = {
      store_id: STORE_ID,
      generated_count: 13,
      skipped_existing_count: 0,
      skipped_closure_count: 0,
      horizon_start: '2026-05-25T00:00:00+01:00',
      horizon_end: '2026-08-25T00:00:00+01:00',
    };
    vi.mocked(apiClient.post).mockResolvedValue({ data: generated });

    const result = await generateMerchantSlots(STORE_ID, 3);

    expect(apiClient.post).toHaveBeenCalledWith(
      `/api/merchant/stores/${STORE_ID}/pickup-slot-rules/generate`,
      { horizon_months: 3 },
    );
    expect(result.generated_count).toBe(13);
  });
```

- [ ] **Step 2 : Lancer les tests pour vérifier qu'ils échouent**

```bash
cd apps/frontend && npm test -- merchant.slot-rules.service 2>&1 | tail -20
```

Expected: `generates slots for 1 month by default` et `generates slots for 3 months when specified` FAIL (le service envoie encore `{}`).

- [ ] **Step 3 : Mettre à jour `generateMerchantSlots` dans le service**

Dans `apps/frontend/src/lib/services/merchant-slot-rules.service.ts`, remplacer la fonction `generateMerchantSlots` :

```ts
export async function generateMerchantSlots(
  storeId: string,
  horizonMonths: 1 | 3 = 1,
): Promise<GenerateSlotsResult> {
  const { data } = await apiClient.post<GenerateSlotsResult>(
    `/api/merchant/stores/${storeId}/pickup-slot-rules/generate`,
    { horizon_months: horizonMonths },
  );
  return data;
}
```

- [ ] **Step 4 : Relancer les tests**

```bash
cd apps/frontend && npm test -- merchant.slot-rules.service 2>&1 | tail -10
```

Expected: `5 passed`

---

## Task 6 : RuleForm multi-day chips (frontend)

**Files:**
- Modify: `apps/frontend/src/components/merchant/creneaux/RuleForm.tsx`

- [ ] **Step 1 : Réécrire `RuleForm.tsx` entièrement**

```tsx
'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { cn } from '@/lib/cn';
import type { CreateSlotRulePayload } from '@/lib/types/merchant-slots.types';

const WEEKDAYS = [
  { value: 1, label: 'Lun' },
  { value: 2, label: 'Mar' },
  { value: 3, label: 'Mer' },
  { value: 4, label: 'Jeu' },
  { value: 5, label: 'Ven' },
  { value: 6, label: 'Sam' },
  { value: 7, label: 'Dim' },
];

export interface RuleFormProps {
  onSubmit: (payload: CreateSlotRulePayload) => Promise<void>;
  onCancel: () => void;
}

export function RuleForm({ onSubmit, onCancel }: RuleFormProps) {
  const [weekdays, setWeekdays] = useState<Set<number>>(
    new Set([1, 2, 3, 4, 5, 6, 7]),
  );
  const [startTime, setStartTime] = useState('17:00');
  const [endTime, setEndTime] = useState('19:00');
  const [capacity, setCapacity] = useState('6');
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  function toggleDay(day: number) {
    setWeekdays((prev) => {
      const next = new Set(prev);
      if (next.has(day)) {
        next.delete(day);
      } else {
        next.add(day);
      }
      return next;
    });
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (weekdays.size === 0) {
      setError('Sélectionnez au moins un jour.');
      return;
    }
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
    const failed: string[] = [];
    try {
      for (const day of [...weekdays].sort((a, b) => a - b)) {
        try {
          await onSubmit({
            weekday: day,
            start_time: startTime,
            end_time: endTime,
            capacity: cap,
          });
        } catch {
          failed.push(WEEKDAYS.find((d) => d.value === day)?.label ?? String(day));
        }
      }
      if (failed.length > 0) {
        setError(`Échec pour : ${failed.join(', ')} (doublon ou erreur serveur).`);
      }
    } finally {
      setSaving(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="mt-3 space-y-3 rounded-lg border border-line bg-soft p-3">
      <div>
        <span className="mb-2 block text-xs font-bold text-muted">Jours de la semaine</span>
        <div className="flex flex-wrap gap-2" role="group" aria-label="Jours de la semaine">
          {WEEKDAYS.map((d) => (
            <button
              key={d.value}
              type="button"
              aria-pressed={weekdays.has(d.value)}
              onClick={() => toggleDay(d.value)}
              className={cn(
                'rounded-full px-3 py-1 text-xs font-bold transition-colors',
                weekdays.has(d.value)
                  ? 'bg-primary text-white'
                  : 'border border-line bg-white text-muted',
              )}
            >
              {d.label}
            </button>
          ))}
        </div>
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

- [ ] **Step 2 : Lancer le lint TypeScript**

```bash
cd apps/frontend && npm run lint 2>&1 | grep -i "ruleform\|error" | head -10
```

Expected: aucune erreur sur `RuleForm.tsx`

---

## Task 7 : RuleAccordion + page.tsx + mise à jour des tests (frontend)

**Files:**
- Modify: `apps/frontend/src/components/merchant/creneaux/RuleAccordion.tsx`
- Modify: `apps/frontend/src/app/merchant/creneaux/page.tsx`
- Modify: `apps/frontend/src/tests/merchant.creneaux.test.tsx`

- [ ] **Step 1 : Écrire les tests mis à jour pour RuleAccordion et la page**

Dans `merchant.creneaux.test.tsx` :

**a) Dans la section `describe('RuleAccordion', ...)` :** ajouter `onGenerate: vi.fn()` à tous les `React.createElement(RuleAccordion, { ... })` existants.

```ts
// Chaque createElement pour RuleAccordion doit inclure :
onGenerate: vi.fn(),
```

**b) Ajouter un nouveau test dans `describe('RuleAccordion', ...)` :**

```ts
  it('shows generate section when rules exist', () => {
    render(
      React.createElement(RuleAccordion, {
        rules: [rule],
        onCreateRule: vi.fn(),
        onDeleteRule: vi.fn(),
        onGenerate: vi.fn(),
      }),
    );
    fireEvent.click(screen.getByText('Règles récurrentes'));
    expect(screen.getByText(/générer les créneaux/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /1 mois/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /3 mois/i })).toBeInTheDocument();
  });

  it('calls onGenerate(1) when "1 mois" is clicked', async () => {
    const onGenerate = vi.fn().mockResolvedValue({
      store_id: 'store-1', generated_count: 4,
      skipped_existing_count: 0, skipped_closure_count: 0,
      horizon_start: '', horizon_end: '',
    });
    render(
      React.createElement(RuleAccordion, {
        rules: [rule],
        onCreateRule: vi.fn(),
        onDeleteRule: vi.fn(),
        onGenerate,
      }),
    );
    fireEvent.click(screen.getByText('Règles récurrentes'));
    fireEvent.click(screen.getByRole('button', { name: /1 mois/i }));
    await waitFor(() => expect(onGenerate).toHaveBeenCalledWith(1));
  });
```

**c) Remplacer le test page `'shows GenerateBanner after creating a rule'` :**

```ts
  it('calls onCreateRule for each selected weekday in RuleForm', async () => {
    vi.mocked(createMerchantSlotRule).mockResolvedValue(rule);
    vi.mocked(listMerchantSlotRules).mockResolvedValue({ total: 0, items: [] });

    render(React.createElement(MerchantCreneauxPage));
    await waitFor(() => screen.getByRole('heading', { name: 'Créneaux' }));
    await waitFor(() => screen.getByText('Nouvelle règle'));
    fireEvent.click(screen.getByText('Nouvelle règle'));

    // Par défaut tous les jours sont sélectionnés → 7 appels
    fireEvent.click(screen.getByRole('button', { name: /ajouter la règle/i }));

    await waitFor(() =>
      expect(createMerchantSlotRule).toHaveBeenCalledTimes(7),
    );
  });
```

- [ ] **Step 2 : Lancer les tests pour vérifier les échecs**

```bash
cd apps/frontend && npm test -- merchant.creneaux 2>&1 | grep -E "FAIL|PASS|✓|✗" | head -20
```

Expected: les nouveaux tests échouent, les anciens passent (sauf ceux modifiés).

- [ ] **Step 3 : Mettre à jour `RuleAccordion.tsx`**

Ajouter l'import et le type :
```tsx
import type { CreateSlotRulePayload, GenerateSlotsResult, MerchantPickupSlotRule } from '@/lib/types/merchant-slots.types';

export interface RuleAccordionProps {
  rules: MerchantPickupSlotRule[];
  onCreateRule: (payload: CreateSlotRulePayload) => Promise<void>;
  onDeleteRule: (ruleId: string) => Promise<void>;
  onGenerate: (horizonMonths: 1 | 3) => Promise<GenerateSlotsResult>;
}
```

Ajouter le composant `GenerateSection` en haut du fichier (avant `RuleAccordion`) :

```tsx
function GenerateSection({ onGenerate }: { onGenerate: (h: 1 | 3) => Promise<GenerateSlotsResult> }) {
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<GenerateSlotsResult | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function handleGenerate(months: 1 | 3) {
    setLoading(true);
    setError(null);
    setResult(null);
    try {
      setResult(await onGenerate(months));
    } catch {
      setError('La génération a échoué. Réessayez.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="mt-4 border-t border-line pt-3">
      <p className="mb-2 text-xs font-bold uppercase tracking-wide text-muted">
        Générer les créneaux
      </p>
      <div className="flex gap-2">
        <Button size="md" onClick={() => handleGenerate(1)} disabled={loading}>
          {loading ? 'Génération…' : '1 mois'}
        </Button>
        <Button size="md" variant="ghost" onClick={() => handleGenerate(3)} disabled={loading}>
          3 mois
        </Button>
      </div>
      {result && (
        <p className="mt-2 text-xs font-bold text-primary">
          ✓ {result.generated_count} créneau{result.generated_count !== 1 ? 'x' : ''} généré{result.generated_count !== 1 ? 's' : ''}.
        </p>
      )}
      {error && <p role="alert" className="mt-2 text-xs text-danger">{error}</p>}
    </div>
  );
}
```

Dans `RuleAccordion`, ajouter `onGenerate` à la destructuration du props et rendre `<GenerateSection>` juste avant le bouton "Nouvelle règle" **quand `rules.length > 0`** :

```tsx
export function RuleAccordion({ rules, onCreateRule, onDeleteRule, onGenerate }: RuleAccordionProps) {
  // ... état inchangé ...

  // Dans le rendu, à la fin du panneau, avant le bouton "Nouvelle règle" :
  {rules.length > 0 && <GenerateSection onGenerate={onGenerate} />}
  // ... bouton Nouvelle règle inchangé
```

- [ ] **Step 4 : Mettre à jour `page.tsx`**

Supprimer `showBanner` et `GenerateBanner` du flux. Le fichier final :

```tsx
// Supprimer : import { GenerateBanner } ...
// Supprimer : const [showBanner, setShowBanner] = useState(false);
// Dans handleCreateRule : supprimer setShowBanner(true)
// Dans handleGenerate : ajouter le paramètre horizonMonths
  async function handleGenerate(horizonMonths: 1 | 3) {
    const result = await generateMerchantSlots(storeId, horizonMonths);
    void loadAll();
    return result;
  }
// Supprimer le bloc {showBanner && <GenerateBanner ... />}
// Passer onGenerate à RuleAccordion :
      <RuleAccordion
        rules={rules}
        onCreateRule={handleCreateRule}
        onDeleteRule={handleDeleteRule}
        onGenerate={handleGenerate}
      />
```

- [ ] **Step 5 : Relancer les tests**

```bash
cd apps/frontend && npm test -- merchant.creneaux 2>&1 | grep -E "FAIL|PASS|Tests" | head -10
```

Expected: tous les tests passent.

---

## Task 8 : SlotCoverageWarning (frontend)

**Files:**
- Create: `apps/frontend/src/components/merchant/SlotCoverageWarning.tsx`

- [ ] **Step 1 : Créer le composant `SlotCoverageWarning`**

```tsx
'use client';

import { useState } from 'react';
import { AlertTriangle } from 'lucide-react';
import Link from 'next/link';
import type { MerchantPickupSlot } from '@/lib/types/merchant-slots.types';

interface SlotCoverageWarningProps {
  slots: MerchantPickupSlot[];
}

export function SlotCoverageWarning({ slots }: SlotCoverageWarningProps) {
  const [dismissed, setDismissed] = useState(false);

  const now = new Date();
  const j6 = new Date(now);
  j6.setDate(j6.getDate() + 6);
  j6.setHours(23, 59, 59, 999);

  const hasSlot = slots.some((s) => {
    const start = new Date(s.starts_at);
    return start >= now && start <= j6;
  });

  if (hasSlot || dismissed) return null;

  return (
    <div
      role="alert"
      className="flex items-start gap-3 rounded-lg border border-danger/30 bg-danger/10 px-4 py-3"
    >
      <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-danger" aria-hidden="true" />
      <div className="flex-1">
        <p className="text-sm font-bold text-ink">
          Aucun créneau disponible dans les 6 prochains jours.
        </p>
        <p className="mt-0.5 text-xs text-muted">
          Vos clients ne pourront pas passer de commande.{' '}
          <Link href="/merchant/creneaux" className="font-bold text-primary hover:underline">
            Aller dans Créneaux
          </Link>{' '}
          pour générer 1 ou 3 mois de créneaux.
        </p>
      </div>
      <button
        type="button"
        onClick={() => setDismissed(true)}
        aria-label="Fermer l'avertissement"
        className="shrink-0 text-lg leading-none text-muted hover:text-ink"
      >
        ✕
      </button>
    </div>
  );
}
```

- [ ] **Step 2 : Lancer le lint**

```bash
cd apps/frontend && npm run lint 2>&1 | grep -i "slotcoverage\|error" | head -10
```

Expected: aucune erreur sur le nouveau fichier.

---

## Task 9 : MerchantShell — intégration warning (frontend)

**Files:**
- Modify: `apps/frontend/src/components/merchant/MerchantShell.tsx`

- [ ] **Step 1 : Ajouter les imports dans `MerchantShell.tsx`**

```tsx
import { listMerchantSlots } from '@/lib/services/merchant-slots.service';
import { SlotCoverageWarning } from './SlotCoverageWarning';
import type { MerchantPickupSlot } from '@/lib/types/merchant-slots.types';
```

- [ ] **Step 2 : Ajouter l'état et l'effet de chargement des slots**

Dans `MerchantShell`, après les états existants (`unreadNotifications`, etc.) :

```tsx
  const [slots, setSlots] = useState<MerchantPickupSlot[]>([]);

  useEffect(() => {
    const storeId = merchant?.store.id;
    if (!storeId) return;
    void listMerchantSlots(storeId).then(setSlots).catch(() => {
      // warning non affiché en cas d'erreur réseau — non bloquant
    });
  }, [merchant?.store.id]);
```

- [ ] **Step 3 : Insérer `<SlotCoverageWarning>` au-dessus de `<main>`**

Dans le JSX, juste avant `<main className="flex-1 overflow-auto p-4 md:p-6">{children}</main>` :

```tsx
        <SlotCoverageWarning slots={slots} />
        <main className="flex-1 overflow-auto p-4 md:p-6">{children}</main>
```

- [ ] **Step 4 : Lint**

```bash
cd apps/frontend && npm run lint 2>&1 | grep -i "merchantshell\|error" | head -10
```

Expected: aucune erreur.

---

## Task 10 : Tests + lint + commit frontend

- [ ] **Step 1 : Lancer toute la suite Vitest**

```bash
cd apps/frontend && npm test 2>&1 | tail -15
```

Expected: tous les tests passent, aucun FAIL.

- [ ] **Step 2 : Build Next.js pour détecter les erreurs TS**

```bash
cd apps/frontend && npm run build 2>&1 | grep -E "error|Error|warning" | grep -v "^>" | head -20
```

Expected: aucune erreur TypeScript ni d'import manquant.

- [ ] **Step 3 : Lint global**

```bash
cd apps/frontend && npm run lint 2>&1 | tail -5
```

Expected: `✔ No ESLint warnings or errors`

- [ ] **Step 4 : Commit frontend**

```bash
git add apps/frontend/src/components/merchant/creneaux/RuleForm.tsx \
        apps/frontend/src/components/merchant/creneaux/RuleAccordion.tsx \
        apps/frontend/src/app/merchant/creneaux/page.tsx \
        apps/frontend/src/lib/services/merchant-slot-rules.service.ts \
        apps/frontend/src/components/merchant/SlotCoverageWarning.tsx \
        apps/frontend/src/components/merchant/MerchantShell.tsx \
        apps/frontend/src/tests/merchant.creneaux.test.tsx \
        apps/frontend/src/tests/merchant.slot-rules.service.test.ts
git commit -m "feat(creneaux): multi-jours, génération 1/3 mois permanente, warning j+6 global"
```
