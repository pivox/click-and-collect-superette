<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Incident;
use App\Entity\Order;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\IncidentStatus;
use App\Enum\IncidentType;
use PHPUnit\Framework\TestCase;

final class IncidentTest extends TestCase
{
    public function testOpenIncidentCapturesOrderMerchantCustomerAndType(): void
    {
        $merchant = $this->createUser('merchant-incident@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer-incident@example.test', ['ROLE_CUSTOMER']);
        $shop = (new Shop())
            ->setName('Supérette Incident')
            ->setSlug('superette-incident')
            ->setQrCodeToken('qr-incident')
            ->setOwner($merchant);
        $order = (new Order())->setShop($shop)->setCustomer($customer);
        $occurredAt = new \DateTimeImmutable('2026-06-05T12:30:00+01:00');

        $incident = Incident::open($order, IncidentType::MissingProduct, $occurredAt);

        self::assertNotNull($incident->getId());
        self::assertSame($order, $incident->getOrder());
        self::assertSame($merchant, $incident->getMerchant());
        self::assertSame($customer, $incident->getCustomer());
        self::assertSame(IncidentType::MissingProduct, $incident->getType());
        self::assertSame(IncidentStatus::Open, $incident->getStatus());
        self::assertSame($occurredAt, $incident->getOccurredAt());
        self::assertSame($occurredAt, $incident->getCreatedAt());
        self::assertNull($incident->getClosedAt());
    }

    public function testIncidentCanMoveToInProgressAndClosed(): void
    {
        $incident = Incident::open($this->createOrder(), IncidentType::CustomerAbsent, new \DateTimeImmutable('2026-06-05T12:30:00+01:00'));
        $startedAt = new \DateTimeImmutable('2026-06-05T13:00:00+01:00');
        $closedAt = new \DateTimeImmutable('2026-06-05T14:00:00+01:00');

        $incident->startProcessing($startedAt);
        self::assertSame(IncidentStatus::InProgress, $incident->getStatus());
        self::assertSame($startedAt, $incident->getUpdatedAt());

        $incident->close($closedAt);
        self::assertSame(IncidentStatus::Closed, $incident->getStatus());
        self::assertSame($closedAt, $incident->getClosedAt());
        self::assertSame($closedAt, $incident->getUpdatedAt());
    }

    public function testClosedIncidentCannotBeReopenedByProcessingTransition(): void
    {
        $incident = Incident::open($this->createOrder(), IncidentType::LateCancellation, new \DateTimeImmutable('2026-06-05T12:30:00+01:00'));
        $incident->close(new \DateTimeImmutable('2026-06-05T13:00:00+01:00'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('INCIDENT_ALREADY_CLOSED');

        $incident->startProcessing(new \DateTimeImmutable('2026-06-05T14:00:00+01:00'));
    }

    public function testIncidentRequiresMerchantOwnerOnOrderShop(): void
    {
        $order = (new Order())
            ->setShop((new Shop())->setName('Sans owner')->setSlug('sans-owner')->setQrCodeToken('qr-sans-owner'))
            ->setCustomer($this->createUser('customer-no-owner@example.test', ['ROLE_CUSTOMER']));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('INCIDENT_ORDER_MERCHANT_REQUIRED');

        Incident::open($order, IncidentType::Other, new \DateTimeImmutable('2026-06-05T12:30:00+01:00'));
    }

    public function testIncidentCanBeOpenedForEverySupportedType(): void
    {
        $order = $this->createOrder();

        foreach (IncidentType::cases() as $type) {
            $incident = Incident::open($order, $type, new \DateTimeImmutable('2026-06-05T12:30:00+01:00'));

            self::assertSame($type, $incident->getType());
            self::assertSame(IncidentStatus::Open, $incident->getStatus());
        }
    }

    private function createOrder(): Order
    {
        $merchant = $this->createUser('merchant-'.bin2hex(random_bytes(4)).'@example.test', ['ROLE_MERCHANT']);
        $customer = $this->createUser('customer-'.bin2hex(random_bytes(4)).'@example.test', ['ROLE_CUSTOMER']);
        $shop = (new Shop())
            ->setName('Supérette Test')
            ->setSlug('superette-test-'.bin2hex(random_bytes(4)))
            ->setQrCodeToken('qr-'.bin2hex(random_bytes(4)))
            ->setOwner($merchant);

        return (new Order())->setShop($shop)->setCustomer($customer);
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $email, array $roles): User
    {
        return (new User())
            ->setEmail($email)
            ->setRoles($roles)
            ->setPassword('test-password')
            ->setName('Test User');
    }
}
