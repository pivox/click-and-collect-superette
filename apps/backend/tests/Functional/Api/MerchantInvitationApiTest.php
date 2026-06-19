<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\AdminAuditLog;
use App\Entity\MerchantInvitationToken;
use App\Entity\User;
use App\Repository\MerchantInvitationTokenRepository;
use App\Service\MerchantInvitationTokenManager;
use App\Tests\Support\MerchantInvitation\TestMerchantInvitationSender;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class MerchantInvitationApiTest extends FunctionalApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->invitationSender()->reset();
    }

    public function testAdminCanCreateMerchantInvitationWithoutExposingSecret(): void
    {
        $admin = $this->createUser('admin-invitation-create@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchantWithPassword('merchant-invitation-create@example.test', 'oldSecret123');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/admin/merchants/%s/invitation', $merchant->getId()),
            [],
            $admin,
        );

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $payload = $this->decodeJson($response);
        self::assertSame($merchant->getId()->toRfc4122(), $payload['merchant_id']);
        self::assertSame('sent', $payload['status']);
        self::assertArrayHasKey('expires_at', $payload);

        $content = (string) $response->getContent();
        self::assertStringNotContainsStringIgnoringCase('token', $content);
        self::assertStringNotContainsStringIgnoringCase('password', $content);
        self::assertStringNotContainsStringIgnoringCase('hash', $content);
        self::assertStringNotContainsStringIgnoringCase('secret', $content);

        $rawToken = $this->invitationSender()->tokenFor('merchant-invitation-create@example.test');
        self::assertIsString($rawToken);
        self::assertGreaterThanOrEqual(32, \strlen($rawToken));

        $storedToken = $this->singleInvitationToken();
        self::assertSame($merchant->getId()->toRfc4122(), $storedToken->getMerchant()->getId()->toRfc4122());
        self::assertSame($admin->getId()->toRfc4122(), $storedToken->getCreatedBy()?->getId()->toRfc4122());
        self::assertNotSame($rawToken, $storedToken->getTokenHash());
        self::assertSame(64, \strlen($storedToken->getTokenHash()));
        self::assertSame(MerchantInvitationTokenManager::hashToken($rawToken), $storedToken->getTokenHash());
        self::assertNull($storedToken->getUsedAt());
        self::assertNull($storedToken->getRevokedAt());

        $auditLog = $this->findAuditLog('merchant.invitation.create', $merchant);
        self::assertNotNull($auditLog);
        self::assertSame($merchant->getEmail(), $auditLog->getMetadata()['email'] ?? null);
        self::assertStringNotContainsString($rawToken, json_encode($auditLog->getMetadata(), \JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString($rawToken, (string) $auditLog->getSummary());
    }

    public function testNonAdminCannotCreateMerchantInvitation(): void
    {
        $actor = $this->createUser('customer-invitation-forbidden@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createMerchantWithPassword('merchant-invitation-forbidden@example.test', 'oldSecret123');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/admin/merchants/%s/invitation', $merchant->getId()),
            [],
            $actor,
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertCount(0, $this->allInvitationTokens());
        self::assertNull($this->invitationSender()->tokenFor('merchant-invitation-forbidden@example.test'));
    }

    public function testCreateInvitationRejectsNonMerchantTarget(): void
    {
        $admin = $this->createUser('admin-invitation-non-merchant@example.test', ['ROLE_ADMIN']);
        $customer = $this->createUser('customer-invitation-non-merchant@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/admin/merchants/%s/invitation', $customer->getId()),
            [],
            $admin,
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('ADMIN_MERCHANT_INVITATION_TARGET_NOT_MERCHANT', (string) $response->getContent());
    }

    public function testCreateInvitationRejectsInactiveMerchant(): void
    {
        $admin = $this->createUser('admin-invitation-inactive@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchantWithPassword('merchant-invitation-inactive@example.test', 'oldSecret123');
        $merchant->setActive(false);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/admin/merchants/%s/invitation', $merchant->getId()),
            [],
            $admin,
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('ADMIN_MERCHANT_INVITATION_TARGET_NOT_ELIGIBLE', (string) $response->getContent());
        self::assertCount(0, $this->allInvitationTokens());
        self::assertNull($this->invitationSender()->tokenFor($merchant->getEmail()));
    }

    public function testPublicVerifyAcceptsValidInvitationTokenWithoutExposingSecret(): void
    {
        $rawToken = $this->createInvitationFor('merchant-invitation-verify@example.test');

        $response = $this->requestJson('POST', '/api/auth/merchant-invitations/verify', [
            'token' => $rawToken,
        ]);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $payload = $this->decodeJson($response);
        self::assertSame('valid', $payload['status']);
        self::assertArrayHasKey('expires_at', $payload);
        self::assertArrayNotHasKey('token', $payload);
        self::assertArrayNotHasKey('token_hash', $payload);
        self::assertArrayNotHasKey('password', $payload);
    }

    public function testMerchantCanCompleteInvitationAndLoginWithDefinitivePassword(): void
    {
        $merchant = $this->createMerchantWithPassword('merchant-invitation-complete@example.test', 'oldSecret123');
        $merchant->setPasswordChangeRequired(true);
        $this->entityManager->flush();
        $rawToken = $this->createInvitationFor($merchant->getEmail(), $merchant);

        $response = $this->requestJson('POST', '/api/auth/merchant-invitations/complete', [
            'token' => $rawToken,
            'new_password' => 'definitiveSecret456',
            'new_password_confirmation' => 'definitiveSecret456',
        ]);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('', (string) $response->getContent());

        $this->entityManager->clear();
        $storedMerchant = $this->entityManager->getRepository(User::class)->find($merchant->getId());
        self::assertInstanceOf(User::class, $storedMerchant);
        self::assertFalse($storedMerchant->isPasswordChangeRequired());
        self::assertTrue($storedMerchant->isActive());
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertFalse($hasher->isPasswordValid($storedMerchant, 'oldSecret123'));
        self::assertTrue($hasher->isPasswordValid($storedMerchant, 'definitiveSecret456'));

        $storedToken = $this->findInvitationTokenByRawToken($rawToken);
        self::assertNotNull($storedToken->getUsedAt());

        $oldLoginResponse = $this->requestJson('POST', '/api/auth/login', [
            'email' => 'merchant-invitation-complete@example.test',
            'password' => 'oldSecret123',
        ]);
        $newLoginResponse = $this->requestJson('POST', '/api/auth/login', [
            'email' => 'merchant-invitation-complete@example.test',
            'password' => 'definitiveSecret456',
        ]);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $oldLoginResponse->getStatusCode());
        self::assertSame(Response::HTTP_OK, $newLoginResponse->getStatusCode(), (string) $newLoginResponse->getContent());
        self::assertFalse($this->decodeJson($newLoginResponse)['password_change_required']);
    }

    public function testCompletedInvitationTokenCannotBeReusedToChangePasswordAgain(): void
    {
        $merchant = $this->createMerchantWithPassword('merchant-invitation-reuse-after-complete@example.test', 'oldSecret123');
        $merchant->setPasswordChangeRequired(true);
        $this->entityManager->flush();
        $rawToken = $this->createInvitationFor($merchant->getEmail(), $merchant);

        $firstResponse = $this->requestJson('POST', '/api/auth/merchant-invitations/complete', [
            'token' => $rawToken,
            'new_password' => 'definitiveSecret456',
            'new_password_confirmation' => 'definitiveSecret456',
        ]);
        self::assertSame(Response::HTTP_NO_CONTENT, $firstResponse->getStatusCode(), (string) $firstResponse->getContent());

        $secondResponse = $this->requestJson('POST', '/api/auth/merchant-invitations/complete', [
            'token' => $rawToken,
            'new_password' => 'anotherSecret789',
            'new_password_confirmation' => 'anotherSecret789',
        ]);
        self::assertSame(Response::HTTP_BAD_REQUEST, $secondResponse->getStatusCode());
        self::assertStringContainsString('MERCHANT_INVITATION_TOKEN_ALREADY_USED', (string) $secondResponse->getContent());

        $this->entityManager->clear();
        $storedMerchant = $this->entityManager->getRepository(User::class)->find($merchant->getId());
        self::assertInstanceOf(User::class, $storedMerchant);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($storedMerchant, 'definitiveSecret456'));
        self::assertFalse($hasher->isPasswordValid($storedMerchant, 'anotherSecret789'));
        self::assertFalse($storedMerchant->isPasswordChangeRequired());
    }

    public function testCompleteInvitationRejectsPasswordConfirmationMismatch(): void
    {
        $rawToken = $this->createInvitationFor('merchant-invitation-confirm@example.test');

        $response = $this->requestJson('POST', '/api/auth/merchant-invitations/complete', [
            'token' => $rawToken,
            'new_password' => 'definitiveSecret456',
            'new_password_confirmation' => 'anotherSecret456',
        ]);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('MERCHANT_INVITATION_PASSWORD_CONFIRMATION_MISMATCH', (string) $response->getContent());
        self::assertNull($this->findInvitationTokenByRawToken($rawToken)->getUsedAt());
    }

    public function testCompleteInvitationRejectsWeakPassword(): void
    {
        $rawToken = $this->createInvitationFor('merchant-invitation-weak@example.test');

        $response = $this->requestJson('POST', '/api/auth/merchant-invitations/complete', [
            'token' => $rawToken,
            'new_password' => 'short',
            'new_password_confirmation' => 'short',
        ]);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('AUTH_WEAK_PASSWORD', (string) $response->getContent());
        self::assertNull($this->findInvitationTokenByRawToken($rawToken)->getUsedAt());
    }

    public function testExpiredInvitationTokenIsRejected(): void
    {
        $merchant = $this->createMerchantWithPassword('merchant-invitation-expired@example.test', 'oldSecret123');
        $rawToken = 'expired-invitation-token';
        $this->persistInvitationToken($merchant, $rawToken, new \DateTimeImmutable('-1 minute'));

        $verifyResponse = $this->requestJson('POST', '/api/auth/merchant-invitations/verify', [
            'token' => $rawToken,
        ]);
        $completeResponse = $this->requestJson('POST', '/api/auth/merchant-invitations/complete', [
            'token' => $rawToken,
            'new_password' => 'definitiveSecret456',
            'new_password_confirmation' => 'definitiveSecret456',
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $verifyResponse->getStatusCode());
        self::assertStringContainsString('MERCHANT_INVITATION_TOKEN_EXPIRED', (string) $verifyResponse->getContent());
        self::assertSame(Response::HTTP_BAD_REQUEST, $completeResponse->getStatusCode());
        self::assertStringContainsString('MERCHANT_INVITATION_TOKEN_EXPIRED', (string) $completeResponse->getContent());
    }

    public function testUsedInvitationTokenIsRejected(): void
    {
        $rawToken = $this->createInvitationFor('merchant-invitation-used@example.test');
        $this->findInvitationTokenByRawToken($rawToken)->markUsed();
        $this->entityManager->flush();

        $response = $this->requestJson('POST', '/api/auth/merchant-invitations/complete', [
            'token' => $rawToken,
            'new_password' => 'definitiveSecret456',
            'new_password_confirmation' => 'definitiveSecret456',
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertStringContainsString('MERCHANT_INVITATION_TOKEN_ALREADY_USED', (string) $response->getContent());
    }

    public function testAlreadyConsumedInvitationDoesNotChangePassword(): void
    {
        $merchant = $this->createMerchantWithPassword('merchant-invitation-consumed-race@example.test', 'oldSecret123');
        $rawToken = $this->createInvitationFor($merchant->getEmail(), $merchant);
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE merchant_invitation_tokens SET used_at = :usedAt WHERE token_hash = :tokenHash',
            [
                'usedAt' => new \DateTimeImmutable(),
                'tokenHash' => MerchantInvitationTokenManager::hashToken($rawToken),
            ],
            [
                'usedAt' => 'datetime_immutable',
            ],
        );

        $response = $this->requestJson('POST', '/api/auth/merchant-invitations/complete', [
            'token' => $rawToken,
            'new_password' => 'definitiveSecret456',
            'new_password_confirmation' => 'definitiveSecret456',
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertStringContainsString('MERCHANT_INVITATION_TOKEN_ALREADY_USED', (string) $response->getContent());

        $this->entityManager->clear();
        $storedMerchant = $this->entityManager->getRepository(User::class)->find($merchant->getId());
        self::assertInstanceOf(User::class, $storedMerchant);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($storedMerchant, 'oldSecret123'));
        self::assertFalse($hasher->isPasswordValid($storedMerchant, 'definitiveSecret456'));
    }

    public function testRevokedInvitationTokenIsRejected(): void
    {
        $rawToken = $this->createInvitationFor('merchant-invitation-revoked@example.test');
        $this->findInvitationTokenByRawToken($rawToken)->revoke();
        $this->entityManager->flush();

        $response = $this->requestJson('POST', '/api/auth/merchant-invitations/complete', [
            'token' => $rawToken,
            'new_password' => 'definitiveSecret456',
            'new_password_confirmation' => 'definitiveSecret456',
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertStringContainsString('MERCHANT_INVITATION_TOKEN_REVOKED', (string) $response->getContent());
    }

    public function testUnknownInvitationTokenIsRejected(): void
    {
        $response = $this->requestJson('POST', '/api/auth/merchant-invitations/complete', [
            'token' => 'unknown-token',
            'new_password' => 'definitiveSecret456',
            'new_password_confirmation' => 'definitiveSecret456',
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertStringContainsString('MERCHANT_INVITATION_TOKEN_INVALID', (string) $response->getContent());
    }

    public function testResendInvitationRevokesPreviousActiveInvitation(): void
    {
        $admin = $this->createUser('admin-invitation-resend@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchantWithPassword('merchant-invitation-resend@example.test', 'oldSecret123');

        $firstResponse = $this->requestJson(
            'POST',
            \sprintf('/api/admin/merchants/%s/invitation', $merchant->getId()),
            [],
            $admin,
        );
        self::assertSame(Response::HTTP_CREATED, $firstResponse->getStatusCode(), (string) $firstResponse->getContent());
        $firstRawToken = $this->invitationSender()->tokenFor($merchant->getEmail());
        self::assertIsString($firstRawToken);

        $secondResponse = $this->requestJson(
            'POST',
            \sprintf('/api/admin/merchants/%s/invitation/resend', $merchant->getId()),
            [],
            $admin,
        );

        self::assertSame(Response::HTTP_OK, $secondResponse->getStatusCode(), (string) $secondResponse->getContent());
        $secondRawToken = $this->invitationSender()->tokenFor($merchant->getEmail());
        self::assertIsString($secondRawToken);
        self::assertNotSame($firstRawToken, $secondRawToken);

        $this->entityManager->clear();
        $tokens = $this->allInvitationTokens();
        self::assertCount(2, $tokens);
        self::assertNotNull($this->findInvitationTokenByRawToken($firstRawToken)->getRevokedAt());
        self::assertNull($this->findInvitationTokenByRawToken($secondRawToken)->getRevokedAt());

        $firstCompleteResponse = $this->requestJson('POST', '/api/auth/merchant-invitations/complete', [
            'token' => $firstRawToken,
            'new_password' => 'definitiveSecret456',
            'new_password_confirmation' => 'definitiveSecret456',
        ]);
        self::assertSame(Response::HTTP_BAD_REQUEST, $firstCompleteResponse->getStatusCode());
        self::assertStringContainsString('MERCHANT_INVITATION_TOKEN_REVOKED', (string) $firstCompleteResponse->getContent());

        self::assertNotNull($this->findAuditLog('merchant.invitation.resend', $merchant));
    }

    public function testDatabaseRejectsConcurrentPendingInvitationsForSameMerchant(): void
    {
        $merchant = $this->createMerchantWithPassword('merchant-invitation-concurrent@example.test', 'oldSecret123');
        $first = new MerchantInvitationToken(
            merchant: $merchant,
            tokenHash: MerchantInvitationTokenManager::hashToken('first-concurrent-token'),
            expiresAt: new \DateTimeImmutable('+1 hour'),
        );
        $second = new MerchantInvitationToken(
            merchant: $merchant,
            tokenHash: MerchantInvitationTokenManager::hashToken('second-concurrent-token'),
            expiresAt: new \DateTimeImmutable('+1 hour'),
        );

        $this->entityManager->persist($first);
        $this->entityManager->persist($second);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    private function createMerchantWithPassword(string $email, string $plainPassword): User
    {
        $merchant = $this->createUser($email, ['ROLE_MERCHANT'])
            ->setFirstName('Ali')
            ->setLastName('Ben Salah')
            ->setName('Ali Ben Salah');
        $merchant->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($merchant, $plainPassword));
        $this->entityManager->flush();

        return $merchant;
    }

    private function createInvitationFor(string $email, ?User $merchant = null): string
    {
        $admin = $this->createUser('admin-'.$email, ['ROLE_ADMIN']);
        $merchant ??= $this->createMerchantWithPassword($email, 'oldSecret123');

        $response = $this->requestJson(
            'POST',
            \sprintf('/api/admin/merchants/%s/invitation', $merchant->getId()),
            [],
            $admin,
        );
        self::assertContains($response->getStatusCode(), [Response::HTTP_CREATED, Response::HTTP_OK], (string) $response->getContent());

        $rawToken = $this->invitationSender()->tokenFor($email);
        self::assertIsString($rawToken);

        return $rawToken;
    }

    private function persistInvitationToken(User $merchant, string $rawToken, \DateTimeImmutable $expiresAt): MerchantInvitationToken
    {
        $token = new MerchantInvitationToken(
            merchant: $merchant,
            tokenHash: MerchantInvitationTokenManager::hashToken($rawToken),
            expiresAt: $expiresAt,
        );

        $this->entityManager->persist($token);
        $this->entityManager->flush();

        return $token;
    }

    private function singleInvitationToken(): MerchantInvitationToken
    {
        $tokens = $this->allInvitationTokens();
        self::assertCount(1, $tokens);

        return $tokens[0];
    }

    /**
     * @return list<MerchantInvitationToken>
     */
    private function allInvitationTokens(): array
    {
        return array_values(self::getContainer()->get(MerchantInvitationTokenRepository::class)->findBy([], ['createdAt' => 'ASC']));
    }

    private function findInvitationTokenByRawToken(string $rawToken): MerchantInvitationToken
    {
        $token = self::getContainer()
            ->get(MerchantInvitationTokenRepository::class)
            ->findOneByHash(MerchantInvitationTokenManager::hashToken($rawToken));
        self::assertInstanceOf(MerchantInvitationToken::class, $token);

        return $token;
    }

    private function findAuditLog(string $action, User $merchant): ?AdminAuditLog
    {
        return $this->entityManager->getRepository(AdminAuditLog::class)->findOneBy([
            'action' => $action,
            'resourceType' => 'merchant',
            'resourceId' => $merchant->getId()->toRfc4122(),
        ]);
    }

    private function invitationSender(): TestMerchantInvitationSender
    {
        $sender = self::getContainer()->get(TestMerchantInvitationSender::class);
        self::assertInstanceOf(TestMerchantInvitationSender::class, $sender);

        return $sender;
    }
}
