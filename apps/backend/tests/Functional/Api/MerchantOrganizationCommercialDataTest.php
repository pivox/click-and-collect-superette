<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\MerchantCrmProfile;
use App\Entity\MerchantMembership;
use App\Entity\MerchantOrganization;
use App\Entity\Shop;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\SubscriptionLifecycle;
use App\Enum\SubscriptionPricingPhase;
use App\Service\ShopOrderingAvailabilityChecker;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * MERCHANT-TEAM-002: commercial data (subscription, CRM, billing) belongs to
 * the organization; accounts and shops resolve it through the organization
 * with a merchant-User fallback during the transition.
 */
final class MerchantOrganizationCommercialDataTest extends FunctionalApiTestCase
{
    public function testBackfillAttachesSubscriptionAndCrmProfileIdempotently(): void
    {
        $merchant = $this->createUser('commercial-backfill@example.test', ['ROLE_MERCHANT']);
        $this->createShop($merchant);
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'));
        $subscription->setLifecycle(SubscriptionLifecycle::Active);
        $this->entityManager->persist($subscription);
        $profile = new MerchantCrmProfile($merchant);
        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        $first = $this->runBackfill();
        self::assertSame(Command::SUCCESS, $first->getStatusCode(), $first->getDisplay());
        self::assertStringContainsString('subscriptions_attached: 1', $first->getDisplay());
        self::assertStringContainsString('crm_profiles_attached: 1', $first->getDisplay());

        $this->entityManager->clear();

        $organizations = $this->entityManager->getRepository(MerchantOrganization::class)->findAll();
        self::assertCount(1, $organizations);

        $persistedSubscription = $this->entityManager->getRepository(Subscription::class)->find($subscription->getId());
        self::assertNotNull($persistedSubscription);
        self::assertSame(
            $organizations[0]->getId()->toRfc4122(),
            $persistedSubscription->getMerchantOrganization()?->getId()->toRfc4122(),
        );
        // Business identity, amounts, periods and statuses are preserved.
        self::assertSame($merchant->getId()->toRfc4122(), $persistedSubscription->getMerchant()->getId()->toRfc4122());
        self::assertSame(SubscriptionLifecycle::Active, $persistedSubscription->getLifecycle());
        self::assertSame(SubscriptionPricingPhase::Trial, $persistedSubscription->getPricingPhase());
        self::assertSame('0.000', $persistedSubscription->getMonthlyPriceTnd());

        $persistedProfile = $this->entityManager->getRepository(MerchantCrmProfile::class)->find($profile->getId());
        self::assertNotNull($persistedProfile);
        self::assertSame(
            $organizations[0]->getId()->toRfc4122(),
            $persistedProfile->getMerchantOrganization()?->getId()->toRfc4122(),
        );

        $second = $this->runBackfill();
        self::assertStringContainsString('subscriptions_attached: 0', $second->getDisplay());
        self::assertStringContainsString('crm_profiles_attached: 0', $second->getDisplay());
        self::assertCount(1, $this->entityManager->getRepository(Subscription::class)->findAll());
        self::assertCount(1, $this->entityManager->getRepository(MerchantCrmProfile::class)->findAll());
    }

    public function testShopSuspensionResolvesThroughOrganization(): void
    {
        $primary = $this->createUser('commercial-primary@example.test', ['ROLE_MERCHANT']);
        $organization = $this->createOrganization($primary);
        $this->createActiveMembership($organization, $primary);

        // Shop with an organization but no owner: only the organization path
        // can resolve the subscription.
        $shop = $this->createShop();
        $shop->setMerchantOrganization($organization);

        $subscription = Subscription::startTrial($primary, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'), $organization);
        $subscription->setLifecycle(SubscriptionLifecycle::Suspended);
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        $checker = self::getContainer()->get(ShopOrderingAvailabilityChecker::class);
        self::assertInstanceOf(ShopOrderingAvailabilityChecker::class, $checker);

        self::assertSame(ShopOrderingAvailabilityChecker::STORE_SUSPENDED_FOR_SUBSCRIPTION, $checker->blockReason($shop));

        // Reactivation lifts the block for the whole organization at once.
        $subscription->setLifecycle(SubscriptionLifecycle::Active);
        $this->entityManager->flush();
        self::assertNull($checker->blockReason($shop));
    }

    public function testDeactivatingSecondaryAccountNeverSuspendsTheShop(): void
    {
        $primary = $this->createUser('commercial-primary-2@example.test', ['ROLE_MERCHANT']);
        $secondary = $this->createUser('commercial-secondary-2@example.test', ['ROLE_MERCHANT']);
        $organization = $this->createOrganization($primary);
        $this->createActiveMembership($organization, $primary);
        $this->createActiveMembership($organization, $secondary);

        $shop = $this->createShop($primary);
        $shop->setMerchantOrganization($organization);

        $subscription = Subscription::startTrial($primary, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'), $organization);
        $subscription->setLifecycle(SubscriptionLifecycle::Active);
        $this->entityManager->persist($subscription);

        $secondary->setActive(false);
        $this->entityManager->flush();

        $checker = self::getContainer()->get(ShopOrderingAvailabilityChecker::class);
        self::assertInstanceOf(ShopOrderingAvailabilityChecker::class, $checker);
        self::assertNull($checker->blockReason($shop));
    }

    public function testSecondaryAccountSeesTheOrganizationSubscription(): void
    {
        $primary = $this->createUser('commercial-sub-primary@example.test', ['ROLE_MERCHANT']);
        $secondary = $this->createUser('commercial-sub-secondary@example.test', ['ROLE_MERCHANT']);
        $organization = $this->createOrganization($primary);
        $this->createActiveMembership($organization, $primary);
        $this->createActiveMembership($organization, $secondary);

        $subscription = Subscription::startTrial($primary, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'), $organization);
        $subscription->setLifecycle(SubscriptionLifecycle::Active);
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', '/api/merchant/subscription', user: $secondary);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($subscription->getId()->toRfc4122(), $payload['id']);
        self::assertSame('active', $payload['lifecycle']);
    }

    public function testAdminMerchantOrganizationsCollectionListsEachOrganizationOnce(): void
    {
        $admin = $this->createUser('commercial-admin@example.test', ['ROLE_ADMIN']);
        $primary = $this->createUser('commercial-list-primary@example.test', ['ROLE_MERCHANT']);
        $secondary = $this->createUser('commercial-list-secondary@example.test', ['ROLE_MERCHANT']);
        $revoked = $this->createUser('commercial-list-revoked@example.test', ['ROLE_MERCHANT']);

        $organization = $this->createOrganization($primary);
        $this->createActiveMembership($organization, $primary);
        $this->createActiveMembership($organization, $secondary);
        $revokedMembership = (new MerchantMembership())
            ->setOrganization($organization)
            ->setUser($revoked)
            ->revoke($primary);
        $this->entityManager->persist($revokedMembership);

        $shop = $this->createShop($primary);
        $shop->setMerchantOrganization($organization);

        $subscription = Subscription::startTrial($primary, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'), $organization);
        $subscription->setLifecycle(SubscriptionLifecycle::Active);
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', '/api/admin/merchant-organizations', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertCount(1, $payload['items']);

        $item = $payload['items'][0];
        self::assertSame($organization->getId()->toRfc4122(), $item['id']);
        self::assertSame('Organisation test', $item['name']);
        self::assertTrue($item['active']);
        self::assertSame($primary->getId()->toRfc4122(), $item['primary_account']['user_id']);
        self::assertSame('commercial-list-primary@example.test', $item['primary_account']['email']);
        // Revoked accounts are excluded from the quota count.
        self::assertSame(2, $item['accounts_count']);
        self::assertSame(1, $item['stores_count']);
        self::assertSame('active', $item['subscription_status']);
    }

    public function testAdminMerchantOrganizationsCollectionPaginates(): void
    {
        $admin = $this->createUser('commercial-admin-page@example.test', ['ROLE_ADMIN']);
        foreach ([1, 2, 3] as $i) {
            $merchant = $this->createUser(\sprintf('commercial-page-%d@example.test', $i), ['ROLE_MERCHANT']);
            $this->createOrganization($merchant);
        }

        $response = $this->requestJson('GET', '/api/admin/merchant-organizations?page=1&limit=2', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(3, $payload['total']);
        self::assertSame(2, $payload['limit']);
        self::assertCount(2, $payload['items']);
    }

    public function testAdminMerchantOrganizationsCollectionIsAdminOnly(): void
    {
        $merchant = $this->createUser('commercial-forbidden@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson('GET', '/api/admin/merchant-organizations', user: $merchant);

        self::assertSame(403, $response->getStatusCode());
    }

    // Fixtures

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

    private function runBackfill(): CommandTester
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:merchant-organizations:backfill');
        $tester = new CommandTester($command);
        $tester->execute([]);

        return $tester;
    }
}
