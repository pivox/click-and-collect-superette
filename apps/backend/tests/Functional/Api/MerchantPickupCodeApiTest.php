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
use App\Enum\OrderStatus;
use App\Enum\ProductReferenceStatus;
use Symfony\Component\Uid\Uuid;

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

    public function testRedeemByCodeMarksPickupSessionAsUsedForCustomerStatus(): void
    {
        $merchant = $this->createUser('merchant-redeem-session@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-redeem-session@example.test', ['ROLE_CUSTOMER']);
        $order = $this->createReadyOrder($customer, $shop);
        $pickupSession = new PickupSession($order);
        $this->entityManager->persist($pickupSession);
        $this->entityManager->flush();

        $code = $order->getPickupCode();
        self::assertNotNull($code);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/redeem-by-code', $shop->getId()),
            ['pickupCode' => $code],
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $this->entityManager->refresh($pickupSession);

        self::assertTrue($pickupSession->isUsed());
        self::assertNotNull($pickupSession->getScannedAt());
        self::assertNotNull($pickupSession->getMerchantConfirmedAt());
        self::assertNotNull($pickupSession->getCustomerConfirmedAt());

        $statusResponse = $this->requestJson(
            'GET',
            \sprintf('/api/me/orders/%s/status', $order->getId()),
            null,
            $customer,
        );

        self::assertSame(200, $statusResponse->getStatusCode());
        $payload = $this->decodeJson($statusResponse);
        self::assertSame('completed', $payload['status']);
        self::assertTrue($payload['pickup_session']['exists']);
        self::assertTrue($payload['pickup_session']['is_scanned']);
        self::assertTrue($payload['pickup_session']['merchant_confirmed']);
        self::assertTrue($payload['pickup_session']['customer_confirmed']);
        self::assertTrue($payload['pickup_session']['is_used']);
        self::assertFalse($payload['pickup_session']['force_completed_by_merchant']);
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
        $id = Uuid::v4()->toRfc4122();
        $brand = (new Brand())->setCanonicalName('Brand '.$id)->setSlug('brand-pickup-'.$id);
        $category = (new Category())->setNameFr('Catégorie '.$id)->setSlug('cat-pickup-'.$id);
        $productRef = (new ProductReference())
            ->setNameFr('Produit test')
            ->setCategory($category)
            ->setBrand($brand)
            ->setStatus(ProductReferenceStatus::Approved);
        $product = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productRef)
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

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
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
