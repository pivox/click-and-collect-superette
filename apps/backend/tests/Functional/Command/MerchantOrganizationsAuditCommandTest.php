<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\MerchantMembership;
use App\Entity\MerchantOrganization;
use App\Entity\User;
use App\Tests\Functional\Api\FunctionalApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class MerchantOrganizationsAuditCommandTest extends FunctionalApiTestCase
{
    public function testSoundModelReturnsSuccess(): void
    {
        $merchant = $this->createUser('audit-ok@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $organization = $this->createOrganization($merchant);
        $this->createActiveMembership($organization, $merchant);
        $shop->setMerchantOrganization($organization);
        $this->entityManager->flush();

        $tester = $this->runAudit();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('merchant_organizations_audit: OK', $tester->getDisplay());
    }

    public function testActiveShopWithoutOrganizationIsReported(): void
    {
        $merchant = $this->createUser('audit-shop-noorg@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $tester = $this->runAudit();

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString(
            'active_shop_without_organization: '.$shop->getId()->toRfc4122(),
            $tester->getDisplay(),
        );
    }

    public function testOrganizationWithoutPrimaryAccountIsReported(): void
    {
        $organization = (new MerchantOrganization())->setName('Org sans principal');
        $this->entityManager->persist($organization);
        $this->entityManager->flush();

        $tester = $this->runAudit();

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString(
            'organization_without_primary_account: '.$organization->getId()->toRfc4122(),
            $tester->getDisplay(),
        );
        // organization_without_shop is a transient state: reported as warning only.
        self::assertStringContainsString(
            'warning: organization_without_shop: '.$organization->getId()->toRfc4122(),
            $tester->getDisplay(),
        );
    }

    public function testOrganizationWithoutShopIsAWarningAndDoesNotFailTheAudit(): void
    {
        $merchant = $this->createUser('audit-org-noshop@example.test', ['ROLE_MERCHANT']);
        $organization = $this->createOrganization($merchant);
        $this->createActiveMembership($organization, $merchant);

        $tester = $this->runAudit();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('merchant_organizations_audit: 1 warning(s)', $tester->getDisplay());
        self::assertStringContainsString(
            'warning: organization_without_shop: '.$organization->getId()->toRfc4122(),
            $tester->getDisplay(),
        );
        self::assertStringContainsString('merchant_organizations_audit: OK', $tester->getDisplay());
    }

    public function testPrimaryAccountWithoutActiveMembershipIsReported(): void
    {
        $merchant = $this->createUser('audit-no-membership@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $organization = $this->createOrganization($merchant);
        $shop->setMerchantOrganization($organization);
        $this->entityManager->flush();

        $tester = $this->runAudit();

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString(
            'primary_account_without_active_membership: '.$organization->getId()->toRfc4122(),
            $tester->getDisplay(),
        );
    }

    public function testActiveMembershipWithoutMerchantRoleIsReported(): void
    {
        $customer = $this->createUser('audit-customer-membership@example.test', ['ROLE_CUSTOMER']);
        $organization = $this->createOrganization($customer);
        $this->createActiveMembership($organization, $customer);
        $shop = $this->createShop();
        $shop->setMerchantOrganization($organization);
        $this->entityManager->flush();

        $tester = $this->runAudit();

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString(
            'active_membership_without_merchant_role: '.$customer->getId()->toRfc4122(),
            $tester->getDisplay(),
        );
    }

    public function testShopOwnerDivergingFromPrimaryAccountIsReported(): void
    {
        $primary = $this->createUser('audit-primary@example.test', ['ROLE_MERCHANT']);
        $otherOwner = $this->createUser('audit-other-owner@example.test', ['ROLE_MERCHANT']);
        $organization = $this->createOrganization($primary);
        $this->createActiveMembership($organization, $primary);
        $shop = $this->createShop($otherOwner);
        $shop->setMerchantOrganization($organization);
        $this->entityManager->flush();

        $tester = $this->runAudit();

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString(
            'shop_owner_diverges_from_primary_account: shop='.$shop->getId()->toRfc4122(),
            $tester->getDisplay(),
        );
    }

    public function testAuditNeverRepairsData(): void
    {
        $merchant = $this->createUser('audit-readonly@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $this->runAudit();

        $this->entityManager->clear();
        self::assertCount(0, $this->entityManager->getRepository(MerchantOrganization::class)->findAll());
        self::assertNull(
            $this->entityManager->getRepository(\App\Entity\Shop::class)->find($shop->getId())?->getMerchantOrganization(),
        );
    }

    private function createOrganization(User $primaryAccount): MerchantOrganization
    {
        $organization = (new MerchantOrganization())
            ->setName('Organisation test')
            ->setPrimaryAccount($primaryAccount);
        $this->entityManager->persist($organization);
        $this->entityManager->flush();

        return $organization;
    }

    private function createActiveMembership(MerchantOrganization $organization, User $user): MerchantMembership
    {
        $membership = (new MerchantMembership())
            ->setOrganization($organization)
            ->setUser($user)
            ->activate();
        $this->entityManager->persist($membership);
        $this->entityManager->flush();

        return $membership;
    }

    private function runAudit(): CommandTester
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:merchant-organizations:audit');
        $tester = new CommandTester($command);
        $tester->execute([]);

        return $tester;
    }
}
