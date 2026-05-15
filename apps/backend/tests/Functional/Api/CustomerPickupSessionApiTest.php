<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\PickupSession;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\ProductReferenceStatus;
use Symfony\Component\Uid\Uuid;

final class CustomerPickupSessionApiTest extends FunctionalApiTestCase
{
    public function testGetPickupSessionHappyPath(): void
    {
        $customer = $this->createUser('pickup-session-customer@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $order = $this->createReadyOrder($customer, $shop);
        $pickupSession = new PickupSession($order, new \DateTimeImmutable('2026-05-15 10:00:00'));
        $this->entityManager->persist($pickupSession);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/pickup-session', $order->getId()->toRfc4122()),
            user: $customer,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($pickupSession->getId()->toRfc4122(), $payload['id']);
        self::assertSame($pickupSession->getToken()->toRfc4122(), $payload['token']);
        self::assertSame($pickupSession->getToken()->toRfc4122(), $payload['qr_payload']);
        self::assertSame('2026-05-16T10:00:00+00:00', $payload['expires_at']);
        self::assertFalse($payload['is_used']);
        self::assertFalse($payload['is_expired']);
    }

    public function testGetPickupSessionForAnotherCustomerReturns404(): void
    {
        $customerA = $this->createUser('pickup-session-a@example.test', ['ROLE_CUSTOMER']);
        $customerB = $this->createUser('pickup-session-b@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $order = $this->createReadyOrder($customerB, $shop);
        $this->entityManager->persist(new PickupSession($order));
        $this->entityManager->flush();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/pickup-session', $order->getId()->toRfc4122()),
            user: $customerA,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('ORDER_NOT_FOUND', (string) $response->getContent());
    }

    public function testGetPickupSessionRejectsNonReadyOrder(): void
    {
        $customer = $this->createUser('pickup-session-non-ready@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $order = $this->createPreparingOrder($customer, $shop);
        $this->entityManager->persist(new PickupSession($order));
        $this->entityManager->flush();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/pickup-session', $order->getId()->toRfc4122()),
            user: $customer,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('ORDER_NOT_READY', (string) $response->getContent());
    }

    public function testGetPickupSessionReturns404WhenSessionIsMissing(): void
    {
        $customer = $this->createUser('pickup-session-missing@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $order = $this->createReadyOrder($customer, $shop);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/pickup-session', $order->getId()->toRfc4122()),
            user: $customer,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('PICKUP_SESSION_NOT_FOUND', (string) $response->getContent());
    }

    public function testGetPickupSessionUnauthenticatedReturns401(): void
    {
        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/pickup-session', Uuid::v4()->toRfc4122()),
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testGetPickupSessionMerchantRoleReturns403(): void
    {
        $merchant = $this->createUser('pickup-session-merchant@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/pickup-session', Uuid::v4()->toRfc4122()),
            user: $merchant,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    private function createReadyOrder(User $customer, Shop $shop): Order
    {
        $order = $this->createPreparingOrder($customer, $shop);
        foreach ($order->getLines() as $line) {
            $line->markPrepared(true);
        }
        $order->markReady();
        $this->entityManager->flush();

        return $order;
    }

    private function createPreparingOrder(User $customer, Shop $shop): Order
    {
        $product = $this->createMerchantProduct($shop);
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $order->submit();
        $order->accept();
        $order->startPreparing();

        $line = (new OrderLine())
            ->setMerchantProduct($product)
            ->setQuantity(1)
            ->setUnitPriceTnd('1.000')
            ->setLineTotalTnd('1.000');
        $order->addLine($line);
        $order->recomputeTotal();

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    private function createMerchantProduct(Shop $shop): MerchantProduct
    {
        $uniqueId = Uuid::v4();
        $brand = (new Brand())
            ->setCanonicalName('Marque QR '.$uniqueId)
            ->setSlug('marque-qr-'.$uniqueId);
        $category = (new Category())
            ->setNameFr('Catégorie QR '.$uniqueId)
            ->setSlug('categorie-qr-'.$uniqueId);
        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr('Produit QR')
            ->setStatus(ProductReferenceStatus::Approved);
        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd('1.000');

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($productReference);
        $this->entityManager->persist($merchantProduct);
        $this->entityManager->flush();

        return $merchantProduct;
    }
}
