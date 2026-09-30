<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\MerchantMembership;
use App\Entity\MerchantOrganization;
use App\Entity\Shop;
use App\Entity\User;

/**
 * MERCHANT-TEAM-003: merchant access is granted by active membership on the
 * shop organization; ownership alone only remains valid for shops not yet
 * backfilled. Revocation takes effect on the next request.
 */
final class MerchantMembershipAuthorizationApiTest extends FunctionalApiTestCase
{
    private User $primary;
    private User $secondary;
    private MerchantOrganization $organization;
    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->primary = $this->createUser('authz-primary@example.test', ['ROLE_MERCHANT']);
        $this->secondary = $this->createUser('authz-secondary@example.test', ['ROLE_MERCHANT']);
        $this->organization = (new MerchantOrganization())
            ->setName('Organisation Authz')
            ->setPrimaryAccount($this->primary);
        $this->entityManager->persist($this->organization);
        $this->membership($this->organization, $this->primary)->activate();
        $this->membership($this->organization, $this->secondary)->activate();
        $this->shop = $this->createShop($this->primary);
        $this->shop->setMerchantOrganization($this->organization);
        $this->entityManager->flush();
    }

    public function testBothAccountsOfTheOrganizationReadTheSameShopData(): void
    {
        foreach ([$this->primary, $this->secondary] as $account) {
            $profile = $this->requestJson('GET', $this->url('profile'), user: $account);
            self::assertSame(200, $profile->getStatusCode());
            self::assertSame($this->shop->getId()->toRfc4122(), $this->decodeJson($profile)['id']);

            $orders = $this->requestJson('GET', $this->url('orders'), user: $account);
            self::assertSame(200, $orders->getStatusCode());

            $catalog = $this->requestJson('GET', $this->url('catalog'), user: $account);
            self::assertSame(200, $catalog->getStatusCode());

            $slots = $this->requestJson('GET', $this->url('pickup-slots'), user: $account);
            self::assertSame(200, $slots->getStatusCode());
        }
    }

    public function testSecondaryAccountCanMutateShopData(): void
    {
        $response = $this->requestJson(
            'PATCH',
            $this->url('profile'),
            ['phone' => '+216 71 111 222'],
            $this->secondary,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('+216 71 111 222', $this->decodeJson($response)['phone']);
    }

    public function testThemeVoterAcceptsSecondaryAccount(): void
    {
        $response = $this->requestJson('GET', $this->url('theme'), user: $this->secondary);

        // 200 (theme resolved) — never 403: the SHOP_OWNER voter now accepts
        // any active membership of the shop organization.
        self::assertSame(200, $response->getStatusCode());
    }

    public function testMerchantOfAnotherOrganizationIsDeniedOnReadAndMutation(): void
    {
        $foreign = $this->createUser('authz-foreign@example.test', ['ROLE_MERCHANT']);
        $foreignOrganization = (new MerchantOrganization())
            ->setName('Organisation étrangère')
            ->setPrimaryAccount($foreign);
        $this->entityManager->persist($foreignOrganization);
        $this->membership($foreignOrganization, $foreign)->activate();
        $this->entityManager->flush();

        $read = $this->requestJson('GET', $this->url('profile'), user: $foreign);
        self::assertSame(403, $read->getStatusCode());
        self::assertSame('MERCHANT_CATALOG_FORBIDDEN', $this->decodeJson($read)['detail']);

        $write = $this->requestJson('PATCH', $this->url('profile'), ['phone' => '+216 71 999 999'], $foreign);
        self::assertSame(403, $write->getStatusCode());
    }

    public function testInvitedMembershipGrantsNoOperationalAccess(): void
    {
        $invited = $this->createUser('authz-invited@example.test', ['ROLE_MERCHANT']);
        $this->membership($this->organization, $invited)->markInvited($this->primary);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', $this->url('profile'), user: $invited);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('MERCHANT_CATALOG_FORBIDDEN', $this->decodeJson($response)['detail']);
    }

    public function testRevocationTakesEffectOnTheNextRequest(): void
    {
        $before = $this->requestJson('GET', $this->url('profile'), user: $this->secondary);
        self::assertSame(200, $before->getStatusCode());

        $membership = $this->entityManager->getRepository(MerchantMembership::class)
            ->findOneBy(['user' => $this->secondary]);
        self::assertNotNull($membership);
        $membership->revoke($this->primary);
        $this->entityManager->flush();

        // Same authenticated identity (JWT still valid): access is now denied,
        // on reads and on mutations alike.
        $read = $this->requestJson('GET', $this->url('profile'), user: $this->secondary);
        self::assertSame(403, $read->getStatusCode());

        $write = $this->requestJson('PATCH', $this->url('profile'), ['phone' => '+216 71 000 111'], $this->secondary);
        self::assertSame(403, $write->getStatusCode());

        // The other accounts and the organization are unaffected.
        $primaryStill = $this->requestJson('GET', $this->url('profile'), user: $this->primary);
        self::assertSame(200, $primaryStill->getStatusCode());
    }

    public function testInactiveUserIsDeniedEvenWithActiveMembership(): void
    {
        $this->secondary->setActive(false);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', $this->url('profile'), user: $this->secondary);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('MERCHANT_ACCOUNT_INACTIVE', $this->decodeJson($response)['detail']);
    }

    public function testInactiveOrganizationDeniesEveryAccount(): void
    {
        $this->organization->setActive(false);
        $this->entityManager->flush();

        foreach ([$this->primary, $this->secondary] as $account) {
            $response = $this->requestJson('GET', $this->url('profile'), user: $account);
            self::assertSame(403, $response->getStatusCode());
        }
    }

    public function testOwnershipAloneNoLongerGrantsAccessOnceShopHasOrganization(): void
    {
        // Owner whose membership was revoked: ownership must not bypass.
        $membership = $this->entityManager->getRepository(MerchantMembership::class)
            ->findOneBy(['user' => $this->primary]);
        self::assertNotNull($membership);
        $membership->revoke(null);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', $this->url('profile'), user: $this->primary);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testShopWithoutOrganizationKeepsHistoricalOwnerRule(): void
    {
        $legacyOwner = $this->createUser('authz-legacy@example.test', ['ROLE_MERCHANT']);
        $legacyShop = $this->createShop($legacyOwner);

        $owner = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/profile', $legacyShop->getId()),
            user: $legacyOwner,
        );
        self::assertSame(200, $owner->getStatusCode());

        // A member of another organization gains nothing on a legacy shop.
        $other = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/profile', $legacyShop->getId()),
            user: $this->secondary,
        );
        self::assertSame(403, $other->getStatusCode());
    }

    public function testMerchantMeBootstrapsTheOrganizationStoreForBothAccounts(): void
    {
        $primaryMe = $this->requestJson('GET', '/api/merchant/me', user: $this->primary);
        self::assertSame(200, $primaryMe->getStatusCode());
        $primaryPayload = $this->decodeJson($primaryMe);
        self::assertSame($this->shop->getId()->toRfc4122(), $primaryPayload['store']['id']);
        self::assertSame($this->organization->getId()->toRfc4122(), $primaryPayload['merchant_organization_id']);
        self::assertSame('active', $primaryPayload['account']['status']);
        self::assertTrue($primaryPayload['account']['is_primary']);

        $secondaryMe = $this->requestJson('GET', '/api/merchant/me', user: $this->secondary);
        self::assertSame(200, $secondaryMe->getStatusCode());
        $secondaryPayload = $this->decodeJson($secondaryMe);
        // The secondary account has no owned shop: the store comes from the organization.
        self::assertSame($this->shop->getId()->toRfc4122(), $secondaryPayload['store']['id']);
        self::assertSame($this->organization->getId()->toRfc4122(), $secondaryPayload['merchant_organization_id']);
        self::assertSame('active', $secondaryPayload['account']['status']);
        self::assertFalse($secondaryPayload['account']['is_primary']);
    }

    public function testMerchantMeWithSeveralActiveOrganizationShopsReturnsStableConflict(): void
    {
        $secondShop = $this->createShop($this->primary);
        $secondShop->setMerchantOrganization($this->organization);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', '/api/merchant/me', user: $this->secondary);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('MERCHANT_MULTIPLE_ACTIVE_STORES', $this->decodeJson($response)['detail']);
    }

    // Fixtures

    private function membership(MerchantOrganization $organization, User $user): MerchantMembership
    {
        $membership = (new MerchantMembership())
            ->setOrganization($organization)
            ->setUser($user);
        $this->entityManager->persist($membership);

        return $membership;
    }

    private function url(string $suffix): string
    {
        return \sprintf('/api/merchant/stores/%s/%s', $this->shop->getId(), $suffix);
    }
}
