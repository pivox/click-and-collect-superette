<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Order;
use App\Entity\PickupSlot;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Service\PickupSlotDisplayTime;
use Symfony\Component\Uid\Uuid;

final class MerchantOrderHistoryApiTest extends FunctionalApiTestCase
{
    public function testMerchantOwnerCanListCompleteHistoryWithAllOrderStatuses(): void
    {
        $merchant = $this->createUser('merchant-history-list@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createCustomer('customer-history-list@example.test', 'Ali', 'Ben Salah', '+21622111000');
        $slot = $this->createPickupSlot($shop);

        $statuses = [
            OrderStatus::Submitted,
            OrderStatus::Accepted,
            OrderStatus::PartiallyAccepted,
            OrderStatus::Preparing,
            OrderStatus::Ready,
            OrderStatus::PickupPending,
            OrderStatus::Completed,
            OrderStatus::Cancelled,
            OrderStatus::Rejected,
        ];

        foreach ($statuses as $index => $status) {
            $this->createOrder(
                $customer,
                $shop,
                $status,
                createdAt: new \DateTimeImmutable(\sprintf('2026-05-%02dT10:00:00+01:00', $index + 1)),
                pickupSlot: 0 === $index ? $slot : null,
            );
        }
        $this->createOrder($customer, $shop, OrderStatus::Draft, new \DateTimeImmutable('2026-05-20T10:00:00+01:00'));

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/history', $shop->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame(9, $payload['total']);
        self::assertSame(1, $payload['page']);
        self::assertSame(20, $payload['limit']);
        self::assertCount(9, $payload['items']);
        self::assertSame('rejected', $payload['items'][0]['status']);
        self::assertSame('submitted', $payload['items'][8]['status']);

        $returnedStatuses = array_column($payload['items'], 'status');
        foreach ($statuses as $status) {
            self::assertContains($status->value, $returnedStatuses);
        }
        self::assertNotContains('draft', $returnedStatuses);

        $submitted = $payload['items'][8];
        self::assertSame('Commande envoyée', $submitted['status_label_fr']);
        self::assertSame('تم إرسال الطلب', $submitted['status_label_ar']);
        self::assertSame('Ali', $submitted['customer']['first_name']);
        self::assertSame('Ben Salah', $submitted['customer']['last_name']);
        self::assertSame('+21622111000', $submitted['customer']['phone']);
        self::assertSame('0.000', $submitted['total']);
        self::assertSame(PickupSlotDisplayTime::toLocalAtom($slot->getStartsAt()), $submitted['pickup_slot']['starts_at']);
        self::assertSame(PickupSlotDisplayTime::toLocalAtom($slot->getEndsAt()), $submitted['pickup_slot']['ends_at']);

        $rejected = $payload['items'][0];
        self::assertSame('rejected', $rejected['status']);
        self::assertNull($rejected['customer']['first_name']);
        self::assertNull($rejected['customer']['last_name']);
        self::assertNull($rejected['customer']['phone']);
        self::assertArrayNotHasKey('lines', $submitted);
        self::assertArrayNotHasKey('customer_email', $submitted['customer']);
        self::assertArrayNotHasKey('password', $submitted['customer']);
        self::assertArrayNotHasKey('roles', $submitted['customer']);
    }

    public function testHistoryPaginationAndLimitCap(): void
    {
        $merchant = $this->createUser('merchant-history-pagination@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createCustomer('customer-history-pagination@example.test');

        for ($i = 1; $i <= 55; ++$i) {
            $this->createOrder(
                $customer,
                $shop,
                OrderStatus::Submitted,
                new \DateTimeImmutable(\sprintf('2026-05-17T10:%02d:00+01:00', $i % 60)),
            );
        }

        $cappedResponse = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/history?limit=100', $shop->getId()),
            null,
            $merchant,
        );
        self::assertSame(200, $cappedResponse->getStatusCode());
        $cappedPayload = $this->decodeJson($cappedResponse);
        self::assertSame(50, $cappedPayload['limit']);
        self::assertCount(50, $cappedPayload['items']);
        self::assertSame(55, $cappedPayload['total']);

        $pageResponse = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/history?page=2&limit=20', $shop->getId()),
            null,
            $merchant,
        );
        self::assertSame(200, $pageResponse->getStatusCode());
        $pagePayload = $this->decodeJson($pageResponse);
        self::assertSame(2, $pagePayload['page']);
        self::assertSame(20, $pagePayload['limit']);
        self::assertCount(20, $pagePayload['items']);
    }

    public function testHistoryFiltersByStatusDatesAndQuery(): void
    {
        $merchant = $this->createUser('merchant-history-filters@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customerAli = $this->createCustomer('ali-history-filters@example.test', 'Ali', 'Ben Salah', '+21622000001');
        $customerAmira = $this->createCustomer('amira-history-filters@example.test', 'Amira', 'Trabelsi', '+21622000002');

        $submittedAli = $this->createOrder($customerAli, $shop, OrderStatus::Submitted, new \DateTimeImmutable('2026-05-10T10:00:00+01:00'));
        $this->createOrder($customerAmira, $shop, OrderStatus::Accepted, new \DateTimeImmutable('2026-05-12T10:00:00+01:00'));
        $this->createOrder($customerAli, $shop, OrderStatus::Submitted, new \DateTimeImmutable('2026-06-10T10:00:00+01:00'));

        $statusResponse = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/history?status=accepted', $shop->getId()),
            null,
            $merchant,
        );
        self::assertSame(200, $statusResponse->getStatusCode());
        $statusPayload = $this->decodeJson($statusResponse);
        self::assertSame(1, $statusPayload['total']);
        self::assertSame('accepted', $statusPayload['items'][0]['status']);

        $dateResponse = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/history?date_from=2026-05-01&date_to=2026-05-31', $shop->getId()),
            null,
            $merchant,
        );
        self::assertSame(200, $dateResponse->getStatusCode());
        $datePayload = $this->decodeJson($dateResponse);
        self::assertSame(2, $datePayload['total']);

        $queryResponse = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/history?query=%s', $shop->getId(), urlencode('ben salah')),
            null,
            $merchant,
        );
        self::assertSame(200, $queryResponse->getStatusCode());
        $queryPayload = $this->decodeJson($queryResponse);
        self::assertSame(2, $queryPayload['total']);
        self::assertContains($submittedAli->getId()->toRfc4122(), array_column($queryPayload['items'], 'id'));

        $emptyQueryResponse = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/history?query=+++', $shop->getId()),
            null,
            $merchant,
        );
        self::assertSame(200, $emptyQueryResponse->getStatusCode());
        self::assertSame(3, $this->decodeJson($emptyQueryResponse)['total']);
    }

    public function testHistoryFiltersByMultipleCsvStatuses(): void
    {
        $merchant = $this->createUser('merchant-history-status-csv@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createCustomer('customer-history-status-csv@example.test');

        $ready = $this->createOrder($customer, $shop, OrderStatus::Ready, new \DateTimeImmutable('2026-05-10T10:00:00+01:00'));
        $pickupPending = $this->createOrder($customer, $shop, OrderStatus::PickupPending, new \DateTimeImmutable('2026-05-11T10:00:00+01:00'));
        $this->createOrder($customer, $shop, OrderStatus::Completed, new \DateTimeImmutable('2026-05-12T10:00:00+01:00'));

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/history?status=ready,pickup_pending', $shop->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(2, $payload['total']);
        self::assertCount(2, $payload['items']);

        $returnedIds = array_column($payload['items'], 'id');
        $returnedStatuses = array_column($payload['items'], 'status');

        self::assertContains($ready->getId()->toRfc4122(), $returnedIds);
        self::assertContains($pickupPending->getId()->toRfc4122(), $returnedIds);
        self::assertContains('ready', $returnedStatuses);
        self::assertContains('pickup_pending', $returnedStatuses);
        self::assertNotContains('completed', $returnedStatuses);

        $duplicateResponse = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/history?status=ready,ready', $shop->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $duplicateResponse->getStatusCode());
        $duplicatePayload = $this->decodeJson($duplicateResponse);
        self::assertSame(1, $duplicatePayload['total']);
        self::assertSame('ready', $duplicatePayload['items'][0]['status']);
    }

    public function testHistoryValidatesQueryParameters(): void
    {
        $merchant = $this->createUser('merchant-history-validation@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        foreach ([
            'status=unknown',
            'status=ready,unknown',
            'status=draft',
            'status=ready,draft',
            'date_from=not-a-date',
            'date_from=2026-02-31',
            'date_from=2026-05-01T00:00:00',
            'date_from=2026-05-20&date_to=2026-05-01',
            'page=0',
            'limit=0',
        ] as $query) {
            $response = $this->requestJson(
                'GET',
                \sprintf('/api/merchant/stores/%s/orders/history?%s', $shop->getId(), $query),
                null,
                $merchant,
            );

            self::assertSame(422, $response->getStatusCode(), $query.' should be rejected');
        }
    }

    public function testHistoryIsScopedToTargetShopAndMerchantOwner(): void
    {
        $merchantA = $this->createUser('merchant-history-a@example.test', ['ROLE_MERCHANT']);
        $merchantB = $this->createUser('merchant-history-b@example.test', ['ROLE_MERCHANT']);
        $shopA = $this->createShop($merchantA);
        $shopB = $this->createShop($merchantB);
        $customer = $this->createCustomer('customer-history-scope@example.test');

        $orderA = $this->createOrder($customer, $shopA, OrderStatus::Submitted, new \DateTimeImmutable('2026-05-10T10:00:00+01:00'));
        $this->createOrder($customer, $shopB, OrderStatus::Completed, new \DateTimeImmutable('2026-05-11T10:00:00+01:00'));

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/history', $shopA->getId()),
            null,
            $merchantA,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame($orderA->getId()->toRfc4122(), $payload['items'][0]['id']);

        $forbiddenResponse = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/history', $shopB->getId()),
            null,
            $merchantA,
        );
        self::assertSame(403, $forbiddenResponse->getStatusCode());
    }

    public function testHistoryQueryDoesNotLeakOrdersFromAnotherShop(): void
    {
        $merchantA = $this->createUser('merchant-history-query-a@example.test', ['ROLE_MERCHANT']);
        $merchantB = $this->createUser('merchant-history-query-b@example.test', ['ROLE_MERCHANT']);
        $shopA = $this->createShop($merchantA);
        $shopB = $this->createShop($merchantB);
        $customerA = $this->createCustomer('customer-history-query-a@example.test', 'Ali', 'Owner', '+21622000111');
        $customerB = $this->createCustomer('customer-history-query-b@example.test', 'Ali', 'Other', '+21622000222');

        $orderA = $this->createOrder($customerA, $shopA, OrderStatus::Submitted, new \DateTimeImmutable('2026-05-10T10:00:00+01:00'));
        $this->createOrder($customerB, $shopB, OrderStatus::Submitted, new \DateTimeImmutable('2026-05-11T10:00:00+01:00'));

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/history?query=Ali', $shopA->getId()),
            null,
            $merchantA,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame($orderA->getId()->toRfc4122(), $payload['items'][0]['id']);
    }

    public function testHistoryQueryEscapesLikeWildcards(): void
    {
        $merchant = $this->createUser('merchant-history-wildcards@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createCustomer('customer-history-wildcards@example.test', 'Ali', 'Owner', '+21622000111');
        $this->createOrder($customer, $shop, OrderStatus::Submitted, new \DateTimeImmutable('2026-05-10T10:00:00+01:00'));

        foreach (['%25', '_'] as $query) {
            $response = $this->requestJson(
                'GET',
                \sprintf('/api/merchant/stores/%s/orders/history?query=%s', $shop->getId(), $query),
                null,
                $merchant,
            );

            self::assertSame(200, $response->getStatusCode());
            self::assertSame(0, $this->decodeJson($response)['total']);
        }
    }

    public function testHistoryAccessControlAndUnknownShop(): void
    {
        $shop = $this->createShop($this->createUser('merchant-history-owner-acl@example.test', ['ROLE_MERCHANT']));
        $customer = $this->createUser('customer-history-acl@example.test', ['ROLE_CUSTOMER']);

        $anonymousResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/orders/history', $shop->getId()));
        self::assertSame(401, $anonymousResponse->getStatusCode());

        $customerResponse = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/history', $shop->getId()),
            null,
            $customer,
        );
        self::assertSame(403, $customerResponse->getStatusCode());

        $merchant = $this->createUser('merchant-history-unknown-shop@example.test', ['ROLE_MERCHANT']);
        $notFoundResponse = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders/history', Uuid::v4()->toRfc4122()),
            null,
            $merchant,
        );
        self::assertSame(404, $notFoundResponse->getStatusCode());
    }

    public function testOperationalOrderListStillWorks(): void
    {
        $merchant = $this->createUser('merchant-history-regression@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createCustomer('customer-history-regression@example.test');
        $this->createOrder($customer, $shop, OrderStatus::Submitted, new \DateTimeImmutable('2026-05-10T10:00:00+01:00'));

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/orders', $shop->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame('submitted', $payload['items'][0]['status']);
    }

    private function createCustomer(
        string $email,
        ?string $firstName = 'Haythem',
        ?string $lastName = 'Mabrouk',
        ?string $phone = '+21600000000',
    ): User {
        $customer = $this->createUser($email, ['ROLE_CUSTOMER']);
        $customer
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setName(trim((string) $firstName.' '.(string) $lastName))
            ->setPhone($phone);
        $this->entityManager->flush();

        return $customer;
    }

    private function createOrder(
        User $customer,
        Shop $shop,
        OrderStatus $status,
        \DateTimeImmutable $createdAt,
        ?PickupSlot $pickupSlot = null,
    ): Order {
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        if (null !== $pickupSlot) {
            $order->setPickupSlot($pickupSlot);
        }

        $this->setPrivateProperty($order, 'status', $status);
        $this->setPrivateProperty($order, 'createdAt', $createdAt);
        $this->setPrivateProperty($order, 'updatedAt', $createdAt->modify('+10 minutes'));

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    private function createPickupSlot(Shop $shop): PickupSlot
    {
        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt(new \DateTimeImmutable('2026-05-17T10:00:00+01:00'))
            ->setEndsAt(new \DateTimeImmutable('2026-05-17T10:30:00+01:00'))
            ->setCapacity(5);

        $this->entityManager->persist($slot);
        $this->entityManager->flush();

        return $slot;
    }
}
