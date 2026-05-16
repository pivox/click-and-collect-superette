<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Notification;
use App\Entity\Order;
use App\Entity\User;
use App\Tests\Functional\OrderPickupFixtureTrait;
use Symfony\Component\Uid\Uuid;

final class CustomerNotificationApiTest extends FunctionalApiTestCase
{
    use OrderPickupFixtureTrait;
    // --- GET /api/me/notifications ---

    public function testCustomerReceivesNotificationAfterOrderAccepted(): void
    {
        $customer = $this->createUser('cust-notif-accepted@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-notif-accepted@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        $order = $this->createSubmittedOrder($customer, $shop, $product);
        $order->accept();
        $this->createNotificationForCustomer($customer, $order, 'Kadhia acceptée', 'تم قبول القاضية');

        $response = $this->requestJson('GET', '/api/me/notifications', null, $customer);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertArrayHasKey('items', $payload);
        self::assertArrayHasKey('total', $payload);
        self::assertArrayHasKey('page', $payload);
        self::assertCount(1, $payload['items']);
        self::assertSame('Kadhia acceptée', $payload['items'][0]['title_fr']);
        self::assertSame('تم قبول القاضية', $payload['items'][0]['title_ar']);
        self::assertFalse($payload['items'][0]['is_read']);
    }

    public function testCustomerSeesOnlyOwnNotifications(): void
    {
        $customerA = $this->createUser('cust-own-a@example.test', ['ROLE_CUSTOMER']);
        $customerB = $this->createUser('cust-own-b@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-own@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        $orderA = $this->createSubmittedOrder($customerA, $shop, $product);
        $orderB = $this->createSubmittedOrder($customerB, $shop, $product);
        $this->createNotificationForCustomer($customerA, $orderA, 'Notif A', 'إشعار أ');
        $this->createNotificationForCustomer($customerB, $orderB, 'Notif B', 'إشعار ب');

        $response = $this->requestJson('GET', '/api/me/notifications', null, $customerA);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['items']);
        self::assertSame('Notif A', $payload['items'][0]['title_fr']);
        self::assertSame(1, $payload['total']);
    }

    public function testUnreadFilterReturnsOnlyUnread(): void
    {
        $customer = $this->createUser('cust-unread@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-unread@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        $order = $this->createSubmittedOrder($customer, $shop, $product);

        $unread = $this->createNotificationForCustomer($customer, $order, 'Non lue', 'غير مقروءة');
        $read = $this->createNotificationForCustomer($customer, $order, 'Lue', 'مقروءة');
        $read->markRead();
        $this->entityManager->flush();

        $response = $this->requestJson('GET', '/api/me/notifications?unread=true', null, $customer);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertCount(1, $payload['items']);
        self::assertSame('Non lue', $payload['items'][0]['title_fr']);
    }

    public function testAnonymousGetReturns401(): void
    {
        $response = $this->requestJson('GET', '/api/me/notifications');
        self::assertSame(401, $response->getStatusCode());
    }

    public function testMerchantCannotAccessCustomerNotifications(): void
    {
        $merchant = $this->createUser('merch-forbidden-notif@example.test', ['ROLE_MERCHANT']);
        $response = $this->requestJson('GET', '/api/me/notifications', null, $merchant);
        self::assertSame(403, $response->getStatusCode());
    }

    // --- PATCH /api/me/notifications/{id}/read ---

    public function testMarkReadSetsIsReadTrue(): void
    {
        $customer = $this->createUser('cust-mark-read@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-mark-read@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        $order = $this->createSubmittedOrder($customer, $shop, $product);
        $notification = $this->createNotificationForCustomer($customer, $order, 'Test', 'اختبار');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/notifications/%s/read', $notification->getId()->toRfc4122()),
            [],
            $customer,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertTrue($payload['is_read']);
        self::assertSame($notification->getId()->toRfc4122(), $payload['id']);
    }

    public function testMarkReadOfAnotherCustomerReturns404(): void
    {
        $customerA = $this->createUser('cust-read-a@example.test', ['ROLE_CUSTOMER']);
        $customerB = $this->createUser('cust-read-b@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-read-cross@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        $order = $this->createSubmittedOrder($customerA, $shop, $product);
        $notification = $this->createNotificationForCustomer($customerA, $order, 'Privé', 'خاص');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/notifications/%s/read', $notification->getId()->toRfc4122()),
            [],
            $customerB,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testMarkReadAnonymousReturns401(): void
    {
        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/me/notifications/%s/read', Uuid::v4()->toRfc4122()),
            [],
        );
        self::assertSame(401, $response->getStatusCode());
    }

    // --- PATCH /api/me/notifications/read-all ---

    public function testMarkAllReadReturns204(): void
    {
        $customer = $this->createUser('cust-read-all@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-read-all@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        $order = $this->createSubmittedOrder($customer, $shop, $product);
        $this->createNotificationForCustomer($customer, $order, 'A', 'أ');
        $this->createNotificationForCustomer($customer, $order, 'B', 'ب');

        $response = $this->requestJson('PATCH', '/api/me/notifications/read-all', [], $customer);

        self::assertSame(204, $response->getStatusCode());
    }

    public function testMarkAllReadMarksAllAsRead(): void
    {
        $customer = $this->createUser('cust-all-marked@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-all-marked@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        $order = $this->createSubmittedOrder($customer, $shop, $product);
        $this->createNotificationForCustomer($customer, $order, 'A', 'أ');
        $this->createNotificationForCustomer($customer, $order, 'B', 'ب');

        $this->requestJson('PATCH', '/api/me/notifications/read-all', [], $customer);

        $this->entityManager->clear();
        $response = $this->requestJson('GET', '/api/me/notifications?unread=true', null, $customer);
        $payload = $this->decodeJson($response);
        self::assertSame(0, $payload['total']);
    }

    public function testMarkAllReadDoesNotAffectOtherCustomers(): void
    {
        $customerA = $this->createUser('cust-all-a@example.test', ['ROLE_CUSTOMER']);
        $customerB = $this->createUser('cust-all-b@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-all-sep@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        $orderA = $this->createSubmittedOrder($customerA, $shop, $product);
        $orderB = $this->createSubmittedOrder($customerB, $shop, $product);
        $this->createNotificationForCustomer($customerA, $orderA, 'A', 'أ');
        $this->createNotificationForCustomer($customerB, $orderB, 'B', 'ب');

        $this->requestJson('PATCH', '/api/me/notifications/read-all', [], $customerA);

        $this->entityManager->clear();
        $response = $this->requestJson('GET', '/api/me/notifications?unread=true', null, $customerB);
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
    }

    public function testMarkAllReadAnonymousReturns401(): void
    {
        $response = $this->requestJson('PATCH', '/api/me/notifications/read-all', []);
        self::assertSame(401, $response->getStatusCode());
    }

    // --- Integration: notification created on order transitions ---

    public function testNotificationCreatedWhenOrderAccepted(): void
    {
        $customer = $this->createUser('cust-integ-accepted@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-integ-accepted@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        $this->createSubmittedOrder($customer, $shop, $product);

        $order = $this->entityManager->getRepository(Order::class)->findOneBy(['customer' => $customer]);
        self::assertNotNull($order);

        // Accept the order via API (route is POST)
        $this->requestJson(
            'POST',
            \sprintf('/api/merchant/stores/%s/orders/%s/accept', $shop->getId()->toRfc4122(), $order->getId()->toRfc4122()),
            [],
            $merchant,
        );

        $response = $this->requestJson('GET', '/api/me/notifications', null, $customer);
        $payload = $this->decodeJson($response);
        self::assertGreaterThanOrEqual(1, $payload['total']);

        $titles = array_column($payload['items'], 'title_fr');
        self::assertContains('Kadhia acceptée', $titles);
    }

    public function testNotificationForReadyOrderAppearsInList(): void
    {
        $customer = $this->createUser('cust-integ-ready@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createUser('merch-integ-ready@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);
        $order = $this->createSubmittedOrder($customer, $shop, $product);
        $this->createNotificationForCustomer(
            $customer,
            $order,
            'Kadhia prête',
            'القاضية واجدة',
        );

        $response = $this->requestJson('GET', '/api/me/notifications', null, $customer);
        $payload = $this->decodeJson($response);
        $titles = array_column($payload['items'], 'title_fr');
        self::assertContains('Kadhia prête', $titles);
        self::assertNotEmpty($payload['items'][0]['title_ar']);
        self::assertNotEmpty($payload['items'][0]['body_fr']);
        self::assertFalse($payload['items'][0]['is_read']);
    }

    // --- Fixtures ---

    private function createNotificationForCustomer(User $user, Order $order, string $titleFr, string $titleAr): Notification
    {
        $notification = new Notification(
            user: $user,
            titleFr: $titleFr,
            titleAr: $titleAr,
            bodyFr: 'Corps test',
            bodyAr: 'نص الاختبار',
            order: $order,
        );
        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }
}
