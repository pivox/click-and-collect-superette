<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Order;
use App\Entity\PickupSlot;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\OrderStatus;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class MerchantOrderExportApiTest extends FunctionalApiTestCase
{
    // ---- helpers -----------------------------------------------------------

    private function exportUrl(string $storeId, string $query = ''): string
    {
        $url = \sprintf('/api/merchant/stores/%s/orders/export.csv', $storeId);

        return '' !== $query ? $url.'?'.$query : $url;
    }

    private function createMerchant(string $suffix): User
    {
        return $this->createUser('merchant-export-'.$suffix.'@example.test', ['ROLE_MERCHANT']);
    }

    private function createCustomer(
        string $suffix,
        ?string $firstName = 'Ali',
        ?string $lastName = 'Ben Salah',
        ?string $phone = '+21622111000',
    ): User {
        $customer = $this->createUser('customer-export-'.$suffix.'@example.test', ['ROLE_CUSTOMER']);
        $customer->setFirstName($firstName)->setLastName($lastName)->setPhone($phone);
        $name = trim((string) $firstName.' '.(string) $lastName);
        $customer->setName('' !== $name ? $name : 'Customer');
        $this->entityManager->flush();

        return $customer;
    }

    private function createOrder(
        User $customer,
        Shop $shop,
        OrderStatus $status,
        \DateTimeImmutable $createdAt,
        ?PickupSlot $slot = null,
    ): Order {
        $order = (new Order())->setCustomer($customer)->setShop($shop);
        if (null !== $slot) {
            $order->setPickupSlot($slot);
        }
        $this->setPrivateProperty($order, 'status', $status);
        $this->setPrivateProperty($order, 'createdAt', $createdAt);
        $this->setPrivateProperty($order, 'updatedAt', $createdAt->modify('+5 minutes'));
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    private function createSlot(Shop $shop): PickupSlot
    {
        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt(new \DateTimeImmutable('2026-05-20T10:00:00+01:00'))
            ->setEndsAt(new \DateTimeImmutable('2026-05-20T10:30:00+01:00'))
            ->setCapacity(5);
        $this->entityManager->persist($slot);
        $this->entityManager->flush();

        return $slot;
    }

    /**
     * Captures the streamed body of a StreamedResponse using output buffering.
     * Falls back to getContent() for regular responses.
     */
    private function captureBody(Response $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    /**
     * Parses the CSV body into an array of rows (each row = assoc array keyed by header).
     *
     * @return list<array<string, string>>
     */
    private function parseCsvBody(string $body): array
    {
        $lines = explode("\n", trim($body));
        if ([] === $lines || '' === $lines[0]) {
            return [];
        }

        $header = str_getcsv(array_shift($lines), ';', '"', '\\');
        $rows = [];
        foreach ($lines as $line) {
            if ('' === trim($line)) {
                continue;
            }
            $values = str_getcsv($line, ';', '"', '\\');
            /** @var array<string, string> $row */
            $row = array_combine($header, $values);
            $rows[] = $row;
        }

        return $rows;
    }

    // ---- nominal export ----------------------------------------------------

    public function testNominalExportReturns200WithCsvContentType(): void
    {
        $merchant = $this->createMerchant('nominal');
        $shop = $this->createShop($merchant);
        $customer = $this->createCustomer('nominal');
        $this->createOrder($customer, $shop, OrderStatus::Submitted, new \DateTimeImmutable('2026-05-10T10:00:00+01:00'));

        $response = $this->requestJson('GET', $this->exportUrl($shop->getId()->toRfc4122(), 'date_from=2026-05-01&date_to=2026-05-31'), null, $merchant);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function testCsvFirstLineContainsExpectedHeaders(): void
    {
        $merchant = $this->createMerchant('headers');
        $shop = $this->createShop($merchant);

        $response = $this->requestJson('GET', $this->exportUrl($shop->getId()->toRfc4122(), 'date_from=2026-05-01&date_to=2026-05-31'), null, $merchant);

        self::assertSame(200, $response->getStatusCode());
        $content = $this->captureBody($response);
        $firstLine = explode("\n", $content)[0];
        $headers = str_getcsv($firstLine, ';', '"', '\\');

        self::assertSame([
            'order_id',
            'status',
            'customer_name',
            'customer_phone',
            'total_tnd',
            'pickup_starts_at',
            'pickup_ends_at',
            'created_at',
            'updated_at',
        ], $headers);
    }

    public function testExportContainsCorrectRowData(): void
    {
        $merchant = $this->createMerchant('rowdata');
        $shop = $this->createShop($merchant);
        $customer = $this->createCustomer('rowdata', 'Amira', 'Trabelsi', '+21698765432');
        $slot = $this->createSlot($shop);
        $order = $this->createOrder($customer, $shop, OrderStatus::Accepted, new \DateTimeImmutable('2026-05-15T09:00:00+01:00'), $slot);

        $response = $this->requestJson('GET', $this->exportUrl($shop->getId()->toRfc4122(), 'date_from=2026-05-01&date_to=2026-05-31'), null, $merchant);

        self::assertSame(200, $response->getStatusCode());
        $rows = $this->parseCsvBody($this->captureBody($response));
        self::assertCount(1, $rows);

        $row = $rows[0];
        self::assertSame($order->getId()->toRfc4122(), $row['order_id']);
        self::assertSame('accepted', $row['status']);
        self::assertSame('Amira Trabelsi', $row['customer_name']);
        self::assertSame('+21698765432', $row['customer_phone']);
        self::assertSame('0.000', $row['total_tnd']);
        self::assertSame($slot->getStartsAt()->format(\DateTimeInterface::ATOM), $row['pickup_starts_at']);
        self::assertSame($slot->getEndsAt()->format(\DateTimeInterface::ATOM), $row['pickup_ends_at']);
        self::assertNotEmpty($row['created_at']);
        self::assertNotEmpty($row['updated_at']);
    }

    // ---- date filter -------------------------------------------------------

    public function testExportFiltersOrdersByDateRange(): void
    {
        $merchant = $this->createMerchant('datefilter');
        $shop = $this->createShop($merchant);
        $customer = $this->createCustomer('datefilter');

        $this->createOrder($customer, $shop, OrderStatus::Submitted, new \DateTimeImmutable('2026-05-10T10:00:00+01:00'));
        $this->createOrder($customer, $shop, OrderStatus::Submitted, new \DateTimeImmutable('2026-06-01T10:00:00+01:00'));

        $response = $this->requestJson('GET', $this->exportUrl($shop->getId()->toRfc4122(), 'date_from=2026-05-01&date_to=2026-05-31'), null, $merchant);

        self::assertSame(200, $response->getStatusCode());
        $rows = $this->parseCsvBody($this->captureBody($response));
        self::assertCount(1, $rows);
    }

    // ---- status filter -----------------------------------------------------

    public function testExportFiltersOrdersByStatus(): void
    {
        $merchant = $this->createMerchant('statusfilter');
        $shop = $this->createShop($merchant);
        $customer = $this->createCustomer('statusfilter');

        $this->createOrder($customer, $shop, OrderStatus::Submitted, new \DateTimeImmutable('2026-05-10T10:00:00+01:00'));
        $this->createOrder($customer, $shop, OrderStatus::Completed, new \DateTimeImmutable('2026-05-12T10:00:00+01:00'));

        $response = $this->requestJson('GET', $this->exportUrl($shop->getId()->toRfc4122(), 'date_from=2026-05-01&date_to=2026-05-31&status=completed'), null, $merchant);

        self::assertSame(200, $response->getStatusCode());
        $rows = $this->parseCsvBody($this->captureBody($response));
        self::assertCount(1, $rows);
        self::assertSame('completed', $rows[0]['status']);
    }

    // ---- draft exclusion ---------------------------------------------------

    public function testDraftOrdersAreNotIncludedInExport(): void
    {
        $merchant = $this->createMerchant('draft');
        $shop = $this->createShop($merchant);
        $customer = $this->createCustomer('draft');

        $this->createOrder($customer, $shop, OrderStatus::Draft, new \DateTimeImmutable('2026-05-10T10:00:00+01:00'));
        $this->createOrder($customer, $shop, OrderStatus::Submitted, new \DateTimeImmutable('2026-05-11T10:00:00+01:00'));

        $response = $this->requestJson('GET', $this->exportUrl($shop->getId()->toRfc4122(), 'date_from=2026-05-01&date_to=2026-05-31'), null, $merchant);

        self::assertSame(200, $response->getStatusCode());
        $rows = $this->parseCsvBody($this->captureBody($response));
        self::assertCount(1, $rows);
        self::assertSame('submitted', $rows[0]['status']);
    }

    // ---- CSV escaping (RFC 4180) -------------------------------------------

    public function testCsvValuesWithSemicolonAndQuotesAreEscaped(): void
    {
        $merchant = $this->createMerchant('escape');
        $shop = $this->createShop($merchant);
        $customer = $this->createCustomer('escape', 'Ali;Tricky', 'Ben"Quote"', '+21699000000');
        $this->createOrder($customer, $shop, OrderStatus::Submitted, new \DateTimeImmutable('2026-05-10T10:00:00+01:00'));

        $response = $this->requestJson('GET', $this->exportUrl($shop->getId()->toRfc4122(), 'date_from=2026-05-01&date_to=2026-05-31'), null, $merchant);

        self::assertSame(200, $response->getStatusCode());
        $rows = $this->parseCsvBody($this->captureBody($response));
        self::assertCount(1, $rows);
        self::assertSame('Ali;Tricky Ben"Quote"', $rows[0]['customer_name']);
    }

    // ---- validation errors -------------------------------------------------

    public function testMissingDateFromReturns400(): void
    {
        $merchant = $this->createMerchant('missing-from');
        $shop = $this->createShop($merchant);

        $response = $this->requestJson('GET', $this->exportUrl($shop->getId()->toRfc4122(), 'date_to=2026-05-31'), null, $merchant);

        self::assertSame(400, $response->getStatusCode());
    }

    public function testMissingDateToReturns400(): void
    {
        $merchant = $this->createMerchant('missing-to');
        $shop = $this->createShop($merchant);

        $response = $this->requestJson('GET', $this->exportUrl($shop->getId()->toRfc4122(), 'date_from=2026-05-01'), null, $merchant);

        self::assertSame(400, $response->getStatusCode());
    }

    public function testRangeExceeding92DaysReturns400(): void
    {
        $merchant = $this->createMerchant('range');
        $shop = $this->createShop($merchant);

        $response = $this->requestJson('GET', $this->exportUrl($shop->getId()->toRfc4122(), 'date_from=2026-01-01&date_to=2026-12-31'), null, $merchant);

        self::assertSame(400, $response->getStatusCode());
    }

    public function testInvalidStatusReturns400(): void
    {
        $merchant = $this->createMerchant('badstatus');
        $shop = $this->createShop($merchant);

        $response = $this->requestJson('GET', $this->exportUrl($shop->getId()->toRfc4122(), 'date_from=2026-05-01&date_to=2026-05-31&status=unknown'), null, $merchant);

        self::assertSame(400, $response->getStatusCode());
    }

    // ---- access control ----------------------------------------------------

    public function testUnknownStoreReturns404(): void
    {
        $merchant = $this->createMerchant('notfound');

        $response = $this->requestJson('GET', $this->exportUrl(Uuid::v4()->toRfc4122(), 'date_from=2026-05-01&date_to=2026-05-31'), null, $merchant);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testAnotherMerchantReturns403(): void
    {
        $merchantA = $this->createMerchant('owner-acl');
        $merchantB = $this->createMerchant('other-acl');
        $shopA = $this->createShop($merchantA);

        $response = $this->requestJson('GET', $this->exportUrl($shopA->getId()->toRfc4122(), 'date_from=2026-05-01&date_to=2026-05-31'), null, $merchantB);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testCustomerReturns403(): void
    {
        $merchant = $this->createMerchant('customer-acl');
        $shop = $this->createShop($merchant);
        $customer = $this->createUser('customer-export-acl@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson('GET', $this->exportUrl($shop->getId()->toRfc4122(), 'date_from=2026-05-01&date_to=2026-05-31'), null, $customer);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testAnonymousReturns401(): void
    {
        $merchant = $this->createMerchant('anon-acl');
        $shop = $this->createShop($merchant);

        $response = $this->requestJson('GET', $this->exportUrl($shop->getId()->toRfc4122(), 'date_from=2026-05-01&date_to=2026-05-31'));

        self::assertSame(401, $response->getStatusCode());
    }

    // ---- data privacy ------------------------------------------------------

    public function testResponseDoesNotContainSensitiveData(): void
    {
        $merchant = $this->createMerchant('privacy');
        $shop = $this->createShop($merchant);
        $customer = $this->createCustomer('privacy');
        $customer->setPassword('super-secret-hash');
        $this->entityManager->flush();
        $this->createOrder($customer, $shop, OrderStatus::Submitted, new \DateTimeImmutable('2026-05-10T10:00:00+01:00'));

        $response = $this->requestJson('GET', $this->exportUrl($shop->getId()->toRfc4122(), 'date_from=2026-05-01&date_to=2026-05-31'), null, $merchant);

        self::assertSame(200, $response->getStatusCode());
        $body = $this->captureBody($response);
        self::assertStringNotContainsString('super-secret-hash', $body);
        self::assertStringNotContainsString('@example.test', $body);
        self::assertStringNotContainsString('password', strtolower($body));
    }

    // ---- empty result ------------------------------------------------------

    public function testEmptyRangeReturnsOnlyHeaderLine(): void
    {
        $merchant = $this->createMerchant('empty');
        $shop = $this->createShop($merchant);

        $response = $this->requestJson('GET', $this->exportUrl($shop->getId()->toRfc4122(), 'date_from=2026-05-01&date_to=2026-05-31'), null, $merchant);

        self::assertSame(200, $response->getStatusCode());
        $rows = $this->parseCsvBody($this->captureBody($response));
        self::assertCount(0, $rows);
    }
}
