<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Order;
use App\Entity\PickupSlot;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\OrderStatus;

final class MerchantDashboardApiTest extends FunctionalApiTestCase
{
    public function testMerchantOwnerCanReadTodayDashboard(): void
    {
        $merchant = $this->createUser('merchant-dashboard-owner@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-dashboard-owner@example.test', ['ROLE_CUSTOMER']);

        $slot = $this->createPickupSlot($shop, '+1 hour', '+2 hours', capacity: 5, bookedCount: 2);
        $this->createOrder($customer, $shop, $slot, OrderStatus::Submitted);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/dashboard/today', $shop->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertSame((new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis')))->format('Y-m-d'), $payload['date']);
        self::assertSame(1, $payload['total_orders_today']);
        self::assertSame(1, $payload['submitted_count']);
        self::assertSame(0, $payload['accepted_count']);
        self::assertSame(1, $payload['orders_by_status']['submitted']);
        self::assertCount(1, $payload['pickup_slots_today']);
        self::assertSame($slot->getId()->toRfc4122(), $payload['pickup_slots_today'][0]['pickup_slot_id']);
        self::assertSame(5, $payload['pickup_slots_today'][0]['capacity']);
        self::assertSame(2, $payload['pickup_slots_today'][0]['booked_count']);
        self::assertSame(3, $payload['pickup_slots_today'][0]['remaining_capacity']);
    }

    public function testDashboardAccessIsForbiddenForAnotherMerchant(): void
    {
        $owner = $this->createUser('merchant-dashboard-owner-forbidden@example.test', ['ROLE_MERCHANT']);
        $otherMerchant = $this->createUser('merchant-dashboard-other-forbidden@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($owner);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/dashboard/today', $shop->getId()),
            null,
            $otherMerchant,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testDashboardAccessIsForbiddenForCustomer(): void
    {
        $customer = $this->createUser('customer-dashboard-forbidden@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/dashboard/today', $shop->getId()),
            null,
            $customer,
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testDashboardAccessRequiresAuthentication(): void
    {
        $shop = $this->createShop();

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/dashboard/today', $shop->getId()),
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testDashboardCountsOnlyTodayOrdersForTargetShopByStatus(): void
    {
        $merchant = $this->createUser('merchant-dashboard-counts@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $otherShop = $this->createShop($this->createUser('merchant-dashboard-counts-other@example.test', ['ROLE_MERCHANT']));
        $customer = $this->createUser('customer-dashboard-counts@example.test', ['ROLE_CUSTOMER']);

        $todaySlot = $this->createPickupSlot($shop, '+1 hour', '+2 hours');
        $todaySecondSlot = $this->createPickupSlot($shop, '+3 hours', '+4 hours');
        $yesterdaySlot = $this->createPickupSlot($shop, '-1 day +1 hour', '-1 day +2 hours');
        $otherShopSlot = $this->createPickupSlot($otherShop, '+1 hour', '+2 hours');

        $this->createOrder($customer, $shop, $todaySlot, OrderStatus::Submitted);
        $this->createOrder($customer, $shop, $todaySlot, OrderStatus::Submitted);
        $this->createOrder($customer, $shop, $todaySlot, OrderStatus::Accepted);
        $this->createOrder($customer, $shop, $todaySlot, OrderStatus::PartiallyAccepted);
        $this->createOrder($customer, $shop, $todaySecondSlot, OrderStatus::Preparing);
        $this->createOrder($customer, $shop, $todaySecondSlot, OrderStatus::Ready);
        $this->createOrder($customer, $shop, $todaySecondSlot, OrderStatus::Cancelled);
        $this->createOrder($customer, $shop, $todaySecondSlot, OrderStatus::Rejected);
        $this->createOrder($customer, $shop, $todaySecondSlot, OrderStatus::Completed);
        $this->createOrder($customer, $shop, $yesterdaySlot, OrderStatus::Submitted);
        $this->createOrder($customer, $otherShop, $otherShopSlot, OrderStatus::Submitted);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/dashboard/today', $shop->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame(9, $payload['total_orders_today']);
        self::assertSame(2, $payload['submitted_count']);
        self::assertSame(1, $payload['accepted_count']);
        self::assertSame(1, $payload['partially_accepted_count']);
        self::assertSame(1, $payload['preparing_count']);
        self::assertSame(1, $payload['ready_count']);
        self::assertSame(1, $payload['cancelled_count']);
        self::assertSame(1, $payload['rejected_count']);
        self::assertSame(1, $payload['completed_count']);
        self::assertSame(2, $payload['orders_by_status']['submitted']);
        self::assertSame(1, $payload['orders_by_status']['completed']);
    }

    public function testDashboardReturnsOnlyTargetShopPickupSlotsForToday(): void
    {
        $merchant = $this->createUser('merchant-dashboard-slots@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $otherShop = $this->createShop($this->createUser('merchant-dashboard-slots-other@example.test', ['ROLE_MERCHANT']));

        $todaySlot = $this->createPickupSlot($shop, '+1 hour', '+2 hours', capacity: 6, bookedCount: 4);
        $this->createPickupSlot($shop, '-1 day +1 hour', '-1 day +2 hours');
        $this->createPickupSlot($shop, '+1 day +1 hour', '+1 day +2 hours');
        $this->createPickupSlot($otherShop, '+1 hour', '+2 hours');

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/dashboard/today', $shop->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['pickup_slots_today']);
        self::assertSame($todaySlot->getId()->toRfc4122(), $payload['pickup_slots_today'][0]['pickup_slot_id']);
        self::assertSame(6, $payload['pickup_slots_today'][0]['capacity']);
        self::assertSame(4, $payload['pickup_slots_today'][0]['booked_count']);
        self::assertSame(2, $payload['pickup_slots_today'][0]['remaining_capacity']);
    }

    public function testDashboardCountsUrgentSubmittedOrdersWithinThreeHours(): void
    {
        $merchant = $this->createUser('merchant-dashboard-urgent@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-dashboard-urgent@example.test', ['ROLE_CUSTOMER']);

        $urgentSlot = $this->createPickupSlotFromNow($shop, '+2 hours', '+3 hours');
        $notUrgentSlot = $this->createPickupSlotFromNow($shop, '+4 hours', '+5 hours');

        $this->createOrder($customer, $shop, $urgentSlot, OrderStatus::Submitted);
        $this->createOrder($customer, $shop, $urgentSlot, OrderStatus::Accepted);
        $this->createOrder($customer, $shop, $notUrgentSlot, OrderStatus::Submitted);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/dashboard/today', $shop->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['urgent_submitted_count']);
    }

    public function testDashboardDoesNotExposeCustomerDataOrOrderLines(): void
    {
        $merchant = $this->createUser('merchant-dashboard-private@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-dashboard-private@example.test', ['ROLE_CUSTOMER']);
        $customer->setName('Client Dashboard')->setPhone('+21622123456');
        $this->entityManager->flush();

        $slot = $this->createPickupSlot($shop, '+1 hour', '+2 hours');
        $this->createOrder($customer, $shop, $slot, OrderStatus::Submitted);

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/dashboard/today', $shop->getId()),
            null,
            $merchant,
        );

        self::assertSame(200, $response->getStatusCode());

        $content = (string) $response->getContent();
        $payload = $this->decodeJson($response);

        self::assertArrayNotHasKey('customer_name', $payload);
        self::assertArrayNotHasKey('customer_phone', $payload);
        self::assertArrayNotHasKey('customer_email', $payload);
        self::assertArrayNotHasKey('lines', $payload);
        self::assertStringNotContainsString('Client Dashboard', $content);
        self::assertStringNotContainsString('+21622123456', $content);
        self::assertStringNotContainsString('customer-dashboard-private@example.test', $content);
    }

    private function createPickupSlot(
        Shop $shop,
        string $startsAtModifier,
        string $endsAtModifier,
        int $capacity = 5,
        int $bookedCount = 0,
    ): PickupSlot {
        $timezone = new \DateTimeZone('Africa/Tunis');
        $dayStart = (new \DateTimeImmutable('now', $timezone))->setTime(9, 0);
        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($dayStart->modify($startsAtModifier))
            ->setEndsAt($dayStart->modify($endsAtModifier))
            ->setCapacity($capacity);

        for ($i = 0; $i < $bookedCount; ++$i) {
            $slot->book();
        }

        $this->entityManager->persist($slot);
        $this->entityManager->flush();

        return $slot;
    }

    private function createOrder(User $customer, Shop $shop, PickupSlot $slot, OrderStatus $status): Order
    {
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setPickupSlot($slot);

        if (OrderStatus::Draft !== $status) {
            $order->submit();
        }

        if (!\in_array($status, [OrderStatus::Draft, OrderStatus::Submitted], true)) {
            $this->forceOrderStatus($order, $status);
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    private function createPickupSlotFromNow(
        Shop $shop,
        string $startsAtModifier,
        string $endsAtModifier,
        int $capacity = 5,
    ): PickupSlot {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis'));
        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify($startsAtModifier))
            ->setEndsAt($now->modify($endsAtModifier))
            ->setCapacity($capacity);

        $this->entityManager->persist($slot);
        $this->entityManager->flush();

        return $slot;
    }

    private function forceOrderStatus(Order $order, OrderStatus $status): void
    {
        $reflection = new \ReflectionProperty(Order::class, 'status');
        $reflection->setValue($order, $status);
    }
}
