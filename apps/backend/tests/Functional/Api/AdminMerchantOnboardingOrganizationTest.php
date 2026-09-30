<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\MerchantMembership;
use App\Entity\MerchantOrganization;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\MerchantMembershipStatus;

/**
 * MERCHANT-TEAM-001: admin onboarding feeds the organization model atomically
 * while keeping the historical Shop.owner behaviour and payload untouched.
 */
final class AdminMerchantOnboardingOrganizationTest extends FunctionalApiTestCase
{
    public function testOnboardingCreatesOrganizationMembershipAndAttachedShopAtomically(): void
    {
        $admin = $this->createUser('admin-onboarding-org@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('POST', '/api/admin/merchant-onboarding', [
            'merchant' => [
                'email' => 'org-merchant@example.test',
                'first_name' => 'Noura',
                'last_name' => 'Trabelsi',
            ],
            'shop' => [
                'name' => 'Supérette Organisation',
            ],
            'first_login_mode' => 'temporary_password',
            'product_group_ids' => [],
        ], user: $admin);

        self::assertSame(201, $response->getStatusCode());

        $this->entityManager->clear();

        $merchant = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'org-merchant@example.test']);
        self::assertInstanceOf(User::class, $merchant);

        $organizations = $this->entityManager->getRepository(MerchantOrganization::class)->findAll();
        self::assertCount(1, $organizations);
        $organization = $organizations[0];
        self::assertSame('Noura Trabelsi', $organization->getName());
        self::assertTrue($organization->isActive());
        self::assertSame($merchant->getId()->toRfc4122(), $organization->getPrimaryAccount()?->getId()->toRfc4122());

        $memberships = $this->entityManager->getRepository(MerchantMembership::class)->findAll();
        self::assertCount(1, $memberships);
        self::assertSame(MerchantMembershipStatus::Active, $memberships[0]->getStatus());
        self::assertSame($merchant->getId()->toRfc4122(), $memberships[0]->getUser()?->getId()->toRfc4122());
        self::assertSame($organization->getId()->toRfc4122(), $memberships[0]->getOrganization()?->getId()->toRfc4122());

        $shop = $this->entityManager->getRepository(Shop::class)->findOneBy(['name' => 'Supérette Organisation']);
        self::assertInstanceOf(Shop::class, $shop);
        // Both models are fed during the transition.
        self::assertSame($merchant->getId()->toRfc4122(), $shop->getOwner()?->getId()->toRfc4122());
        self::assertSame($organization->getId()->toRfc4122(), $shop->getMerchantOrganization()?->getId()->toRfc4122());
    }

    public function testFailedOnboardingCreatesNoOrganization(): void
    {
        $admin = $this->createUser('admin-onboarding-org-fail@example.test', ['ROLE_ADMIN']);
        $this->createUser('already-there@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson('POST', '/api/admin/merchant-onboarding', [
            'merchant' => [
                'email' => 'already-there@example.test',
                'first_name' => 'Sami',
                'last_name' => 'Bouaziz',
            ],
            'shop' => [
                'name' => 'Supérette doublon',
            ],
            'first_login_mode' => 'temporary_password',
            'product_group_ids' => [],
        ], user: $admin);

        self::assertSame(422, $response->getStatusCode());

        $this->entityManager->clear();
        self::assertCount(0, $this->entityManager->getRepository(MerchantOrganization::class)->findAll());
        self::assertCount(0, $this->entityManager->getRepository(MerchantMembership::class)->findAll());
    }

    public function testAdminStoreCreationAttachesShopToOwnersOrganization(): void
    {
        $admin = $this->createUser('admin-store-org@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('store-org-merchant@example.test', ['ROLE_MERCHANT']);

        $organization = (new MerchantOrganization())
            ->setName('Org existante')
            ->setPrimaryAccount($merchant);
        $membership = (new MerchantMembership())
            ->setOrganization($organization)
            ->setUser($merchant)
            ->activate();
        $this->entityManager->persist($organization);
        $this->entityManager->persist($membership);
        $this->entityManager->flush();

        $response = $this->requestJson('POST', '/api/admin/stores', [
            'name' => 'Deuxième supérette',
            'ownerId' => $merchant->getId()->toRfc4122(),
        ], user: $admin);

        self::assertSame(201, $response->getStatusCode());

        $this->entityManager->clear();
        $shop = $this->entityManager->getRepository(Shop::class)->findOneBy(['name' => 'Deuxième supérette']);
        self::assertInstanceOf(Shop::class, $shop);
        self::assertSame($organization->getId()->toRfc4122(), $shop->getMerchantOrganization()?->getId()->toRfc4122());
    }

    public function testAdminStoreCreationWithoutBackfilledOwnerLeavesOrganizationNull(): void
    {
        $admin = $this->createUser('admin-store-noorg@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('store-noorg-merchant@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson('POST', '/api/admin/stores', [
            'name' => 'Supérette sans organisation',
            'ownerId' => $merchant->getId()->toRfc4122(),
        ], user: $admin);

        self::assertSame(201, $response->getStatusCode());

        $this->entityManager->clear();
        $shop = $this->entityManager->getRepository(Shop::class)->findOneBy(['name' => 'Supérette sans organisation']);
        self::assertInstanceOf(Shop::class, $shop);
        self::assertNull($shop->getMerchantOrganization());
    }
}
