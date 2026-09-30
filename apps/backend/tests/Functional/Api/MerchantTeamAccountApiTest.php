<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\MerchantCrmProfile;
use App\Entity\MerchantMembership;
use App\Entity\MerchantOrganization;
use App\Entity\Shop;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\MerchantMembershipStatus;
use App\Tests\Support\MerchantInvitation\TestMerchantInvitationSender;

/**
 * MERCHANT-TEAM-004: team lifecycle (list, invite, resend, revoke) managed by
 * the primary account, reusing the existing invitation token/email journey.
 */
final class MerchantTeamAccountApiTest extends FunctionalApiTestCase
{
    private User $primary;
    private User $secondary;
    private MerchantOrganization $organization;
    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->invitationSender()->reset();

        $this->primary = $this->createUser('team-primary@example.test', ['ROLE_MERCHANT']);
        $this->secondary = $this->createUser('team-secondary@example.test', ['ROLE_MERCHANT']);
        $this->organization = (new MerchantOrganization())
            ->setName('Organisation Équipe')
            ->setPrimaryAccount($this->primary);
        $this->entityManager->persist($this->organization);
        $this->activeMembership($this->primary);
        $this->activeMembership($this->secondary);
        $this->shop = $this->createShop($this->primary);
        $this->shop->setMerchantOrganization($this->organization);
        $this->entityManager->flush();
    }

    public function testPrimaryListsTheTeamWithoutSensitiveFields(): void
    {
        $revoked = $this->createUser('team-revoked@example.test', ['ROLE_MERCHANT']);
        $membership = (new MerchantMembership())
            ->setOrganization($this->organization)
            ->setUser($revoked)
            ->revoke($this->primary);
        $this->entityManager->persist($membership);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', $this->accountsUrl(), user: $this->primary);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($this->shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertSame($this->organization->getId()->toRfc4122(), $payload['organization_id']);
        self::assertSame(10, $payload['limit']);
        // Revoked accounts stay listed (history) but leave the quota count.
        self::assertSame(2, $payload['active_or_invited_count']);
        self::assertCount(3, $payload['items']);

        $byEmail = array_column($payload['items'], null, 'email');
        self::assertTrue($byEmail['team-primary@example.test']['is_primary']);
        self::assertSame('active', $byEmail['team-primary@example.test']['status']);
        self::assertFalse($byEmail['team-secondary@example.test']['is_primary']);
        self::assertSame('revoked', $byEmail['team-revoked@example.test']['status']);

        $raw = (string) $response->getContent();
        self::assertStringNotContainsStringIgnoringCase('password', $raw);
        self::assertStringNotContainsStringIgnoringCase('token_hash', $raw);
    }

    public function testInvitationHappyPathActivationAndShopAccess(): void
    {
        $response = $this->requestJson('POST', $this->invitationsUrl(), [
            'first_name' => 'Ahmed',
            'last_name' => 'Ben Ali',
            'email' => 'Ahmed.Invite@Example.Test',
            'phone' => '+21620111222',
        ], $this->primary);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $payload = $this->decodeJson($response);
        self::assertSame('invited', $payload['status']);
        self::assertSame('ahmed.invite@example.test', $payload['email']);
        self::assertSame('sent', $payload['invitation_status']);
        self::assertFalse($payload['is_primary']);
        self::assertStringNotContainsStringIgnoringCase('password', (string) $response->getContent());

        // No commercial data is ever created by a team invitation.
        self::assertCount(0, $this->entityManager->getRepository(Subscription::class)->findAll());
        self::assertCount(0, $this->entityManager->getRepository(MerchantCrmProfile::class)->findAll());
        self::assertCount(1, $this->entityManager->getRepository(Shop::class)->findAll());

        // The invitee defines their password through the existing journey…
        $rawToken = $this->invitationSender()->tokenFor('ahmed.invite@example.test');
        self::assertNotNull($rawToken);
        $complete = $this->requestJson('POST', '/api/auth/merchant-invitations/complete', [
            'token' => $rawToken,
            'new_password' => 'definitiveSecret456',
            'new_password_confirmation' => 'definitiveSecret456',
        ]);
        self::assertSame(204, $complete->getStatusCode(), (string) $complete->getContent());

        // …which activates the membership atomically.
        $this->entityManager->clear();
        $invitee = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'ahmed.invite@example.test']);
        self::assertInstanceOf(User::class, $invitee);
        $membership = $this->entityManager->getRepository(MerchantMembership::class)->findOneBy(['user' => $invitee]);
        self::assertNotNull($membership);
        self::assertSame(MerchantMembershipStatus::Active, $membership->getStatus());
        self::assertNotNull($membership->getAcceptedAt());

        // The activated account operates the organization shop.
        $profile = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/profile', $this->shop->getId()),
            user: $invitee,
        );
        self::assertSame(200, $profile->getStatusCode());
    }

    public function testUsedEmailIsNeverAttachedAutomatically(): void
    {
        $this->createUser('existing-customer@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson('POST', $this->invitationsUrl(), [
            'first_name' => 'Doublon',
            'last_name' => 'Email',
            'email' => 'existing-customer@example.test',
        ], $this->primary);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('MERCHANT_ACCOUNT_EMAIL_ALREADY_USED', $this->decodeJson($response)['detail']);
    }

    public function testDuplicateInvitationIsRejected(): void
    {
        $first = $this->requestJson('POST', $this->invitationsUrl(), [
            'first_name' => 'Ahmed',
            'last_name' => 'Ben Ali',
            'email' => 'double-invite@example.test',
        ], $this->primary);
        self::assertSame(201, $first->getStatusCode());

        $second = $this->requestJson('POST', $this->invitationsUrl(), [
            'first_name' => 'Ahmed',
            'last_name' => 'Ben Ali',
            'email' => 'double-invite@example.test',
        ], $this->primary);

        self::assertSame(422, $second->getStatusCode());
        self::assertSame('MERCHANT_ACCOUNT_EMAIL_ALREADY_USED', $this->decodeJson($second)['detail']);
    }

    public function testQuotaCountsInvitedAndActiveButNotRevoked(): void
    {
        // 2 existing (primary + secondary) + 8 extra = 10 = limit.
        foreach (range(1, 8) as $i) {
            $extra = $this->createUser(\sprintf('team-extra-%d@example.test', $i), ['ROLE_MERCHANT']);
            $this->activeMembership($extra);
        }
        // A revoked membership must not count towards the quota.
        $revoked = $this->createUser('team-quota-revoked@example.test', ['ROLE_MERCHANT']);
        $revokedMembership = (new MerchantMembership())
            ->setOrganization($this->organization)
            ->setUser($revoked)
            ->revoke($this->primary);
        $this->entityManager->persist($revokedMembership);
        $this->entityManager->flush();

        $response = $this->requestJson('POST', $this->invitationsUrl(), [
            'first_name' => 'Trop',
            'last_name' => 'Plein',
            'email' => 'team-overflow@example.test',
        ], $this->primary);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('MERCHANT_ACCOUNT_LIMIT_REACHED', $this->decodeJson($response)['detail']);
        self::assertNull($this->entityManager->getRepository(User::class)->findOneBy(['email' => 'team-overflow@example.test']));
        // No membership row was created for the refused email: the organization
        // still holds 10 non-revoked memberships + 1 revoked one.
        self::assertCount(
            11,
            $this->entityManager->getRepository(MerchantMembership::class)->findBy(['organization' => $this->organization]),
        );
    }

    public function testSecondaryAccountCannotManageTheTeam(): void
    {
        $list = $this->requestJson('GET', $this->accountsUrl(), user: $this->secondary);
        self::assertSame(403, $list->getStatusCode());
        self::assertSame('MERCHANT_TEAM_MANAGEMENT_FORBIDDEN', $this->decodeJson($list)['detail']);

        $invite = $this->requestJson('POST', $this->invitationsUrl(), [
            'first_name' => 'Interdit',
            'last_name' => 'Secondaire',
            'email' => 'forbidden-by-secondary@example.test',
        ], $this->secondary);
        self::assertSame(403, $invite->getStatusCode());

        $revoke = $this->requestJson(
            'DELETE',
            $this->accountUrl($this->primary->getId()->toRfc4122()),
            user: $this->secondary,
        );
        self::assertSame(403, $revoke->getStatusCode());
    }

    public function testForeignPrimaryCannotManageAnotherOrganizationTeam(): void
    {
        $foreignPrimary = $this->createUser('team-foreign@example.test', ['ROLE_MERCHANT']);
        $foreignOrganization = (new MerchantOrganization())
            ->setName('Organisation étrangère')
            ->setPrimaryAccount($foreignPrimary);
        $this->entityManager->persist($foreignOrganization);
        $membership = (new MerchantMembership())
            ->setOrganization($foreignOrganization)
            ->setUser($foreignPrimary)
            ->activate();
        $this->entityManager->persist($membership);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', $this->accountsUrl(), user: $foreignPrimary);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testRevocationIsImmediateIdempotentAndPreservesHistory(): void
    {
        $before = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/profile', $this->shop->getId()),
            user: $this->secondary,
        );
        self::assertSame(200, $before->getStatusCode());

        $revoke = $this->requestJson(
            'DELETE',
            $this->accountUrl($this->secondary->getId()->toRfc4122()),
            user: $this->primary,
        );
        self::assertSame(204, $revoke->getStatusCode());

        // Immediate effect on the next request, JWT still valid.
        $after = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/profile', $this->shop->getId()),
            user: $this->secondary,
        );
        self::assertSame(403, $after->getStatusCode());

        // Idempotent repetition.
        $again = $this->requestJson(
            'DELETE',
            $this->accountUrl($this->secondary->getId()->toRfc4122()),
            user: $this->primary,
        );
        self::assertSame(204, $again->getStatusCode());

        // History preserved: User and membership still exist, with revocation metadata.
        $this->entityManager->clear();
        $membership = $this->entityManager->getRepository(MerchantMembership::class)
            ->findOneBy(['user' => $this->secondary]);
        self::assertNotNull($membership);
        self::assertSame(MerchantMembershipStatus::Revoked, $membership->getStatus());
        self::assertNotNull($membership->getRevokedAt());
        self::assertSame($this->primary->getId()->toRfc4122(), $membership->getRevokedBy()?->getId()->toRfc4122());
        self::assertNotNull($this->entityManager->getRepository(User::class)->find($this->secondary->getId()));
    }

    public function testPrimaryAccountCannotBeRevoked(): void
    {
        $response = $this->requestJson(
            'DELETE',
            $this->accountUrl($this->primary->getId()->toRfc4122()),
            user: $this->primary,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('MERCHANT_PRIMARY_ACCOUNT_CANNOT_BE_REVOKED', $this->decodeJson($response)['detail']);
    }

    public function testUnknownAccountReturnsStable404(): void
    {
        $response = $this->requestJson(
            'DELETE',
            $this->accountUrl('550e8400-e29b-41d4-a716-446655440000'),
            user: $this->primary,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('MERCHANT_ACCOUNT_NOT_FOUND', $this->decodeJson($response)['detail']);
    }

    public function testDeliveryFailureKeepsInvitationAndResendRotatesTheToken(): void
    {
        $this->invitationSender()->failNextSend();

        $invite = $this->requestJson('POST', $this->invitationsUrl(), [
            'first_name' => 'Panne',
            'last_name' => 'Email',
            'email' => 'delivery-failed@example.test',
        ], $this->primary);

        self::assertSame(201, $invite->getStatusCode());
        $payload = $this->decodeJson($invite);
        self::assertSame('delivery_failed', $payload['invitation_status']);
        $firstToken = $this->invitationSender()->tokenFor('delivery-failed@example.test');
        self::assertNotNull($firstToken);

        $resend = $this->requestJson(
            'POST',
            $this->accountUrl($payload['account_id']).'/resend-invitation',
            [],
            $this->primary,
        );
        self::assertSame(200, $resend->getStatusCode(), (string) $resend->getContent());
        self::assertSame('sent', $this->decodeJson($resend)['invitation_status']);

        $secondToken = $this->invitationSender()->tokenFor('delivery-failed@example.test');
        self::assertNotNull($secondToken);
        self::assertNotSame($firstToken, $secondToken);

        // The rotated-out token can no longer be used.
        $replay = $this->requestJson('POST', '/api/auth/merchant-invitations/complete', [
            'token' => $firstToken,
            'new_password' => 'definitiveSecret456',
            'new_password_confirmation' => 'definitiveSecret456',
        ]);
        self::assertSame(400, $replay->getStatusCode());

        // The fresh token works.
        $complete = $this->requestJson('POST', '/api/auth/merchant-invitations/complete', [
            'token' => $secondToken,
            'new_password' => 'definitiveSecret456',
            'new_password_confirmation' => 'definitiveSecret456',
        ]);
        self::assertSame(204, $complete->getStatusCode());
    }

    public function testResendIsRefusedForAnActiveAccount(): void
    {
        $response = $this->requestJson(
            'POST',
            $this->accountUrl($this->secondary->getId()->toRfc4122()).'/resend-invitation',
            [],
            $this->primary,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('MERCHANT_INVITATION_NOT_PENDING', $this->decodeJson($response)['detail']);
    }

    // Fixtures

    private function activeMembership(User $user): MerchantMembership
    {
        $membership = (new MerchantMembership())
            ->setOrganization($this->organization)
            ->setUser($user)
            ->activate();
        $this->entityManager->persist($membership);

        return $membership;
    }

    private function accountsUrl(): string
    {
        return \sprintf('/api/merchant/stores/%s/accounts', $this->shop->getId());
    }

    private function invitationsUrl(): string
    {
        return \sprintf('/api/merchant/stores/%s/account-invitations', $this->shop->getId());
    }

    private function accountUrl(string $accountId): string
    {
        return \sprintf('/api/merchant/stores/%s/accounts/%s', $this->shop->getId(), $accountId);
    }

    private function invitationSender(): TestMerchantInvitationSender
    {
        $sender = self::getContainer()->get(TestMerchantInvitationSender::class);
        self::assertInstanceOf(TestMerchantInvitationSender::class, $sender);

        return $sender;
    }
}
