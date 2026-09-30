<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\MerchantMembership;
use App\Entity\MerchantOrganization;
use App\Entity\Shop;
use App\Enum\MerchantMembershipStatus;
use App\Tests\Functional\Api\FunctionalApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class MerchantOrganizationsBackfillCommandTest extends FunctionalApiTestCase
{
    public function testEmptyDatabaseProducesEmptyReport(): void
    {
        $tester = $this->runBackfill();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('organizations_created: 0', $tester->getDisplay());
        self::assertStringContainsString('orphan_shops: 0', $tester->getDisplay());
    }

    public function testHistoricalMerchantWithShopGetsOrganizationMembershipAndAttachment(): void
    {
        $merchant = $this->createUser('backfill-merchant@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $tester = $this->runBackfill();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('organizations_created: 1', $tester->getDisplay());
        self::assertStringContainsString('memberships_created: 1', $tester->getDisplay());
        self::assertStringContainsString('shops_attached: 1', $tester->getDisplay());

        $this->entityManager->clear();

        $organizations = $this->entityManager->getRepository(MerchantOrganization::class)->findAll();
        self::assertCount(1, $organizations);
        self::assertSame($merchant->getId()->toRfc4122(), $organizations[0]->getPrimaryAccount()?->getId()->toRfc4122());
        self::assertTrue($organizations[0]->isActive());

        $memberships = $this->entityManager->getRepository(MerchantMembership::class)->findAll();
        self::assertCount(1, $memberships);
        self::assertSame(MerchantMembershipStatus::Active, $memberships[0]->getStatus());
        self::assertNotNull($memberships[0]->getAcceptedAt());

        $persistedShop = $this->entityManager->getRepository(Shop::class)->find($shop->getId());
        self::assertNotNull($persistedShop);
        self::assertSame(
            $organizations[0]->getId()->toRfc4122(),
            $persistedShop->getMerchantOrganization()?->getId()->toRfc4122(),
        );
    }

    public function testMerchantWithSeveralShopsGetsSingleOrganization(): void
    {
        $merchant = $this->createUser('backfill-multi-shops@example.test', ['ROLE_MERCHANT']);
        $this->createShop($merchant);
        $this->createShop($merchant);

        $tester = $this->runBackfill();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('organizations_created: 1', $tester->getDisplay());
        self::assertStringContainsString('shops_attached: 2', $tester->getDisplay());
        self::assertCount(1, $this->entityManager->getRepository(MerchantOrganization::class)->findAll());
    }

    public function testShopWithoutOwnerIsReportedNeverAttached(): void
    {
        $shop = $this->createShop();

        $tester = $this->runBackfill();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('orphan_shops: 1', $tester->getDisplay());
        self::assertStringContainsString('orphan_shop: '.$shop->getId()->toRfc4122(), $tester->getDisplay());

        $this->entityManager->clear();
        $persistedShop = $this->entityManager->getRepository(Shop::class)->find($shop->getId());
        self::assertNull($persistedShop?->getMerchantOrganization());
        self::assertCount(0, $this->entityManager->getRepository(MerchantOrganization::class)->findAll());
    }

    public function testRerunCreatesNoDuplicate(): void
    {
        $merchant = $this->createUser('backfill-idempotent@example.test', ['ROLE_MERCHANT']);
        $this->createShop($merchant);

        $first = $this->runBackfill();
        self::assertSame(Command::SUCCESS, $first->getStatusCode(), $first->getDisplay());

        $second = $this->runBackfill();
        self::assertSame(Command::SUCCESS, $second->getStatusCode(), $second->getDisplay());
        self::assertStringContainsString('organizations_created: 0', $second->getDisplay());
        self::assertStringContainsString('memberships_created: 0', $second->getDisplay());
        self::assertStringContainsString('shops_attached: 0', $second->getDisplay());

        self::assertCount(1, $this->entityManager->getRepository(MerchantOrganization::class)->findAll());
        self::assertCount(1, $this->entityManager->getRepository(MerchantMembership::class)->findAll());
    }

    public function testNonMerchantUsersAreIgnored(): void
    {
        $this->createUser('backfill-customer@example.test', ['ROLE_CUSTOMER']);
        $this->createUser('backfill-admin@example.test', ['ROLE_ADMIN']);

        $tester = $this->runBackfill();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('merchants_seen: 0', $tester->getDisplay());
        self::assertCount(0, $this->entityManager->getRepository(MerchantOrganization::class)->findAll());
    }

    private function runBackfill(): CommandTester
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:merchant-organizations:backfill');
        $tester = new CommandTester($command);
        $tester->execute([]);

        return $tester;
    }
}
