<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider;

use App\Entity\Incident;
use App\Entity\Order;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\IncidentType;
use App\Provider\AdminIncidentOutputFactory;
use App\Repository\IncidentNoteRepository;
use PHPUnit\Framework\TestCase;

final class AdminIncidentOutputFactoryTest extends TestCase
{
    public function testItBuildsListSummaryWithoutLoadingNotes(): void
    {
        $noteRepository = $this->createMock(IncidentNoteRepository::class);
        $noteRepository
            ->expects(self::never())
            ->method('findByIncidentOrdered');

        $output = (new AdminIncidentOutputFactory($noteRepository))->fromIncidentSummary($this->incident());

        self::assertSame('missing_product', $output->type);
        self::assertSame('open', $output->status);
        self::assertSame('merchant@example.test', $output->merchantEmail);
        self::assertSame('customer@example.test', $output->customerEmail);
        self::assertSame([], $output->notes);
        self::assertSame([], $output->history);
    }

    private function incident(): Incident
    {
        $merchant = (new User())
            ->setEmail('merchant@example.test')
            ->setName('Merchant')
            ->setPassword('password')
            ->setRoles(['ROLE_MERCHANT']);
        $customer = (new User())
            ->setEmail('customer@example.test')
            ->setName('Customer')
            ->setPassword('password')
            ->setRoles(['ROLE_CUSTOMER']);
        $shop = (new Shop())->setOwner($merchant);
        $order = (new Order())->setShop($shop)->setCustomer($customer);

        return Incident::open(
            $order,
            IncidentType::MissingProduct,
            new \DateTimeImmutable('2026-06-05T12:30:00+01:00'),
            new \DateTimeImmutable('2026-06-05T13:30:00+01:00'),
        );
    }
}
