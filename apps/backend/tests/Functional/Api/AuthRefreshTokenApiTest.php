<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use App\Service\PasswordResetTokenManager;
use App\Service\RefreshTokenManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class AuthRefreshTokenApiTest extends FunctionalApiTestCase
{
    public function testLoginReturnsRefreshTokenAndExpiresIn(): void
    {
        $this->createCustomer('client.refresh-login@example.test');

        $response = $this->login('client.refresh-login@example.test');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertArrayHasKey('token', $payload);
        self::assertArrayHasKey('password_change_required', $payload);
        self::assertSame(3600, $payload['expires_in']);
        self::assertIsString($payload['refresh_token']);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $payload['refresh_token']);

        // Only the sha256 hash is stored — never the raw token.
        $tokens = $this->allTokens();
        self::assertCount(1, $tokens);
        self::assertNotSame($payload['refresh_token'], $tokens[0]->getTokenHash());
        self::assertSame(RefreshTokenManager::hashToken($payload['refresh_token']), $tokens[0]->getTokenHash());
        self::assertNull($tokens[0]->getRevokedAt());
    }

    public function testEachLoginStartsItsOwnFamilyAndStoresDeviceLabel(): void
    {
        $this->createCustomer('client.refresh-multi@example.test');

        $this->login('client.refresh-multi@example.test', extra: ['device_label' => '  Pixel 7 de Haythem  ']);
        $this->login('client.refresh-multi@example.test');

        $tokens = $this->allTokens();
        self::assertCount(2, $tokens);
        // Multi-device: both tokens stay active, in two distinct rotation families.
        self::assertNull($tokens[0]->getRevokedAt());
        self::assertNull($tokens[1]->getRevokedAt());
        self::assertFalse($tokens[0]->getFamilyId()->equals($tokens[1]->getFamilyId()));

        $labels = array_map(static fn (RefreshToken $token): ?string => $token->getDeviceLabel(), $tokens);
        self::assertContains('Pixel 7 de Haythem', $labels);
    }

    public function testRefreshRotatesTokenAndIssuesUsableJwt(): void
    {
        $this->createCustomer('client.refresh-rotate@example.test');
        $firstRefreshToken = $this->loginAndGetRefreshToken('client.refresh-rotate@example.test');

        $response = $this->requestJson('POST', '/api/auth/refresh', ['refresh_token' => $firstRefreshToken]);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(3600, $payload['expires_in']);
        self::assertIsString($payload['refresh_token']);
        self::assertNotSame($firstRefreshToken, $payload['refresh_token']);

        // The presented token is consumed; the new one lives in the same family.
        $consumed = $this->tokenByRaw($firstRefreshToken);
        $issued = $this->tokenByRaw($payload['refresh_token']);
        self::assertNotNull($consumed->getRevokedAt());
        self::assertNotNull($consumed->getLastUsedAt());
        self::assertNull($issued->getRevokedAt());
        self::assertTrue($consumed->getFamilyId()->equals($issued->getFamilyId()));

        // The freshly minted JWT authenticates a protected customer route.
        $profileResponse = $this->requestWithBearer('GET', '/api/me/profile', (string) $payload['token']);
        self::assertSame(Response::HTTP_OK, $profileResponse->getStatusCode());
    }

    public function testReusingConsumedTokenRevokesWholeFamily(): void
    {
        $this->createCustomer('client.refresh-reuse@example.test');
        $firstRefreshToken = $this->loginAndGetRefreshToken('client.refresh-reuse@example.test');

        $rotation = $this->requestJson('POST', '/api/auth/refresh', ['refresh_token' => $firstRefreshToken]);
        self::assertSame(Response::HTTP_OK, $rotation->getStatusCode());
        $secondRefreshToken = (string) $this->decodeJson($rotation)['refresh_token'];

        // Replay of the consumed token → theft detection.
        $replay = $this->requestJson('POST', '/api/auth/refresh', ['refresh_token' => $firstRefreshToken]);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $replay->getStatusCode());
        self::assertStringContainsString('AUTH_REFRESH_TOKEN_REUSED', (string) $replay->getContent());

        foreach ($this->allTokens() as $token) {
            self::assertNotNull($token->getRevokedAt());
        }

        // The legitimate successor is dead too: full re-login required.
        $successor = $this->requestJson('POST', '/api/auth/refresh', ['refresh_token' => $secondRefreshToken]);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $successor->getStatusCode());
        self::assertStringContainsString('AUTH_REFRESH_TOKEN_INVALID', (string) $successor->getContent());
    }

    public function testUnknownTokenReturns401Invalid(): void
    {
        $response = $this->requestJson('POST', '/api/auth/refresh', ['refresh_token' => 'unknown-refresh-token']);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertStringContainsString('AUTH_REFRESH_TOKEN_INVALID', (string) $response->getContent());
    }

    public function testMissingTokenReturns422(): void
    {
        $response = $this->requestJson('POST', '/api/auth/refresh', ['refresh_token' => '']);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testExpiredTokenReturns401Invalid(): void
    {
        $customer = $this->createCustomer('client.refresh-expired@example.test');
        $rawToken = $this->issueToken($customer, expiresAt: new \DateTimeImmutable('-1 minute'));

        $response = $this->requestJson('POST', '/api/auth/refresh', ['refresh_token' => $rawToken]);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertStringContainsString('AUTH_REFRESH_TOKEN_INVALID', (string) $response->getContent());
    }

    public function testLoggedOutTokenReturns401InvalidNotReused(): void
    {
        $customer = $this->createCustomer('client.refresh-revoked@example.test');
        $rawToken = $this->loginAndGetRefreshToken('client.refresh-revoked@example.test');

        $logout = $this->requestJson('POST', '/api/auth/logout', ['refresh_token' => $rawToken], $customer);
        self::assertSame(Response::HTTP_NO_CONTENT, $logout->getStatusCode());

        // An administratively revoked token is not a rotation replay: generic
        // 401 without family-wide revocation side effects.
        $response = $this->requestJson('POST', '/api/auth/refresh', ['refresh_token' => $rawToken]);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertStringContainsString('AUTH_REFRESH_TOKEN_INVALID', (string) $response->getContent());
    }

    public function testSuspendedAccountReturns401AccountDisabledWithoutNewToken(): void
    {
        $customer = $this->createCustomer('client.refresh-suspended@example.test');
        $rawToken = $this->loginAndGetRefreshToken('client.refresh-suspended@example.test');

        $customer->setActive(false);
        $this->entityManager->flush();

        $response = $this->requestJson('POST', '/api/auth/refresh', ['refresh_token' => $rawToken]);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertStringContainsString('AUTH_ACCOUNT_DISABLED', (string) $response->getContent());

        // No new token minted, and existing ones are revoked defensively.
        $tokens = $this->allTokens();
        self::assertCount(1, $tokens);
        self::assertNotNull($tokens[0]->getRevokedAt());
    }

    public function testSoftDeletedAccountReturns401AccountDisabled(): void
    {
        $customer = $this->createCustomer('client.refresh-deleted@example.test');
        $rawToken = $this->loginAndGetRefreshToken('client.refresh-deleted@example.test');

        $customer->setDeletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $response = $this->requestJson('POST', '/api/auth/refresh', ['refresh_token' => $rawToken]);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertStringContainsString('AUTH_ACCOUNT_DISABLED', (string) $response->getContent());
    }

    public function testLogoutRevokesPresentedToken(): void
    {
        $customer = $this->createCustomer('client.logout-one@example.test');
        $rawToken = $this->loginAndGetRefreshToken('client.logout-one@example.test');

        $response = $this->requestJson('POST', '/api/auth/logout', ['refresh_token' => $rawToken], $customer);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertNotNull($this->tokenByRaw($rawToken)->getRevokedAt());
    }

    public function testLogoutAllRevokesEveryDevice(): void
    {
        $customer = $this->createCustomer('client.logout-all@example.test');
        $this->loginAndGetRefreshToken('client.logout-all@example.test');
        $this->loginAndGetRefreshToken('client.logout-all@example.test');

        $response = $this->requestJson('POST', '/api/auth/logout', ['all' => true], $customer);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $tokens = $this->allTokens();
        self::assertCount(2, $tokens);
        foreach ($tokens as $token) {
            self::assertNotNull($token->getRevokedAt());
        }
    }

    public function testLogoutRequiresAuthentication(): void
    {
        $response = $this->requestJson('POST', '/api/auth/logout', []);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testLogoutIgnoresTokenOfAnotherUser(): void
    {
        $this->createCustomer('client.logout-victim@example.test');
        $other = $this->createCustomer('client.logout-other@example.test');
        $victimToken = $this->loginAndGetRefreshToken('client.logout-victim@example.test');

        $response = $this->requestJson('POST', '/api/auth/logout', ['refresh_token' => $victimToken], $other);

        // 204 either way (no enumeration), but the victim's token stays active.
        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertNull($this->tokenByRaw($victimToken)->getRevokedAt());
    }

    public function testPasswordResetRevokesAllRefreshTokens(): void
    {
        $customer = $this->createCustomer('client.reset-revoke@example.test');
        $this->loginAndGetRefreshToken('client.reset-revoke@example.test');
        $this->loginAndGetRefreshToken('client.reset-revoke@example.test');

        // Re-fetch: the HTTP logins above may have reset the entity manager,
        // leaving the $customer instance detached.
        $managedCustomer = $this->entityManager->find(User::class, $customer->getId());
        self::assertInstanceOf(User::class, $managedCustomer);
        $resetToken = self::getContainer()->get(PasswordResetTokenManager::class)->createForUser($managedCustomer);
        $this->entityManager->flush();

        $response = $this->requestJson('POST', '/api/auth/password-reset/confirm', [
            'token' => $resetToken,
            'new_password' => 'newSecret123',
        ]);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $tokens = $this->allTokens();
        self::assertCount(2, $tokens);
        foreach ($tokens as $token) {
            self::assertNotNull($token->getRevokedAt());
        }
    }

    public function testPasswordChangeRejectsARefreshTokenMissedByRevocation(): void
    {
        $customer = $this->createCustomer('client.refresh-credential-change@example.test');
        $rawToken = $this->issueToken($customer, new \DateTimeImmutable('+30 days'));
        $customer->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($customer, 'newSecret123'));
        $this->entityManager->flush();

        // A concurrent issuer can escape the revocation snapshot. Its old
        // credentials must still prevent this token from extending the session.
        self::assertFalse($this->tokenByRaw($rawToken)->isRevoked());
        $response = $this->requestJson('POST', '/api/auth/refresh', ['refresh_token' => $rawToken]);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertStringContainsString('AUTH_REFRESH_TOKEN_INVALID', (string) $response->getContent());
        self::assertCount(1, $this->allTokens());
        self::assertTrue($this->tokenByRaw($rawToken)->isRevoked());
    }

    public function testSuccessorPersistedAfterRevocationSnapshotCannotExtendSession(): void
    {
        $customer = $this->createCustomer('client.refresh-late-successor@example.test');
        $firstRaw = $this->issueToken($customer, new \DateTimeImmutable('+30 days'));
        $first = $this->tokenByRaw($firstRaw);
        $first->consumeForRotation();
        $this->entityManager->flush();
        $manager = self::getContainer()->get(RefreshTokenManager::class);
        // Rotation has read the old password and queued its successor, but has
        // not flushed yet. The password-change SELECT cannot see that row.
        $lateRaw = $manager->issue($customer, familyId: $first->getFamilyId());
        self::assertSame(0, $manager->revokeAllForUser($customer));
        $customer->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($customer, 'newSecret123'));
        $this->entityManager->flush();
        self::assertFalse($this->tokenByRaw($lateRaw)->isRevoked());

        $response = $this->requestJson('POST', '/api/auth/refresh', ['refresh_token' => $lateRaw]);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertStringContainsString('AUTH_REFRESH_TOKEN_INVALID', (string) $response->getContent());
        self::assertCount(2, $this->allTokens());
        self::assertTrue($this->tokenByRaw($lateRaw)->isRevoked());
    }

    public function testLegacyTokenFailsClosedWithoutRevokingAFreshLogin(): void
    {
        $customer = $this->createCustomer('client.refresh-legacy@example.test');
        $legacyRaw = $this->issueToken($customer, new \DateTimeImmutable('+30 days'));
        (new \ReflectionProperty(RefreshToken::class, 'credentialHash'))->setValue($this->tokenByRaw($legacyRaw), null);
        $this->entityManager->flush();
        $freshRaw = $this->issueToken($customer, new \DateTimeImmutable('+30 days'));

        $response = $this->requestJson('POST', '/api/auth/refresh', ['refresh_token' => $legacyRaw]);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertFalse($this->tokenByRaw($freshRaw)->isRevoked());
        self::assertSame(Response::HTTP_OK, $this->requestJson('POST', '/api/auth/refresh', ['refresh_token' => $freshRaw])->getStatusCode());
    }

    public function testAdminSuspendMerchantRevokesRefreshTokens(): void
    {
        $admin = $this->createUser('admin.suspend-revoke@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant.suspend-revoke@example.test');
        $this->loginAndGetRefreshToken('merchant.suspend-revoke@example.test');

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/merchants/%s/suspend', $merchant->getId()->toRfc4122()),
            [],
            $admin,
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $tokens = $this->allTokens();
        self::assertCount(1, $tokens);
        self::assertNotNull($tokens[0]->getRevokedAt());
    }

    public function testCustomerAccountDeletionRevokesRefreshTokens(): void
    {
        $customer = $this->createCustomer('client.delete-revoke@example.test');
        $this->loginAndGetRefreshToken('client.delete-revoke@example.test');

        $response = $this->requestJson('DELETE', '/api/me/account', null, $customer);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $tokens = $this->allTokens();
        self::assertCount(1, $tokens);
        self::assertNotNull($tokens[0]->getRevokedAt());
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function login(string $email, string $password = 'secret123', array $extra = []): Response
    {
        return $this->requestJson('POST', '/api/auth/login', array_merge([
            'email' => $email,
            'password' => $password,
        ], $extra));
    }

    private function loginAndGetRefreshToken(string $email, string $password = 'secret123'): string
    {
        $response = $this->login($email, $password);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $refreshToken = $this->decodeJson($response)['refresh_token'];
        self::assertIsString($refreshToken);

        return $refreshToken;
    }

    private function requestWithBearer(string $method, string $path, string $jwt): Response
    {
        $request = Request::create($path, $method, server: [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$jwt,
        ]);

        return self::$kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
    }

    private function createCustomer(string $email, string $password = 'secret123'): User
    {
        return $this->createPasswordUser($email, ['ROLE_CUSTOMER'], $password);
    }

    private function createMerchant(string $email, string $password = 'secret123'): User
    {
        return $this->createPasswordUser($email, ['ROLE_MERCHANT'], $password);
    }

    /**
     * @param list<string> $roles
     */
    private function createPasswordUser(string $email, array $roles, string $password): User
    {
        $user = $this->createUser($email, $roles);
        $user->setPassword(
            self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, $password),
        );
        $this->entityManager->flush();

        return $user;
    }

    private function issueToken(User $user, \DateTimeImmutable $expiresAt): string
    {
        $rawToken = RefreshTokenManager::generateRawToken();
        $token = new RefreshToken(
            $user,
            RefreshTokenManager::hashToken($rawToken),
            Uuid::v4(),
            $expiresAt,
        );
        $this->entityManager->persist($token);
        $this->entityManager->flush();

        return $rawToken;
    }

    /**
     * @return list<RefreshToken>
     */
    private function allTokens(): array
    {
        return $this->repository()->findBy([], ['createdAt' => 'ASC']);
    }

    private function tokenByRaw(string $rawToken): RefreshToken
    {
        $token = $this->repository()->findOneByHash(RefreshTokenManager::hashToken($rawToken));
        self::assertInstanceOf(RefreshToken::class, $token);

        return $token;
    }

    private function repository(): RefreshTokenRepository
    {
        return self::getContainer()->get(RefreshTokenRepository::class);
    }
}
