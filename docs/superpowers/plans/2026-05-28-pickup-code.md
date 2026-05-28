# Retrait par code 4 chiffres — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettre au marchand de valider le retrait d'une commande avec un code à 4 chiffres (ou manuellement), en alternative au QR code.

**Architecture:** Le champ `pickupCode` est ajouté sur l'entité `Order` et généré lors du passage à `ready`. Deux nouveaux endpoints merchant permettent la validation par code et la validation manuelle. L'entité `PickupSession` et le flux QR existants restent intacts.

**Tech Stack:** Symfony 7 · API Platform 4 · Doctrine · Next.js 14 · TypeScript · Tailwind CSS

**Spec:** `docs/superpowers/specs/2026-05-28-pickup-code-design.md`

---

## Cartographie des fichiers

| Fichier | Action | Rôle |
|---|---|---|
| `apps/backend/src/Entity/Order.php` | Modifier | Ajouter `pickupCode`, `generatePickupCode()`, `redeemByCode()`, `completeManually()` |
| `apps/backend/migrations/Version20260528100000.php` | Créer | `ALTER TABLE orders ADD pickup_code VARCHAR(4)` |
| `apps/backend/src/Service/OrderTransitionService.php` | Modifier | Ajouter `completeByCode()`, `completeManually()` |
| `apps/backend/src/Repository/OrderRepository.php` | Modifier | Ajouter `findReadyByPickupCodeAndShop()` |
| `apps/backend/src/Dto/MerchantRedeemByCodeInput.php` | Créer | DTO input pour l'endpoint code |
| `apps/backend/src/ApiResource/MerchantRedeemByCodeOutput.php` | Créer | ApiResource + opération POST redeem-by-code |
| `apps/backend/src/Processor/MerchantRedeemByCodeProcessor.php` | Créer | Logique de l'endpoint code |
| `apps/backend/src/Dto/MerchantValidateManuallyInput.php` | Créer | DTO input pour l'endpoint manuel |
| `apps/backend/src/ApiResource/MerchantValidateManuallyOutput.php` | Créer | ApiResource + opération POST validate-manually |
| `apps/backend/src/Processor/MerchantValidateManuallyProcessor.php` | Créer | Logique de l'endpoint manuel |
| `apps/backend/src/ApiResource/OrderOutput.php` | Modifier | Ajouter champ `pickup_code` |
| `apps/backend/src/Factory/OrderOutputFactory.php` | Modifier | Passer `pickupCode` au constructeur |
| `apps/backend/tests/Unit/Entity/OrderTest.php` | Modifier | Tests unitaires domaine |
| `apps/backend/tests/Functional/Api/MerchantPickupCodeApiTest.php` | Créer | Tests fonctionnels endpoints |
| `apps/frontend/src/types/index.ts` | Modifier | Ajouter `pickupCode` à `Order` |
| `apps/frontend/src/lib/types/merchant.types.ts` | Modifier | Nouveaux types de réponse |
| `apps/frontend/src/lib/services/orders.service.ts` | Modifier | Mapper `pickup_code` depuis l'API |
| `apps/frontend/src/lib/mock/orders.mock.ts` | Modifier | Ajouter `pickupCode` au mock |
| `apps/frontend/src/lib/services/merchant-pickup.service.ts` | Modifier | Fonctions `redeemByCode`, `validateManually` |
| `apps/frontend/src/app/(client)/orders/[orderId]/page.tsx` | Modifier | Afficher bloc code quand `ready` |
| `apps/frontend/src/app/merchant/retrait/page.tsx` | Modifier | Ajouter onglets Code + Manuel |

---

## Task 1 — Domaine Order : champ pickupCode (TDD)

**Files:**
- Modify: `apps/backend/tests/Unit/Entity/OrderTest.php`
- Modify: `apps/backend/src/Entity/Order.php`

- [ ] **Step 1: Écrire les tests unitaires qui échouent**

Ajouter à la fin de `apps/backend/tests/Unit/Entity/OrderTest.php`, avant la dernière accolade `}` :

```php
// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

private function makeReadyOrder(): Order
{
    $shop = $this->makeShop();
    $order = (new Order())->setCustomer(new User())->setShop($shop);
    $order->submit();
    $order->accept();
    $order->startPreparing();
    $product = $this->makeProductForShop($shop);
    $line = (new OrderLine())
        ->setMerchantProduct($product)
        ->setQuantity(1)
        ->setUnitPriceTnd('1.000')
        ->setLineTotalTnd('1.000')
        ->markPrepared(true);
    $order->addLine($line);
    $order->recomputeTotal();
    $order->markReady();

    return $order;
}

// ---------------------------------------------------------------------------
// pickupCode
// ---------------------------------------------------------------------------

public function testMarkReadyGeneratesPickupCode(): void
{
    $order = $this->makeReadyOrder();
    self::assertNotNull($order->getPickupCode());
    self::assertMatchesRegularExpression('/^\d{4}$/', (string) $order->getPickupCode());
}

public function testRedeemByCodeTransitionsToCompleted(): void
{
    $order = $this->makeReadyOrder();
    $code = $order->getPickupCode();
    self::assertNotNull($code);
    $order->redeemByCode($code);
    self::assertSame(OrderStatus::Completed, $order->getStatus());
    self::assertNull($order->getPickupCode());
}

public function testRedeemByCodeThrowsOnWrongCode(): void
{
    $order = $this->makeReadyOrder();
    $correctCode = $order->getPickupCode() ?? '0000';
    $wrongCode = $correctCode === '1234' ? '5678' : '1234';
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('PICKUP_CODE_INVALID');
    $order->redeemByCode($wrongCode);
}

public function testRedeemByCodeThrowsWhenNotReady(): void
{
    $shop = $this->makeShop();
    $order = (new Order())->setCustomer(new User())->setShop($shop);
    $order->submit();
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('ORDER_NOT_READY');
    $order->redeemByCode('1234');
}

public function testCompleteManuallyTransitionsToCompleted(): void
{
    $order = $this->makeReadyOrder();
    $order->completeManually();
    self::assertSame(OrderStatus::Completed, $order->getStatus());
}

public function testCompleteManuallyThrowsWhenNotReady(): void
{
    $shop = $this->makeShop();
    $order = (new Order())->setCustomer(new User())->setShop($shop);
    $order->submit();
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('ORDER_NOT_READY');
    $order->completeManually();
}
```

Ajouter l'import manquant en haut du fichier (après les imports existants) :

```php
use App\Entity\User;
```

- [ ] **Step 2: Vérifier que les tests échouent**

```bash
cd apps/backend && vendor/bin/phpunit tests/Unit/Entity/OrderTest.php --filter "testMarkReadyGeneratesPickupCode|testRedeemByCode|testCompleteManually" 2>&1 | tail -20
```

Attendu : `ERRORS` ou `FAIL` — les méthodes n'existent pas encore.

- [ ] **Step 3: Implémenter les changements dans Order.php**

Dans `apps/backend/src/Entity/Order.php`, ajouter le champ après `$updatedAt` :

```php
#[ORM\Column(length: 4, nullable: true)]
private ?string $pickupCode = null;
```

Ajouter le getter après `getUpdatedAt()` :

```php
public function getPickupCode(): ?string
{
    return $this->pickupCode;
}
```

Modifier `markReady()` pour appeler `generatePickupCode()` — remplacer :

```php
    public function markReady(): void
    {
        if (OrderStatus::Preparing !== $this->status) {
            throw new \LogicException('ORDER_NOT_PREPARING');
        }
        if (!$this->areAllLinesPrepared()) {
            throw new \LogicException('ORDER_LINES_NOT_FULLY_PREPARED');
        }
        $this->status = OrderStatus::Ready;
    }
```

Par :

```php
    public function markReady(): void
    {
        if (OrderStatus::Preparing !== $this->status) {
            throw new \LogicException('ORDER_NOT_PREPARING');
        }
        if (!$this->areAllLinesPrepared()) {
            throw new \LogicException('ORDER_LINES_NOT_FULLY_PREPARED');
        }
        $this->status = OrderStatus::Ready;
        $this->pickupCode = \str_pad((string) \random_int(0, 9999), 4, '0', \STR_PAD_LEFT);
    }
```

Ajouter `redeemByCode()` et `completeManually()` avant la méthode `cancel()` :

```php
    public function redeemByCode(string $code): void
    {
        if (OrderStatus::Ready !== $this->status) {
            throw new \LogicException('ORDER_NOT_READY');
        }
        if ($this->pickupCode !== $code) {
            throw new \LogicException('PICKUP_CODE_INVALID');
        }
        $this->pickupCode = null;
        $this->status = OrderStatus::Completed;
    }

    public function completeManually(): void
    {
        if (OrderStatus::Ready !== $this->status) {
            throw new \LogicException('ORDER_NOT_READY');
        }
        $this->status = OrderStatus::Completed;
    }
```

- [ ] **Step 4: Vérifier que les tests passent**

```bash
cd apps/backend && vendor/bin/phpunit tests/Unit/Entity/OrderTest.php 2>&1 | tail -10
```

Attendu : `OK (N tests, N assertions)` — tous les tests Order passent.

- [ ] **Step 5: Commit**

```bash
cd apps/backend && git add src/Entity/Order.php tests/Unit/Entity/OrderTest.php
git commit -m "feat: Order — champ pickupCode généré à ready + redeemByCode/completeManually"
```

---

## Task 2 — Migration Doctrine

**Files:**
- Create: `apps/backend/migrations/Version20260528100000.php`

- [ ] **Step 1: Créer la migration**

```bash
cd apps/backend && cat > migrations/Version20260528100000.php << 'EOF'
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pickup_code column to orders table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD COLUMN pickup_code VARCHAR(4) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders DROP COLUMN pickup_code');
    }
}
EOF
```

- [ ] **Step 2: Valider le schéma**

```bash
cd apps/backend && symfony console doctrine:schema:validate 2>&1 | tail -10
```

Attendu : `[OK] The mapping files are correct.` et `[OK] The database schema is in sync with the mapping files.`

Si la DB locale est vide, faire d'abord :
```bash
symfony console doctrine:migrations:migrate --no-interaction
```

- [ ] **Step 3: Commit**

```bash
cd apps/backend && git add migrations/Version20260528100000.php
git commit -m "feat: migration — ajout pickup_code sur orders"
```

---

## Task 3 — OrderTransitionService : completeByCode + completeManually

**Files:**
- Modify: `apps/backend/src/Service/OrderTransitionService.php`

- [ ] **Step 1: Ajouter les deux méthodes après `markCompleted()`**

Dans `apps/backend/src/Service/OrderTransitionService.php`, ajouter après la méthode `markCompleted()` :

```php
    public function completeByCode(Order $order, string $code): void
    {
        $order->redeemByCode($code);
        $this->orderStatusLogRecorder->record($order, OrderStatus::Completed, 'withdrawal_validated_by_code');
        $this->notificationService->notifyCustomerOrderCompleted($order);
        $this->notificationService->notifyMerchantPickupCompleted($order);
    }

    public function completeManually(Order $order, string $note): void
    {
        $order->completeManually();
        $this->orderStatusLogRecorder->record(
            $order,
            OrderStatus::Completed,
            \sprintf('withdrawal_validated_manually: %s', $note),
        );
        $this->notificationService->notifyCustomerOrderCompleted($order);
        $this->notificationService->notifyMerchantPickupCompleted($order);
    }
```

- [ ] **Step 2: Vérifier PHPStan**

```bash
cd apps/backend && vendor/bin/phpstan analyse src/Service/OrderTransitionService.php --memory-limit=512M 2>&1 | tail -5
```

Attendu : `[OK] No errors`

- [ ] **Step 3: Commit**

```bash
cd apps/backend && git add src/Service/OrderTransitionService.php
git commit -m "feat: OrderTransitionService — completeByCode et completeManually"
```

---

## Task 4 — OrderRepository : findReadyByPickupCodeAndShop

**Files:**
- Modify: `apps/backend/src/Repository/OrderRepository.php`

- [ ] **Step 1: Ajouter la méthode de recherche**

Dans `apps/backend/src/Repository/OrderRepository.php`, ajouter après l'import `use App\Entity\Shop;` si absent (sinon en haut avec les autres uses), puis ajouter la méthode à la fin de la classe :

```php
    public function findReadyByPickupCodeAndShop(string $code, Shop $shop): ?Order
    {
        return $this->findOneBy([
            'pickupCode' => $code,
            'shop' => $shop,
            'status' => OrderStatus::Ready,
        ]);
    }
```

S'assurer que `use App\Entity\Shop;` est présent parmi les imports.

- [ ] **Step 2: Vérifier PHPStan**

```bash
cd apps/backend && vendor/bin/phpstan analyse src/Repository/OrderRepository.php --memory-limit=512M 2>&1 | tail -5
```

- [ ] **Step 3: Commit**

```bash
cd apps/backend && git add src/Repository/OrderRepository.php
git commit -m "feat: OrderRepository — findReadyByPickupCodeAndShop"
```

---

## Task 5 — Endpoint redeem-by-code (TDD)

**Files:**
- Create: `apps/backend/src/Dto/MerchantRedeemByCodeInput.php`
- Create: `apps/backend/src/ApiResource/MerchantRedeemByCodeOutput.php`
- Create: `apps/backend/src/Processor/MerchantRedeemByCodeProcessor.php`
- Create: `apps/backend/tests/Functional/Api/MerchantPickupCodeApiTest.php`

- [ ] **Step 1: Écrire les tests fonctionnels qui échouent**

Créer `apps/backend/tests/Functional/Api/MerchantPickupCodeApiTest.php` :

```php
<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\ProductReferenceStatus;

final class MerchantPickupCodeApiTest extends FunctionalApiTestCase
{
    // ---------------------------------------------------------------------------
    // POST /api/merchant/stores/{storeId}/orders/redeem-by-code
    // ---------------------------------------------------------------------------

    public function testRedeemByCodeHappyPath(): void
    {
        $merchant = $this->createUser('merchant-redeem@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-redeem@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createReadyOrder($customer, $shop);

        $code = $order->getPickupCode();
        self::assertNotNull($code);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/redeem-by-code', $shop->getId()),
            ['pickupCode' => $code],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($order->getId()->toRfc4122(), $payload['order_id']);
        self::assertSame('completed', $payload['status']);

        $this->entityManager->refresh($order);
        self::assertSame(OrderStatus::Completed, $order->getStatus());
        self::assertNull($order->getPickupCode());
    }

    public function testRedeemByCodeReturns404OnWrongCode(): void
    {
        $merchant = $this->createUser('merchant-redeem-wrong@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-redeem-wrong@example.test', ['ROLE_CUSTOMER']);
        $this->createReadyOrder($customer, $shop);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/redeem-by-code', $shop->getId()),
            ['pickupCode' => '0000'],
            $merchant,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testRedeemByCodeReturns403WhenNotOwner(): void
    {
        $merchant = $this->createUser('merchant-redeem-owner@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-redeem-other@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-redeem-403@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createReadyOrder($customer, $shop);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/redeem-by-code', $shop->getId()),
            ['pickupCode' => $order->getPickupCode()],
            $otherMerchant,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testRedeemByCodeReturns422OnInvalidFormat(): void
    {
        $merchant = $this->createUser('merchant-redeem-fmt@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/redeem-by-code', $shop->getId()),
            ['pickupCode' => 'ABC'],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    // ---------------------------------------------------------------------------
    // POST /api/merchant/stores/{storeId}/orders/{orderId}/validate-manually
    // ---------------------------------------------------------------------------

    public function testValidateManuallyHappyPath(): void
    {
        $merchant = $this->createUser('merchant-manual@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-manual@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createReadyOrder($customer, $shop);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/validate-manually', $shop->getId(), $order->getId()),
            ['note' => 'Client présent, QR inaccessible'],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($order->getId()->toRfc4122(), $payload['order_id']);
        self::assertSame('completed', $payload['status']);

        $this->entityManager->refresh($order);
        self::assertSame(OrderStatus::Completed, $order->getStatus());
    }

    public function testValidateManuallyReturns422WithoutNote(): void
    {
        $merchant = $this->createUser('merchant-manual-nonote@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-manual-nonote@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createReadyOrder($customer, $shop);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/validate-manually', $shop->getId(), $order->getId()),
            ['note' => ''],
            $merchant,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testValidateManuallyReturns409WhenNotReady(): void
    {
        $merchant = $this->createUser('merchant-manual-notready@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-manual-notready@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createSubmittedOrder($customer, $shop);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/validate-manually', $shop->getId(), $order->getId()),
            ['note' => 'Client présent'],
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
    }

    // ---------------------------------------------------------------------------
    // GET /api/me/orders/{id} — pickup_code exposé quand ready
    // ---------------------------------------------------------------------------

    public function testCustomerOrderDetailExposesPickupCodeWhenReady(): void
    {
        $merchant = $this->createUser('merchant-code-expose@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-code-expose@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createReadyOrder($customer, $shop);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s', $order->getId()),
            null,
            $customer,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertArrayHasKey('pickup_code', $payload);
        self::assertMatchesRegularExpression('/^\d{4}$/', (string) $payload['pickup_code']);
    }

    public function testCustomerOrderDetailDoesNotExposePickupCodeWhenNotReady(): void
    {
        $merchant = $this->createUser('merchant-code-hide@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-code-hide@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createSubmittedOrder($customer, $shop);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s', $order->getId()),
            null,
            $customer,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertArrayNotHasKey('pickup_code', $payload);
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function createReadyOrder(User $customer, Shop $shop): Order
    {
        $category = new Category();
        $category->setName('Test');
        $brand = new Brand();
        $brand->setName('Brand');
        $productRef = (new ProductReference())
            ->setNameFr('Produit test')
            ->setCategory($category)
            ->setBrand($brand)
            ->setStatus(ProductReferenceStatus::Approved);
        $product = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productRef)
            ->setIsAvailable(true)
            ->setPriceTnd('2.000');
        $order = (new Order())->setCustomer($customer)->setShop($shop);
        $order->submit();
        $order->accept();
        $order->startPreparing();
        $line = (new OrderLine())
            ->setMerchantProduct($product)
            ->setQuantity(1)
            ->setUnitPriceTnd('2.000')
            ->setLineTotalTnd('2.000')
            ->markPrepared(true);
        $order->addLine($line);
        $order->recomputeTotal();
        $order->markReady();

        $this->entityManager->persist($category);
        $this->entityManager->persist($brand);
        $this->entityManager->persist($productRef);
        $this->entityManager->persist($product);
        $this->entityManager->persist($order);
        $this->entityManager->persist($line);
        $this->entityManager->flush();

        return $order;
    }

    private function createSubmittedOrder(User $customer, Shop $shop): Order
    {
        $order = (new Order())->setCustomer($customer)->setShop($shop);
        $order->submit();
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }
}
```

- [ ] **Step 2: Vérifier que les tests échouent (endpoints non créés)**

```bash
cd apps/backend && vendor/bin/phpunit tests/Functional/Api/MerchantPickupCodeApiTest.php 2>&1 | tail -15
```

Attendu : `FAILURES` — 404 sur les nouveaux endpoints.

- [ ] **Step 3: Créer le DTO input**

Créer `apps/backend/src/Dto/MerchantRedeemByCodeInput.php` :

```php
<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MerchantRedeemByCodeInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(exactly: 4)]
        #[Assert\Regex('/^\d{4}$/')]
        public string $pickupCode = '',
    ) {
    }
}
```

- [ ] **Step 4: Créer l'ApiResource output**

Créer `apps/backend/src/ApiResource/MerchantRedeemByCodeOutput.php` :

```php
<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Dto\MerchantRedeemByCodeInput;
use App\Entity\Shop;
use App\Processor\MerchantRedeemByCodeProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/orders/redeem-by-code',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_redeem_by_code:read']],
            input: MerchantRedeemByCodeInput::class,
            status: 200,
            read: false,
            processor: MerchantRedeemByCodeProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantRedeemByCodeOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['merchant_redeem_by_code:read'])]
        #[SerializedName('order_id')]
        public string $orderId,
        #[Groups(['merchant_redeem_by_code:read'])]
        public string $status,
    ) {
    }
}
```

- [ ] **Step 5: Créer le processor**

Créer `apps/backend/src/Processor/MerchantRedeemByCodeProcessor.php` :

```php
<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantRedeemByCodeOutput;
use App\Dto\MerchantRedeemByCodeInput;
use App\Repository\OrderRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\OrderTransitionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantRedeemByCodeInput, MerchantRedeemByCodeOutput>
 */
final readonly class MerchantRedeemByCodeProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private OrderRepository $orderRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private OrderTransitionService $orderTransitionService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantRedeemByCodeOutput
    {
        if (!$data instanceof MerchantRedeemByCodeInput) {
            throw new \InvalidArgumentException('MerchantRedeemByCodeInput expected.');
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

        $order = $this->orderRepository->findReadyByPickupCodeAndShop($data->pickupCode, $shop);
        if (null === $order) {
            throw new NotFoundHttpException('PICKUP_CODE_NOT_FOUND');
        }

        try {
            $this->orderTransitionService->completeByCode($order, $data->pickupCode);
        } catch (\LogicException $e) {
            throw new NotFoundHttpException($e->getMessage());
        }

        $this->entityManager->flush();

        return new MerchantRedeemByCodeOutput(
            orderId: $order->getId()->toRfc4122(),
            status: $order->getStatus()->value,
        );
    }
}
```

- [ ] **Step 6: Vérifier les tests redeem-by-code**

```bash
cd apps/backend && vendor/bin/phpunit tests/Functional/Api/MerchantPickupCodeApiTest.php --filter "testRedeemByCode" 2>&1 | tail -10
```

Attendu : `OK` pour les 4 tests redeem-by-code.

- [ ] **Step 7: Commit**

```bash
cd apps/backend && git add src/Dto/MerchantRedeemByCodeInput.php src/ApiResource/MerchantRedeemByCodeOutput.php src/Processor/MerchantRedeemByCodeProcessor.php tests/Functional/Api/MerchantPickupCodeApiTest.php
git commit -m "feat: endpoint POST redeem-by-code (mode 2 — code 4 chiffres)"
```

---

## Task 6 — Endpoint validate-manually

**Files:**
- Create: `apps/backend/src/Dto/MerchantValidateManuallyInput.php`
- Create: `apps/backend/src/ApiResource/MerchantValidateManuallyOutput.php`
- Create: `apps/backend/src/Processor/MerchantValidateManuallyProcessor.php`

- [ ] **Step 1: Créer le DTO input**

Créer `apps/backend/src/Dto/MerchantValidateManuallyInput.php` :

```php
<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MerchantValidateManuallyInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 5, max: 500)]
        public string $note = '',
    ) {
    }
}
```

- [ ] **Step 2: Créer l'ApiResource output**

Créer `apps/backend/src/ApiResource/MerchantValidateManuallyOutput.php` :

```php
<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Dto\MerchantValidateManuallyInput;
use App\Entity\Shop;
use App\Processor\MerchantValidateManuallyProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/merchant/stores/{storeId}/orders/{orderId}/validate-manually',
            uriVariables: [
                'storeId' => new Link(fromClass: Shop::class, identifiers: ['id']),
                'orderId' => new Link(fromClass: MerchantValidateManuallyOutput::class, identifiers: ['id']),
            ],
            requirements: ['orderId' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'],
            formats: ['json' => ['application/json']],
            normalizationContext: ['groups' => ['merchant_validate_manually:read']],
            input: MerchantValidateManuallyInput::class,
            status: 200,
            read: false,
            processor: MerchantValidateManuallyProcessor::class,
            security: "is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final readonly class MerchantValidateManuallyOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['merchant_validate_manually:read'])]
        public string $id,
        #[Groups(['merchant_validate_manually:read'])]
        #[SerializedName('order_id')]
        public string $orderId,
        #[Groups(['merchant_validate_manually:read'])]
        public string $status,
    ) {
    }
}
```

- [ ] **Step 3: Créer le processor**

Créer `apps/backend/src/Processor/MerchantValidateManuallyProcessor.php` :

```php
<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantValidateManuallyOutput;
use App\Dto\MerchantValidateManuallyInput;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\OrderTransitionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantValidateManuallyInput, MerchantValidateManuallyOutput>
 */
final readonly class MerchantValidateManuallyProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private OrderRepository $orderRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private OrderTransitionService $orderTransitionService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantValidateManuallyOutput
    {
        if (!$data instanceof MerchantValidateManuallyInput) {
            throw new \InvalidArgumentException('MerchantValidateManuallyInput expected.');
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

        $orderId = (string) ($uriVariables['orderId'] ?? '');
        if (!Uuid::isValid($orderId)) {
            throw new NotFoundHttpException('ORDER_NOT_FOUND');
        }

        $order = $this->orderRepository->findOneBy(['id' => $orderId, 'shop' => $shop]);
        if (null === $order) {
            throw new NotFoundHttpException('ORDER_NOT_FOUND');
        }

        if (OrderStatus::Ready !== $order->getStatus()) {
            throw new ConflictHttpException('ORDER_NOT_READY');
        }

        $this->orderTransitionService->completeManually($order, $data->note);
        $this->entityManager->flush();

        return new MerchantValidateManuallyOutput(
            id: $order->getId()->toRfc4122(),
            orderId: $order->getId()->toRfc4122(),
            status: $order->getStatus()->value,
        );
    }
}
```

- [ ] **Step 4: Vérifier tous les tests pickup code**

```bash
cd apps/backend && vendor/bin/phpunit tests/Functional/Api/MerchantPickupCodeApiTest.php 2>&1 | tail -15
```

Attendu : tous les tests passent — `OK (N tests, N assertions)`.

- [ ] **Step 5: Commit**

```bash
cd apps/backend && git add src/Dto/MerchantValidateManuallyInput.php src/ApiResource/MerchantValidateManuallyOutput.php src/Processor/MerchantValidateManuallyProcessor.php
git commit -m "feat: endpoint POST validate-manually (mode 3 — validation manuelle)"
```

---

## Task 7 — Exposer pickup_code dans l'API client

**Files:**
- Modify: `apps/backend/src/ApiResource/OrderOutput.php`
- Modify: `apps/backend/src/Factory/OrderOutputFactory.php`

- [ ] **Step 1: Ajouter le champ dans OrderOutput**

Dans `apps/backend/src/ApiResource/OrderOutput.php`, ajouter le paramètre `pickupCode` après `updatedAt` dans le constructeur :

```php
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['order:read'])]
        public string $id,
        #[Groups(['order:read'])]
        #[SerializedName('kadhia_id')]
        public ?string $kadhiaId,
        #[Groups(['order:read'])]
        #[SerializedName('store_id')]
        public string $storeId,
        #[Groups(['order:read'])]
        public string $status,
        #[Groups(['order:read'])]
        #[SerializedName('total_tnd')]
        public string $totalTnd,
        #[Groups(['order:read'])]
        #[SerializedName('pickup_slot_id')]
        public ?string $pickupSlotId,
        #[Groups(['order:read'])]
        public ?string $notes,
        #[Groups(['order:read'])]
        public array $lines,
        #[Groups(['order:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
        #[Groups(['order:read'])]
        #[SerializedName('updated_at')]
        public string $updatedAt,
        #[Groups(['order:read'])]
        #[SerializedName('pickup_code')]
        public ?string $pickupCode = null,
    ) {
    }
```

- [ ] **Step 2: Mettre à jour OrderOutputFactory**

Dans `apps/backend/src/Factory/OrderOutputFactory.php`, modifier la méthode `toOutput()` pour passer `pickupCode`.

Remplacer :

```php
        return new OrderOutput(
            id: $order->getId()->toRfc4122(),
            kadhiaId: $order->getKadhia()?->getId()->toRfc4122(),
            storeId: $order->getShop()->getId()->toRfc4122(),
            status: $order->getStatus()->value,
            totalTnd: $order->getTotalTnd(),
            pickupSlotId: $slot?->getId()->toRfc4122(),
            notes: $order->getNotes(),
            lines: $lines,
            createdAt: $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $order->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
```

Par :

```php
        return new OrderOutput(
            id: $order->getId()->toRfc4122(),
            kadhiaId: $order->getKadhia()?->getId()->toRfc4122(),
            storeId: $order->getShop()->getId()->toRfc4122(),
            status: $order->getStatus()->value,
            totalTnd: $order->getTotalTnd(),
            pickupSlotId: $slot?->getId()->toRfc4122(),
            notes: $order->getNotes(),
            lines: $lines,
            createdAt: $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $order->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            pickupCode: $order->getPickupCode(),
        );
```

- [ ] **Step 3: Vérifier les tests customer pickup_code**

```bash
cd apps/backend && vendor/bin/phpunit tests/Functional/Api/MerchantPickupCodeApiTest.php --filter "testCustomerOrder" 2>&1 | tail -10
```

Attendu : `OK`.

- [ ] **Step 4: Commit**

```bash
cd apps/backend && git add src/ApiResource/OrderOutput.php src/Factory/OrderOutputFactory.php
git commit -m "feat: exposer pickup_code dans GET /api/me/orders/{id} quand ready"
```

---

## Task 8 — Qualité backend

- [ ] **Step 1: PHPStan**

```bash
cd apps/backend && vendor/bin/phpstan analyse --memory-limit=512M 2>&1 | tail -10
```

Attendu : `[OK] No errors`

- [ ] **Step 2: CS Fixer**

```bash
cd apps/backend && vendor/bin/php-cs-fixer fix --dry-run --diff 2>&1 | tail -20
```

Si des corrections sont nécessaires :

```bash
vendor/bin/php-cs-fixer fix
```

- [ ] **Step 3: Suite complète de tests**

```bash
cd apps/backend && vendor/bin/phpunit 2>&1 | tail -10
```

Attendu : `OK` — aucune régression.

- [ ] **Step 4: Commit si corrections CS Fixer**

```bash
cd apps/backend && git add -u && git commit -m "chore: CS Fixer — corrections style pickup code"
```

---

## Task 9 — Frontend : types et services

**Files:**
- Modify: `apps/frontend/src/types/index.ts`
- Modify: `apps/frontend/src/lib/types/merchant.types.ts`
- Modify: `apps/frontend/src/lib/services/orders.service.ts`
- Modify: `apps/frontend/src/lib/mock/orders.mock.ts`
- Modify: `apps/frontend/src/lib/services/merchant-pickup.service.ts`

- [ ] **Step 1: Ajouter pickupCode à l'interface Order**

Dans `apps/frontend/src/types/index.ts`, dans l'interface `Order`, ajouter après `customerNote` :

```typescript
  /** Code de retrait 4 chiffres, visible uniquement quand status === 'ready'. */
  pickupCode?: string | null;
```

- [ ] **Step 2: Ajouter les types de réponse merchant**

Dans `apps/frontend/src/lib/types/merchant.types.ts`, ajouter à la fin du fichier :

```typescript
export interface MerchantRedeemByCodeResult {
  order_id: string;
  status: string;
}

export interface MerchantValidateManuallyResult {
  id: string;
  order_id: string;
  status: string;
}
```

- [ ] **Step 3: Mettre à jour RawOrder et mapRawOrder dans orders.service.ts**

Dans `apps/frontend/src/lib/services/orders.service.ts`, ajouter `pickup_code` dans `RawOrder` :

```typescript
/** Raw shape returned by GET /api/me/orders and /api/me/orders/{id}. */
interface RawOrder {
  id: string;
  kadhia_id: string | null;
  store_id: string;
  status: string;
  total_tnd: string;
  pickup_slot_id: string | null;
  notes: string | null;
  lines: unknown[];
  created_at: string;
  updated_at: string;
  pickup_code?: string | null;
}
```

Dans `mapRawOrder()`, ajouter `pickupCode` dans l'objet retourné :

```typescript
function mapRawOrder(raw: RawOrder): Order {
  return {
    id: raw.id,
    shopId: raw.store_id,
    status: raw.status as Order["status"],
    totalAmountTnd: raw.total_tnd,
    pickupSlot: null,
    submittedAt: null,
    acceptedAt: null,
    readyAt: null,
    completedAt: null,
    rejectionReason: null,
    code: deriveCode(raw.id),
    customerNote: raw.notes ?? null,
    lines: [],
    pickupCode: raw.pickup_code ?? null,
  };
}
```

- [ ] **Step 4: Mettre à jour le mock orders**

Dans `apps/frontend/src/lib/mock/orders.mock.ts`, ajouter `pickupCode` dans `MOCK_ORDER` (le mock a `status: "ready"`) :

```typescript
  pickupCode: "4281",
```

- [ ] **Step 5: Ajouter les fonctions service merchant-pickup**

Dans `apps/frontend/src/lib/services/merchant-pickup.service.ts`, ajouter les imports et fonctions :

```typescript
import type {
  MerchantPickupSessionActionResult,
  MerchantPickupSessionForceCompleteResult,
  MerchantPickupSessionScanResult,
  MerchantRedeemByCodeResult,
  MerchantValidateManuallyResult,
} from '@/lib/types/merchant.types';

export async function redeemByCode(
  storeId: string,
  pickupCode: string,
): Promise<MerchantRedeemByCodeResult> {
  const { data } = await apiClient.post<MerchantRedeemByCodeResult>(
    `/api/merchant/stores/${storeId}/orders/redeem-by-code`,
    { pickupCode },
  );
  return data;
}

export async function validateManually(
  storeId: string,
  orderId: string,
  note: string,
): Promise<MerchantValidateManuallyResult> {
  const { data } = await apiClient.post<MerchantValidateManuallyResult>(
    `/api/merchant/stores/${storeId}/orders/${orderId}/validate-manually`,
    { note },
  );
  return data;
}
```

- [ ] **Step 6: Vérifier les types**

```bash
cd apps/frontend && npm run build 2>&1 | grep -E "error|Error|warn" | head -20
```

Attendu : build sans erreurs TypeScript.

- [ ] **Step 7: Commit**

```bash
cd apps/frontend && git add src/types/index.ts src/lib/types/merchant.types.ts src/lib/services/orders.service.ts src/lib/mock/orders.mock.ts src/lib/services/merchant-pickup.service.ts
git commit -m "feat(frontend): types et services pickup code + validation manuelle"
```

---

## Task 10 — Frontend client : bloc code 4 chiffres

**Files:**
- Modify: `apps/frontend/src/app/(client)/orders/[orderId]/page.tsx`

- [ ] **Step 1: Ajouter le bloc code dans la page de détail commande**

Dans `apps/frontend/src/app/(client)/orders/[orderId]/page.tsx`, dans la colonne droite (après `{order.customerNote && ...}`), ajouter le bloc pickup code avant le `{/* CTA inline sur desktop */}` :

```tsx
          {order.status === "ready" && order.pickupCode && (
            <section className="mt-4 md:mt-0">
              <h3 className="mb-2.5 text-h3 font-extrabold">Code de retrait</h3>
              <Card>
                <div className="flex flex-col items-center py-3 gap-3">
                  <div className="flex gap-2">
                    {order.pickupCode.split("").map((digit, i) => (
                      <span
                        key={i}
                        className="flex h-12 w-10 items-center justify-center rounded-md border-2 border-primary text-2xl font-black text-primary"
                      >
                        {digit}
                      </span>
                    ))}
                  </div>
                  <p className="text-center text-xs text-muted">
                    Communique ce code au marchand si le QR code ne peut pas
                    être scanné.
                  </p>
                </div>
              </Card>
            </section>
          )}
```

- [ ] **Step 2: Commit**

```bash
cd apps/frontend && git add "src/app/(client)/orders/[orderId]/page.tsx"
git commit -m "feat(frontend): bloc code 4 chiffres dans détail commande client"
```

---

## Task 11 — Frontend marchand : 3 onglets retrait

**Files:**
- Modify: `apps/frontend/src/app/merchant/retrait/page.tsx`

- [ ] **Step 1: Réécrire la page merchant retrait avec 3 onglets**

Remplacer le contenu complet de `apps/frontend/src/app/merchant/retrait/page.tsx` par :

```tsx
'use client';

import { useMemo, useState } from 'react';
import axios from 'axios';
import { Button } from '@/components/ui/Button';
import { formatTime, formatTnd } from '@/lib/format';
import {
  confirmMerchantPickupSession,
  forceCompleteMerchantPickupSession,
  redeemByCode,
  scanMerchantPickupSession,
  validateManually,
} from '@/lib/services/merchant-pickup.service';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import type {
  MerchantPickupSessionActionResult,
  MerchantPickupSessionForceCompleteResult,
  MerchantPickupSessionScanResult,
  MerchantRedeemByCodeResult,
  MerchantValidateManuallyResult,
} from '@/lib/types/merchant.types';

type Tab = 'qr' | 'code' | 'manual';

const UUID_PATTERN =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

function apiErrorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const detail = error.response?.data?.detail;
    if (typeof detail === 'string') return detail;
    if (error.response?.status === 404) return 'Code incorrect ou commande non éligible.';
    if (error.response?.status === 409) return 'La commande n\'est pas en état "prête".';
  }
  return "L'action n'a pas pu être effectuée. Réessaie.";
}

function fallbackOrderLabel(orderId: string | undefined): string {
  return orderId ? `#${orderId.slice(0, 8).toUpperCase()}` : '';
}

function lineTotalMillimes(line: MerchantPickupSessionScanResult['lines'][number]): number {
  return Math.round(Number.parseFloat(line.unit_price_tnd) * 1000) * line.quantity;
}

function lineTotalTnd(line: MerchantPickupSessionScanResult['lines'][number]): string {
  return (lineTotalMillimes(line) / 1000).toFixed(3);
}

function customerName(session: MerchantPickupSessionScanResult): string {
  return (
    [session.customer.first_name, session.customer.last_name].filter(Boolean).join(' ') ||
    'Client non renseigné'
  );
}

function actionStatusText(
  action: MerchantPickupSessionActionResult | MerchantPickupSessionForceCompleteResult | null,
): string {
  if (!action) return 'QR scanné. Retrait en attente de confirmation marchand.';
  if (action.is_completed) return 'Retrait finalisé.';
  if (action.merchant_confirmed_at) return 'Confirmation marchand enregistrée.';
  return 'Retrait en attente.';
}

const TABS: { id: Tab; label: string }[] = [
  { id: 'qr', label: 'QR Code' },
  { id: 'code', label: 'Code 4 chiffres' },
  { id: 'manual', label: 'Manuel' },
];

export default function MerchantPickupPage() {
  const { merchant } = useMerchantAuth();

  // QR tab state
  const [token, setToken] = useState('');
  const [session, setSession] = useState<MerchantPickupSessionScanResult | null>(null);
  const [actionResult, setActionResult] = useState<
    MerchantPickupSessionActionResult | MerchantPickupSessionForceCompleteResult | null
  >(null);
  const [forceNote, setForceNote] = useState('');
  const [isScanning, setIsScanning] = useState(false);

  // Code tab state
  const [pickupCode, setPickupCode] = useState('');
  const [codeResult, setCodeResult] = useState<MerchantRedeemByCodeResult | null>(null);
  const [isRedeemingCode, setIsRedeemingCode] = useState(false);

  // Manual tab state
  const [manualOrderId, setManualOrderId] = useState('');
  const [manualNote, setManualNote] = useState('');
  const [manualResult, setManualResult] = useState<MerchantValidateManuallyResult | null>(null);
  const [isValidatingManually, setIsValidatingManually] = useState(false);

  // Shared state
  const [activeTab, setActiveTab] = useState<Tab>('qr');
  const [error, setError] = useState<string | null>(null);
  const [isMutating, setIsMutating] = useState(false);

  const trimmedToken = token.trim();
  const orderLabel = session?.order_number ?? fallbackOrderLabel(session?.order_id);
  const canForceComplete =
    !!actionResult?.merchant_confirmed_at &&
    !actionResult.customer_confirmed_at &&
    !actionResult.is_completed;
  const totalTnd = useMemo(() => {
    if (!session) return '0.000';
    const total = session.lines.reduce((sum, line) => sum + lineTotalMillimes(line), 0);
    return (total / 1000).toFixed(3);
  }, [session]);

  const storeId = merchant?.store?.id ?? '';

  // QR actions
  const scan = async () => {
    setError(null);
    if (!UUID_PATTERN.test(trimmedToken)) {
      setError('Le token QR doit être un UUID valide.');
      return;
    }
    setIsScanning(true);
    try {
      setSession(await scanMerchantPickupSession(trimmedToken));
      setActionResult(null);
      setForceNote('');
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setIsScanning(false);
    }
  };

  const confirm = async () => {
    if (!session) return;
    setIsMutating(true);
    setError(null);
    try {
      setActionResult(await confirmMerchantPickupSession(session.id));
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setIsMutating(false);
    }
  };

  const forceComplete = async () => {
    if (!session) return;
    const note = forceNote.trim();
    if (!note) {
      setError('La note est obligatoire pour forcer la finalisation.');
      return;
    }
    setIsMutating(true);
    setError(null);
    try {
      setActionResult(await forceCompleteMerchantPickupSession(session.id, note));
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setIsMutating(false);
    }
  };

  const resetQr = () => {
    setToken('');
    setSession(null);
    setActionResult(null);
    setForceNote('');
    setError(null);
  };

  // Code action
  const handleRedeemCode = async () => {
    setError(null);
    const code = pickupCode.trim();
    if (!/^\d{4}$/.test(code)) {
      setError('Le code doit être composé de 4 chiffres.');
      return;
    }
    if (!storeId) {
      setError('Supérette non identifiée. Reconnecte-toi.');
      return;
    }
    setIsRedeemingCode(true);
    try {
      const result = await redeemByCode(storeId, code);
      setCodeResult(result);
      setPickupCode('');
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setIsRedeemingCode(false);
    }
  };

  // Manual action
  const handleValidateManually = async () => {
    setError(null);
    const orderId = manualOrderId.trim();
    const note = manualNote.trim();
    if (!UUID_PATTERN.test(orderId)) {
      setError("L'identifiant de commande doit être un UUID valide.");
      return;
    }
    if (note.length < 5) {
      setError('La note est obligatoire (5 caractères minimum).');
      return;
    }
    if (!storeId) {
      setError('Supérette non identifiée. Reconnecte-toi.');
      return;
    }
    setIsValidatingManually(true);
    try {
      const result = await validateManually(storeId, orderId, note);
      setManualResult(result);
      setManualOrderId('');
      setManualNote('');
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setIsValidatingManually(false);
    }
  };

  const changeTab = (tab: Tab) => {
    setActiveTab(tab);
    setError(null);
  };

  return (
    <div className="mx-auto max-w-5xl">
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 className="text-h1 font-black">Retrait sécurisé</h1>
          <p className="mt-1 text-sm text-muted">
            Choisis le mode de validation adapté à la situation.
          </p>
        </div>
      </div>

      {/* Onglets */}
      <div className="mt-5 flex border-b border-line">
        {TABS.map((tab) => (
          <button
            key={tab.id}
            type="button"
            onClick={() => changeTab(tab.id)}
            className={[
              'px-4 py-2 text-sm font-bold transition-colors',
              activeTab === tab.id
                ? 'border-b-2 border-primary text-primary'
                : 'text-muted hover:text-ink',
            ].join(' ')}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {error && (
        <div
          role="alert"
          className="mt-4 rounded-md bg-status-cancel-bg px-4 py-3 text-sm text-status-cancel"
        >
          {error}
        </div>
      )}

      {/* Onglet QR */}
      {activeTab === 'qr' && (
        <div className="mt-5">
          <div className="flex items-center justify-between">
            <p className="text-sm text-muted">
              Colle le token du QR code présenté par le client.
            </p>
            {session && (
              <Button variant="ghost" size="md" onClick={resetQr} disabled={isMutating}>
                Scanner un autre QR
              </Button>
            )}
          </div>

          <section className="mt-3 rounded-md bg-card p-5 shadow-card">
            <label htmlFor="pickup-token" className="text-sm font-black text-ink">
              Token QR de retrait
            </label>
            <div className="mt-2 grid gap-3 md:grid-cols-[1fr_auto]">
              <input
                id="pickup-token"
                type="text"
                value={token}
                onChange={(event) => setToken(event.currentTarget.value)}
                placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                className="min-h-[44px] rounded-md border border-line bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-primary"
              />
              <Button
                size="md"
                disabled={!trimmedToken || isScanning || isMutating}
                onClick={() => void scan()}
              >
                Identifier la Kadhia
              </Button>
            </div>
          </section>

          {session && (
            <section className="mt-5 grid gap-4 lg:grid-cols-[1fr_360px]">
              <div className="rounded-md bg-card shadow-card">
                <div className="border-b border-line p-5">
                  <p className="text-xs font-extrabold uppercase text-muted">Session de retrait</p>
                  <h2 className="mt-1 text-h2 font-black">Commande {orderLabel}</h2>
                  <p className="mt-1 text-sm text-muted">
                    Scan à {formatTime(session.scanned_at)} · statut {session.status}
                  </p>
                </div>
                <div className="grid gap-4 p-5 md:grid-cols-2">
                  <div className="rounded-md border border-line p-4">
                    <h3 className="font-black">Client</h3>
                    <p className="mt-2 text-sm text-muted">{customerName(session)}</p>
                    <p className="mt-1 text-sm text-muted">{session.customer.phone ?? '—'}</p>
                  </div>
                  <div className="rounded-md border border-line p-4">
                    <h3 className="font-black">État</h3>
                    <p className="mt-2 text-sm font-bold text-primary">
                      {actionStatusText(actionResult)}
                    </p>
                  </div>
                </div>
                <div className="border-t border-line">
                  <div className="flex items-center justify-between p-5">
                    <h3 className="text-lg font-black">Kadhia</h3>
                    <strong>{formatTnd(totalTnd)}</strong>
                  </div>
                  <div className="divide-y divide-line">
                    {session.lines.map((line) => (
                      <div key={line.merchant_product_id} className="grid gap-2 p-5 md:grid-cols-[1fr_auto]">
                        <div>
                          <strong>{line.name}</strong>
                          <p className="mt-1 text-sm text-muted">
                            {line.quantity} x {formatTnd(line.unit_price_tnd)}
                          </p>
                        </div>
                        <strong>{formatTnd(lineTotalTnd(line))}</strong>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
              <aside className="rounded-md bg-card p-5 shadow-card">
                <h3 className="text-lg font-black">Actions</h3>
                <div className="mt-4 space-y-3">
                  <Button full disabled={isMutating || !!actionResult?.merchant_confirmed_at} onClick={() => void confirm()}>
                    Remettre la Kadhia
                  </Button>
                  {actionResult?.is_completed && (
                    <p className="rounded-md bg-status-ready-bg px-3 py-2 text-sm font-bold text-status-ready">
                      Session clôturée.
                    </p>
                  )}
                  {canForceComplete && (
                    <div className="rounded-md border border-line p-3">
                      <label htmlFor="force-note" className="text-sm font-black">
                        Note de finalisation forcée
                      </label>
                      <textarea
                        id="force-note"
                        value={forceNote}
                        onChange={(event) => setForceNote(event.currentTarget.value)}
                        maxLength={500}
                        rows={4}
                        className="mt-2 w-full resize-y rounded-md border border-line px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                        placeholder="Ex. Client parti sans confirmer."
                      />
                      <Button full variant="danger" size="md" className="mt-3" disabled={isMutating} onClick={() => void forceComplete()}>
                        Forcer la finalisation
                      </Button>
                    </div>
                  )}
                </div>
              </aside>
            </section>
          )}
        </div>
      )}

      {/* Onglet Code 4 chiffres */}
      {activeTab === 'code' && (
        <div className="mt-5">
          <p className="text-sm text-muted">
            Le client te communique verbalement son code à 4 chiffres.
          </p>

          {codeResult ? (
            <div className="mt-4 rounded-md bg-status-ready-bg px-4 py-4 text-center">
              <p className="text-lg font-black text-status-ready">Retrait validé ✓</p>
              <p className="mt-1 text-sm text-muted">
                Commande #{codeResult.order_id.slice(0, 8).toUpperCase()} — {codeResult.status}
              </p>
              <Button
                variant="ghost"
                size="md"
                className="mt-3"
                onClick={() => { setCodeResult(null); setError(null); }}
              >
                Valider une autre commande
              </Button>
            </div>
          ) : (
            <section className="mt-3 rounded-md bg-card p-5 shadow-card">
              <label htmlFor="pickup-code" className="text-sm font-black text-ink">
                Code de retrait (4 chiffres)
              </label>
              <div className="mt-2 grid gap-3 md:grid-cols-[1fr_auto]">
                <input
                  id="pickup-code"
                  type="text"
                  inputMode="numeric"
                  pattern="\d{4}"
                  maxLength={4}
                  value={pickupCode}
                  onChange={(event) => setPickupCode(event.currentTarget.value.replace(/\D/g, ''))}
                  placeholder="1234"
                  className="min-h-[44px] rounded-md border border-line bg-white px-3 text-center text-xl font-black tracking-[0.5em] outline-none focus:ring-2 focus:ring-primary"
                />
                <Button
                  size="md"
                  disabled={pickupCode.length !== 4 || isRedeemingCode}
                  onClick={() => void handleRedeemCode()}
                >
                  Valider
                </Button>
              </div>
            </section>
          )}
        </div>
      )}

      {/* Onglet Manuel */}
      {activeTab === 'manual' && (
        <div className="mt-5">
          <p className="text-sm text-muted">
            Utilise ce mode si ni le QR code ni le code 4 chiffres ne fonctionnent. Une note est
            obligatoire pour l'audit.
          </p>

          {manualResult ? (
            <div className="mt-4 rounded-md bg-status-ready-bg px-4 py-4 text-center">
              <p className="text-lg font-black text-status-ready">Retrait validé manuellement ✓</p>
              <p className="mt-1 text-sm text-muted">
                Commande #{manualResult.order_id.slice(0, 8).toUpperCase()} — {manualResult.status}
              </p>
              <Button
                variant="ghost"
                size="md"
                className="mt-3"
                onClick={() => { setManualResult(null); setError(null); }}
              >
                Valider une autre commande
              </Button>
            </div>
          ) : (
            <section className="mt-3 rounded-md bg-card p-5 shadow-card space-y-4">
              <div>
                <label htmlFor="manual-order-id" className="text-sm font-black text-ink">
                  Identifiant de commande (UUID)
                </label>
                <input
                  id="manual-order-id"
                  type="text"
                  value={manualOrderId}
                  onChange={(event) => setManualOrderId(event.currentTarget.value)}
                  placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                  className="mt-2 w-full min-h-[44px] rounded-md border border-line bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-primary"
                />
              </div>
              <div>
                <label htmlFor="manual-note" className="text-sm font-black text-ink">
                  Motif (obligatoire, 5 caractères minimum)
                </label>
                <textarea
                  id="manual-note"
                  value={manualNote}
                  onChange={(event) => setManualNote(event.currentTarget.value)}
                  maxLength={500}
                  rows={3}
                  className="mt-2 w-full resize-y rounded-md border border-line bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                  placeholder="Ex. Client présent, QR inaccessible, caméra défaillante."
                />
              </div>
              <Button
                full
                disabled={isValidatingManually}
                onClick={() => void handleValidateManually()}
              >
                Valider manuellement
              </Button>
            </section>
          )}
        </div>
      )}
    </div>
  );
}
```

**Note :** `merchant.store.id` vient de `useMerchantAuth()` → `merchant: MerchantMe` → `store: MerchantStoreContext`. Ce champ est toujours présent après connexion marchand.

- [ ] **Step 2: Vérifier le build**

```bash
cd apps/frontend && npm run build 2>&1 | grep -E "error|Error" | head -20
```

- [ ] **Step 3: Lint**

```bash
cd apps/frontend && npm run lint 2>&1 | grep -E "error|Error" | head -20
```

- [ ] **Step 4: Commit**

```bash
cd apps/frontend && git add src/app/merchant/retrait/page.tsx
git commit -m "feat(frontend): page retrait — 3 onglets QR / Code 4 chiffres / Manuel"
```

---

## Vérification finale

- [ ] **Backend : suite complète**

```bash
cd apps/backend && vendor/bin/phpunit 2>&1 | tail -5
```

- [ ] **Backend : qualité**

```bash
cd apps/backend && vendor/bin/phpstan analyse --memory-limit=512M 2>&1 | tail -3 && vendor/bin/php-cs-fixer fix --dry-run --diff 2>&1 | tail -5
```

- [ ] **Frontend : build propre**

```bash
cd apps/frontend && npm run build 2>&1 | tail -10
```

- [ ] **Tag de fin**

```bash
git log --oneline -10
```

Vérifier que les commits couvrent : domaine, migration, service, endpoints, tests, frontend types/services, UI client, UI marchand.
