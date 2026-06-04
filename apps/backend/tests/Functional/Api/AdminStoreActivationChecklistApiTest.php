<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\OrderStatusLog;
use App\Entity\PickupSession;
use App\Entity\PickupSlotRule;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class AdminStoreActivationChecklistApiTest extends FunctionalApiTestCase
{
    public function testAdminReadsReadyActivationChecklistForCompleteStore(): void
    {
        $admin = $this->createUser('admin-activation-ready@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-activation-ready@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer-activation-ready@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createCompleteStore($merchant, $customer);

        $response = $this->requestJson('GET', \sprintf('/api/admin/stores/%s/activation-checklist', $shop->getId()), user: $admin);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertSame($shop->getName(), $payload['store_name']);
        self::assertTrue($payload['ready']);
        self::assertSame(5, $payload['minimum_catalog_products']);
        self::assertSame(8, $payload['required_total_count']);
        self::assertSame(8, $payload['required_completed_count']);

        $steps = $this->indexStepsByKey($payload['steps']);
        foreach (['merchant_active', 'store_active', 'opening_hours', 'pickup_slots', 'catalog_minimum', 'qr_code', 'test_order', 'test_pickup'] as $key) {
            self::assertTrue($steps[$key]['completed'], \sprintf('Expected %s to be completed.', $key));
            self::assertTrue($steps[$key]['required']);
        }
    }

    public function testAdminReadsIncompleteActivationChecklistForPartialStore(): void
    {
        $admin = $this->createUser('admin-activation-incomplete@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-activation-incomplete@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $this->createMerchantProduct($shop, 'Produit unique');

        $response = $this->requestJson('GET', \sprintf('/api/admin/stores/%s/activation-checklist', $shop->getId()), user: $admin);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertFalse($payload['ready']);
        self::assertSame(3, $payload['required_completed_count']);

        $steps = $this->indexStepsByKey($payload['steps']);
        self::assertTrue($steps['merchant_active']['completed']);
        self::assertTrue($steps['store_active']['completed']);
        self::assertFalse($steps['opening_hours']['completed']);
        self::assertFalse($steps['pickup_slots']['completed']);
        self::assertFalse($steps['catalog_minimum']['completed']);
        self::assertSame(1, $steps['catalog_minimum']['current_value']);
        self::assertSame(5, $steps['catalog_minimum']['target_value']);
        self::assertTrue($steps['qr_code']['completed']);
        self::assertFalse($steps['test_order']['completed']);
        self::assertFalse($steps['test_pickup']['completed']);
    }

    public function testForceCompletedPickupDoesNotSatisfyValidatedTestPickup(): void
    {
        $admin = $this->createUser('admin-activation-force-pickup@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-activation-force-pickup@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer-activation-force-pickup@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createCompleteStore($merchant, $customer, forceCompletedPickup: true);

        $response = $this->requestJson('GET', \sprintf('/api/admin/stores/%s/activation-checklist', $shop->getId()), user: $admin);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        $steps = $this->indexStepsByKey($payload['steps']);
        self::assertFalse($payload['ready']);
        self::assertTrue($steps['test_order']['completed']);
        self::assertFalse($steps['test_pickup']['completed']);
    }

    public function testPickupCodeCompletionDoesNotSatisfyValidatedTestPickup(): void
    {
        $admin = $this->createUser('admin-activation-code-pickup@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-activation-code-pickup@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer-activation-code-pickup@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createCompleteStore($merchant, $customer, pickupCodeCompletion: true);

        $response = $this->requestJson('GET', \sprintf('/api/admin/stores/%s/activation-checklist', $shop->getId()), user: $admin);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        $steps = $this->indexStepsByKey($payload['steps']);
        self::assertFalse($payload['ready']);
        self::assertTrue($steps['test_order']['completed']);
        self::assertFalse($steps['test_pickup']['completed']);
    }

    public function testSameSecondDoubleValidationSatisfiesValidatedTestPickup(): void
    {
        $admin = $this->createUser('admin-activation-same-second-pickup@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-activation-same-second-pickup@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer-activation-same-second-pickup@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createCompleteStore($merchant, $customer, sameSecondPickupConfirmation: true);

        $response = $this->requestJson('GET', \sprintf('/api/admin/stores/%s/activation-checklist', $shop->getId()), user: $admin);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        $steps = $this->indexStepsByKey($payload['steps']);
        self::assertTrue($payload['ready']);
        self::assertTrue($steps['test_pickup']['completed']);
    }

    public function testSubmittedOnlyOrderDoesNotSatisfyTestOrder(): void
    {
        $admin = $this->createUser('admin-activation-submitted-order@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-activation-submitted-order@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer-activation-submitted-order@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createCompleteStore($merchant, $customer, submittedOnlyOrder: true);

        $response = $this->requestJson('GET', \sprintf('/api/admin/stores/%s/activation-checklist', $shop->getId()), user: $admin);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        $steps = $this->indexStepsByKey($payload['steps']);
        self::assertFalse($payload['ready']);
        self::assertFalse($steps['test_order']['completed']);
        self::assertFalse($steps['test_pickup']['completed']);
    }

    public function testInactiveStoreDoesNotSatisfyQrCodeAccess(): void
    {
        $admin = $this->createUser('admin-activation-inactive-qr@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('merchant-activation-inactive-qr@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant, active: false);

        $response = $this->requestJson('GET', \sprintf('/api/admin/stores/%s/activation-checklist', $shop->getId()), user: $admin);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $steps = $this->indexStepsByKey($this->decodeJson($response)['steps']);
        self::assertFalse($steps['store_active']['completed']);
        self::assertFalse($steps['qr_code']['completed']);
    }

    public function testActivationChecklistRequiresAdminAccess(): void
    {
        $merchant = $this->createUser('merchant-activation-forbidden@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer-activation-forbidden@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop($merchant);

        self::assertSame(
            Response::HTTP_UNAUTHORIZED,
            $this->requestJson('GET', \sprintf('/api/admin/stores/%s/activation-checklist', $shop->getId()))->getStatusCode(),
        );
        self::assertSame(
            Response::HTTP_FORBIDDEN,
            $this->requestJson('GET', \sprintf('/api/admin/stores/%s/activation-checklist', $shop->getId()), user: $merchant)->getStatusCode(),
        );
        self::assertSame(
            Response::HTTP_FORBIDDEN,
            $this->requestJson('GET', \sprintf('/api/admin/stores/%s/activation-checklist', $shop->getId()), user: $customer)->getStatusCode(),
        );
    }

    private function createCompleteStore(
        User $merchant,
        User $customer,
        bool $forceCompletedPickup = false,
        bool $pickupCodeCompletion = false,
        bool $submittedOnlyOrder = false,
        bool $sameSecondPickupConfirmation = false,
    ): Shop {
        $shop = $this->createShop($merchant);
        $shop
            ->setName('Supérette Activation Ready')
            ->setOpeningHours([
                'timezone' => 'Africa/Tunis',
                'weekly' => [
                    '1' => [['start' => '08:00', 'end' => '12:00']],
                ],
            ]);

        $this->entityManager->flush();

        for ($i = 1; $i <= 5; ++$i) {
            $this->createMerchantProduct($shop, 'Produit activation '.$i);
        }
        $this->createPickupSlotRule($shop);
        if ($submittedOnlyOrder) {
            $this->createSubmittedOrder($shop, $customer);
        } else {
            $this->createCompletedPickupOrder(
                $shop,
                $customer,
                forceCompleted: $forceCompletedPickup,
                pickupCodeCompletion: $pickupCodeCompletion,
                sameSecondPickupConfirmation: $sameSecondPickupConfirmation,
            );
        }

        return $shop;
    }

    private function createMerchantProduct(Shop $shop, string $name): MerchantProduct
    {
        $productReference = $this->createProductReference($name);
        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd('2.000')
            ->setAvailable(true)
            ->setVisible(true);

        $this->entityManager->persist($merchantProduct);
        $this->entityManager->flush();

        return $merchantProduct;
    }

    private function createProductReference(string $name): ProductReference
    {
        $id = Uuid::v4()->toRfc4122();
        $brand = (new Brand())
            ->setCanonicalName('Brand '.$id)
            ->setSlug('brand-'.$id)
            ->setActive(true);
        $category = (new Category())
            ->setNameFr('Catégorie '.$id)
            ->setSlug('categorie-'.$id)
            ->setActive(true);
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($name)
            ->setUnit(ProductUnit::Piece)
            ->setStatus(ProductReferenceStatus::Approved);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($productReference);
        $this->entityManager->flush();

        return $productReference;
    }

    private function createPickupSlotRule(Shop $shop): PickupSlotRule
    {
        $rule = (new PickupSlotRule())
            ->setShop($shop)
            ->setWeekday(1)
            ->setStartTime(new \DateTimeImmutable('1970-01-01 09:00:00'))
            ->setEndTime(new \DateTimeImmutable('1970-01-01 12:00:00'))
            ->setCapacity(5)
            ->setActive(true);

        $this->entityManager->persist($rule);
        $this->entityManager->flush();

        return $rule;
    }

    private function createSubmittedOrder(Shop $shop, User $customer): Order
    {
        $order = $this->createDraftOrderWithLine($shop, $customer);
        $order->submit();

        $this->entityManager->flush();

        return $order;
    }

    private function createCompletedPickupOrder(
        Shop $shop,
        User $customer,
        bool $forceCompleted = false,
        bool $pickupCodeCompletion = false,
        bool $sameSecondPickupConfirmation = false,
    ): Order {
        $order = $this->createDraftOrderWithLine($shop, $customer);
        $order->submit();
        $order->accept();
        $order->startPreparing();
        $order->markReady();

        if ($pickupCodeCompletion) {
            $pickupSession = new PickupSession($order);
            $pickupSession->completeByCode(new \DateTimeImmutable('2026-06-04T10:00:00+01:00'));
            $this->setPrivateProperty($order, 'status', OrderStatus::Completed);
            $completedLog = new OrderStatusLog($order, OrderStatus::Completed, 'withdrawal_validated_by_code');
            $this->entityManager->persist($pickupSession);
            $this->entityManager->persist($completedLog);
            $this->entityManager->flush();

            return $order;
        }

        $order->startPickup();
        $order->complete();

        $pickupSession = new PickupSession($order);
        $pickupSession->scan(new \DateTimeImmutable('2026-06-04T10:00:00+01:00'));
        if ($forceCompleted) {
            $pickupSession->forceCompleteByMerchant('Client indisponible pour validation test.', new \DateTimeImmutable('2026-06-04T10:03:00+01:00'));
        } elseif ($sameSecondPickupConfirmation) {
            $confirmedAt = new \DateTimeImmutable('2026-06-04T10:00:00+01:00');
            $pickupSession->confirmByMerchant($confirmedAt);
            $pickupSession->confirmByCustomer($confirmedAt);
        } else {
            $pickupSession->confirmByMerchant(new \DateTimeImmutable('2026-06-04T10:02:00+01:00'));
            $pickupSession->confirmByCustomer(new \DateTimeImmutable('2026-06-04T10:03:00+01:00'));
        }

        $this->entityManager->persist($pickupSession);
        $this->entityManager->flush();

        return $order;
    }

    private function createDraftOrderWithLine(Shop $shop, User $customer): Order
    {
        $merchantProduct = $this->createMerchantProduct($shop, 'Produit commande test');
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $line = (new OrderLine())
            ->setMerchantProduct($merchantProduct)
            ->setQuantity(1)
            ->setUnitPriceTnd('2.000')
            ->setLineTotalTnd('2.000')
            ->markPrepared(true);
        $order->addLine($line);
        $order->recomputeTotal();

        $this->entityManager->persist($order);
        $this->entityManager->persist($line);

        return $order;
    }

    /**
     * @param list<array<string, mixed>> $steps
     *
     * @return array<string, array<string, mixed>>
     */
    private function indexStepsByKey(array $steps): array
    {
        $indexed = [];
        foreach ($steps as $step) {
            $indexed[$step['key']] = $step;
        }

        return $indexed;
    }
}
