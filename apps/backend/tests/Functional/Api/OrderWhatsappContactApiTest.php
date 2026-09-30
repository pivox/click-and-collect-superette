<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\AdminAuditLog;
use App\Entity\Order;
use App\Entity\Shop;
use App\Entity\User;
use App\Tests\Functional\OrderPickupFixtureTrait;

final class OrderWhatsappContactApiTest extends FunctionalApiTestCase
{
    use OrderPickupFixtureTrait;

    private const NON_EXISTENT_UUID = '550e8400-e29b-41d4-a716-446655440000';

    // --- Customer endpoint: POST /api/me/orders/{orderId}/whatsapp-contact ---

    public function testCustomerPreparesWhatsappContactForOwnOrderWithPickupSlot(): void
    {
        $customer = $this->createUser('customer-whatsapp@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merchant-whatsapp@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $shop->setPhone('20 123 456');
        $order = $this->createNumberedOrder($customer, $shop, 42);

        $timezone = new \DateTimeZone('Africa/Tunis');
        $tomorrow = new \DateTimeImmutable('tomorrow 09:00:00', $timezone);
        $slot = $this->createPickupSlot($shop, $tomorrow);
        $order->setPickupSlot($slot);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/orders/%s/whatsapp-contact', $order->getId()->toRfc4122()),
            [],
            $customer,
        );

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);

        self::assertSame($order->getId()->toRfc4122(), $payload['order_id']);
        self::assertSame('21620123456', $payload['phone']);
        self::assertStringContainsString('ma commande #0042', $payload['message']);
        self::assertStringContainsString($shop->getName(), $payload['message']);
        self::assertStringContainsString(
            \sprintf('Retrait prévu le %s entre 09:00 et 10:00.', $tomorrow->format('d/m/Y')),
            $payload['message'],
        );
        self::assertStringStartsWith('https://wa.me/21620123456?text=', $payload['whatsapp_url']);

        $auditLog = $this->findWhatsappAuditLog($order);
        self::assertNotNull($auditLog);
        self::assertSame('customer_to_merchant', $auditLog->getMetadata()['direction'] ?? null);
        self::assertSame('customer', $auditLog->getMetadata()['actor_role'] ?? null);
        self::assertSame('21620123456', $auditLog->getMetadata()['phone'] ?? null);
        self::assertSame($shop->getId()->toRfc4122(), $auditLog->getMetadata()['shop_id'] ?? null);
    }

    public function testCustomerWhatsappContactWithoutPickupSlotOmitsPickupSentence(): void
    {
        $customer = $this->createUser('customer-whatsapp-noslot@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $shop->setPhone('20123456');
        $order = $this->createNumberedOrder($customer, $shop, 7);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/orders/%s/whatsapp-contact', $order->getId()->toRfc4122()),
            [],
            $customer,
        );

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertStringContainsString('ma commande #0007', $payload['message']);
        self::assertStringNotContainsString('Retrait prévu', $payload['message']);
    }

    public function testCustomerCannotPrepareWhatsappContactForForeignOrder(): void
    {
        $owner = $this->createUser('customer-whatsapp-owner@example.test', ['ROLE_CUSTOMER']);
        $intruder = $this->createUser('customer-whatsapp-intruder@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $shop->setPhone('20123456');
        $order = $this->createNumberedOrder($owner, $shop, 3);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/orders/%s/whatsapp-contact', $order->getId()->toRfc4122()),
            [],
            $intruder,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertNull($this->findWhatsappAuditLog($order));
    }

    public function testCustomerWhatsappContactReturns409WhenShopPhoneMissing(): void
    {
        $customer = $this->createUser('customer-whatsapp-nophone@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();
        $order = $this->createNumberedOrder($customer, $shop, 5);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/orders/%s/whatsapp-contact', $order->getId()->toRfc4122()),
            [],
            $customer,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('ORDER_WHATSAPP_SHOP_PHONE_MISSING', $this->decodeJson($response)['detail']);
        self::assertNull($this->findWhatsappAuditLog($order));
    }

    public function testCustomerWhatsappContactReturns404ForUnknownOrder(): void
    {
        $customer = $this->createUser('customer-whatsapp-unknown@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/me/orders/%s/whatsapp-contact', self::NON_EXISTENT_UUID),
            [],
            $customer,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    // --- Merchant endpoint: POST /api/merchant/stores/{storeId}/orders/{orderId}/whatsapp-contact ---

    public function testMerchantPreparesWhatsappContactForCustomerOfOwnOrder(): void
    {
        $merchant = $this->createUser('merchant-whatsapp-owner@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer-whatsapp-target@example.test', ['ROLE_CUSTOMER']);
        $customer->setPhone('98 765 432');
        $shop = $this->createShop($merchant);
        $order = $this->createNumberedOrder($customer, $shop, 42);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            \sprintf(
                '/api/merchant/stores/%s/orders/%s/whatsapp-contact',
                $shop->getId()->toRfc4122(),
                $order->getId()->toRfc4122(),
            ),
            [],
            $merchant,
        );

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);

        self::assertSame($order->getId()->toRfc4122(), $payload['order_id']);
        self::assertSame('21698765432', $payload['phone']);
        self::assertSame(
            \sprintf('Bonjour, ici %s au sujet de votre commande #0042.', $shop->getName()),
            $payload['message'],
        );
        self::assertStringStartsWith('https://wa.me/21698765432?text=', $payload['whatsapp_url']);

        $auditLog = $this->findWhatsappAuditLog($order);
        self::assertNotNull($auditLog);
        self::assertSame('merchant_to_customer', $auditLog->getMetadata()['direction'] ?? null);
        self::assertSame('merchant', $auditLog->getMetadata()['actor_role'] ?? null);
        self::assertSame('21698765432', $auditLog->getMetadata()['phone'] ?? null);
        self::assertSame($shop->getId()->toRfc4122(), $auditLog->getMetadata()['shop_id'] ?? null);
    }

    public function testMerchantCannotPrepareWhatsappContactForForeignShop(): void
    {
        $owner = $this->createUser('merchant-whatsapp-legit@example.test', ['ROLE_MERCHANT']);
        $intruder = $this->createUser('merchant-whatsapp-foreign@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer-whatsapp-foreign@example.test', ['ROLE_CUSTOMER']);
        $customer->setPhone('98765432');
        $shop = $this->createShop($owner);
        $order = $this->createNumberedOrder($customer, $shop, 8);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            \sprintf(
                '/api/merchant/stores/%s/orders/%s/whatsapp-contact',
                $shop->getId()->toRfc4122(),
                $order->getId()->toRfc4122(),
            ),
            [],
            $intruder,
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertNull($this->findWhatsappAuditLog($order));
    }

    public function testMerchantWhatsappContactReturns409WhenCustomerPhoneMissing(): void
    {
        $merchant = $this->createUser('merchant-whatsapp-nophone@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer-whatsapp-nophone-target@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop($merchant);
        $order = $this->createNumberedOrder($customer, $shop, 9);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            \sprintf(
                '/api/merchant/stores/%s/orders/%s/whatsapp-contact',
                $shop->getId()->toRfc4122(),
                $order->getId()->toRfc4122(),
            ),
            [],
            $merchant,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('ORDER_WHATSAPP_CUSTOMER_PHONE_MISSING', $this->decodeJson($response)['detail']);
        self::assertNull($this->findWhatsappAuditLog($order));
    }

    public function testMerchantWhatsappContactReturns404ForUnknownOrder(): void
    {
        $merchant = $this->createUser('merchant-whatsapp-unknown@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson(
            'POST',
            \sprintf(
                '/api/merchant/stores/%s/orders/%s/whatsapp-contact',
                $shop->getId()->toRfc4122(),
                self::NON_EXISTENT_UUID,
            ),
            [],
            $merchant,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    private function createNumberedOrder(User $customer, Shop $shop, int $orderNumber): Order
    {
        $product = $this->createMerchantProduct($shop);
        $order = $this->createSubmittedOrder($customer, $shop, $product);
        $order->assignOrderNumber($orderNumber);
        $this->entityManager->flush();

        return $order;
    }

    private function findWhatsappAuditLog(Order $order): ?AdminAuditLog
    {
        return $this->entityManager->getRepository(AdminAuditLog::class)->findOneBy([
            'action' => 'order.whatsapp_contact_prepared',
            'resourceType' => 'order',
            'resourceId' => $order->getId()->toRfc4122(),
        ]);
    }
}
