<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Processor\PasswordResetRequestProcessor;
use App\Repository\PasswordResetTokenRepository;
use App\Service\PasswordResetTokenManager;
use App\Tests\Support\PasswordReset\TestPasswordResetTokenSender;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PasswordResetApiTest extends FunctionalApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->tokenSender()->reset();
    }

    public function testPasswordResetRequestWithExistingCustomerReturnsNeutral202(): void
    {
        $this->createCustomer('client.reset-request@example.test');

        $response = $this->requestJson('POST', '/api/auth/password-reset/request', [
            'email' => '  Client.Reset-Request@Example.TEST  ',
        ]);

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        self::assertSame(['message' => PasswordResetRequestProcessor::NEUTRAL_MESSAGE], $this->decodeJson($response));
        self::assertCount(1, $this->allTokens());
        self::assertIsString($this->tokenSender()->tokenFor('client.reset-request@example.test'));
    }

    public function testPasswordResetRequestWithUnknownEmailReturnsSameNeutral202(): void
    {
        $response = $this->requestJson('POST', '/api/auth/password-reset/request', [
            'email' => 'unknown@example.test',
        ]);

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        self::assertSame(['message' => PasswordResetRequestProcessor::NEUTRAL_MESSAGE], $this->decodeJson($response));
        self::assertCount(0, $this->allTokens());
    }

    public function testPasswordResetRequestWithInvalidEmailReturns422(): void
    {
        $response = $this->requestJson('POST', '/api/auth/password-reset/request', [
            'email' => 'not-an-email',
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testPasswordResetRequestDoesNotRevealIfEmailExists(): void
    {
        $this->createCustomer('client.reset-neutral@example.test');

        $knownResponse = $this->requestJson('POST', '/api/auth/password-reset/request', [
            'email' => 'client.reset-neutral@example.test',
        ]);
        $unknownResponse = $this->requestJson('POST', '/api/auth/password-reset/request', [
            'email' => 'missing.reset-neutral@example.test',
        ]);

        self::assertSame($knownResponse->getStatusCode(), $unknownResponse->getStatusCode());
        self::assertSame($knownResponse->getContent(), $unknownResponse->getContent());
    }

    public function testPasswordResetRequestCreatesTokenOnlyForExistingCustomer(): void
    {
        $this->createCustomer('client.reset-token@example.test');
        $this->createUser('merchant.reset-token@example.test', ['ROLE_MERCHANT']);

        $this->requestJson('POST', '/api/auth/password-reset/request', [
            'email' => 'client.reset-token@example.test',
        ]);
        $this->requestJson('POST', '/api/auth/password-reset/request', [
            'email' => 'missing.reset-token@example.test',
        ]);
        $this->requestJson('POST', '/api/auth/password-reset/request', [
            'email' => 'merchant.reset-token@example.test',
        ]);

        $tokens = $this->allTokens();
        self::assertCount(1, $tokens);
        self::assertSame('client.reset-token@example.test', $tokens[0]->getUser()->getEmail());
        self::assertIsString($this->tokenSender()->tokenFor('client.reset-token@example.test'));
        self::assertNull($this->tokenSender()->tokenFor('merchant.reset-token@example.test'));
    }

    public function testDocumentedForgotPasswordAliasRequestsReset(): void
    {
        $this->createCustomer('client.reset-alias@example.test');

        $response = $this->requestJson('POST', '/api/auth/forgot-password', [
            'email' => 'client.reset-alias@example.test',
        ]);

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        self::assertSame(['message' => PasswordResetRequestProcessor::NEUTRAL_MESSAGE], $this->decodeJson($response));
        self::assertIsString($this->tokenSender()->tokenFor('client.reset-alias@example.test'));
    }

    public function testDeliveredResetTokenCanConfirmPasswordReset(): void
    {
        $this->createCustomer('client.reset-delivered@example.test', 'secret123');

        $this->requestJson('POST', '/api/auth/password-reset/request', [
            'email' => 'client.reset-delivered@example.test',
        ]);

        $rawToken = $this->tokenSender()->tokenFor('client.reset-delivered@example.test');
        self::assertIsString($rawToken);

        $response = $this->requestJson('POST', '/api/auth/password-reset/confirm', [
            'token' => $rawToken,
            'new_password' => 'newSecret123',
        ]);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testStoredTokenIsHashedAndDifferentFromRawToken(): void
    {
        $customer = $this->createCustomer('client.reset-hash@example.test');

        $rawToken = $this->createResetToken($customer);
        $storedToken = $this->singleToken();

        self::assertNotSame($rawToken, $storedToken->getTokenHash());
        self::assertSame(64, \strlen($storedToken->getTokenHash()));
        self::assertSame(PasswordResetTokenManager::hashToken($rawToken), $storedToken->getTokenHash());
    }

    public function testNewPasswordResetInvalidatesPreviousActiveTokens(): void
    {
        $customer = $this->createCustomer('client.reset-invalidate@example.test');

        $firstRawToken = $this->createResetToken($customer);
        $secondRawToken = $this->createResetToken($customer);
        $this->entityManager->clear();

        $tokens = $this->allTokens();
        self::assertCount(2, $tokens);
        self::assertNotNull($this->findTokenByRawToken($firstRawToken)->getConsumedAt());
        self::assertNull($this->findTokenByRawToken($secondRawToken)->getConsumedAt());

        $response = $this->requestJson('POST', '/api/auth/password-reset/confirm', [
            'token' => $firstRawToken,
            'new_password' => 'newSecret123',
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertStringContainsString('AUTH_RESET_TOKEN_ALREADY_USED', (string) $response->getContent());
    }

    public function testPasswordResetRequestEndpointInvalidatesPreviousActiveTokens(): void
    {
        $this->createCustomer('client.reset-request-invalidate@example.test');

        $this->requestJson('POST', '/api/auth/password-reset/request', [
            'email' => 'client.reset-request-invalidate@example.test',
        ]);
        $this->requestJson('POST', '/api/auth/password-reset/request', [
            'email' => 'client.reset-request-invalidate@example.test',
        ]);

        $tokens = $this->allTokens();
        self::assertCount(2, $tokens);
        self::assertSame(1, \count(array_filter(
            $tokens,
            static fn (PasswordResetToken $token): bool => null !== $token->getConsumedAt(),
        )));
        self::assertSame(1, \count(array_filter(
            $tokens,
            static fn (PasswordResetToken $token): bool => null === $token->getConsumedAt(),
        )));
    }

    public function testPasswordResetConfirmWithValidTokenReturns204AndConsumesToken(): void
    {
        $customer = $this->createCustomer('client.reset-confirm@example.test');
        $rawToken = $this->createResetToken($customer);

        $response = $this->requestJson('POST', '/api/auth/password-reset/confirm', [
            'token' => $rawToken,
            'new_password' => 'newSecret123',
        ]);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame('', $response->getContent());

        $this->entityManager->clear();
        $storedToken = $this->singleToken();
        self::assertNotNull($storedToken->getConsumedAt());
    }

    public function testDocumentedResetPasswordAliasConfirmsReset(): void
    {
        $customer = $this->createCustomer('client.reset-confirm-alias@example.test');
        $rawToken = $this->createResetToken($customer);

        $response = $this->requestJson('POST', '/api/auth/reset-password', [
            'token' => $rawToken,
            'new_password' => 'newSecret123',
        ]);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testCustomerCanLoginWithNewPasswordAfterReset(): void
    {
        $customer = $this->createCustomer('client.reset-login@example.test', 'secret123');
        $rawToken = $this->createResetToken($customer);

        $this->requestJson('POST', '/api/auth/password-reset/confirm', [
            'token' => $rawToken,
            'new_password' => 'newSecret123',
        ]);

        $newLoginResponse = $this->requestJson('POST', '/api/auth/login', [
            'email' => 'client.reset-login@example.test',
            'password' => 'newSecret123',
        ]);
        $oldLoginResponse = $this->requestJson('POST', '/api/auth/login', [
            'email' => 'client.reset-login@example.test',
            'password' => 'secret123',
        ]);

        self::assertSame(Response::HTTP_OK, $newLoginResponse->getStatusCode());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $oldLoginResponse->getStatusCode());
    }

    public function testUnknownTokenReturns400(): void
    {
        $response = $this->requestJson('POST', '/api/auth/password-reset/confirm', [
            'token' => 'unknown-token',
            'new_password' => 'newSecret123',
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertStringContainsString('AUTH_RESET_TOKEN_INVALID', (string) $response->getContent());
    }

    public function testEmptyTokenReturns422(): void
    {
        $response = $this->requestJson('POST', '/api/auth/password-reset/confirm', [
            'token' => '',
            'new_password' => 'newSecret123',
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testExpiredTokenReturns400(): void
    {
        $customer = $this->createCustomer('client.reset-expired@example.test');
        $rawToken = 'expired-reset-token';
        $this->entityManager->persist(new PasswordResetToken(
            $customer,
            PasswordResetTokenManager::hashToken($rawToken),
            new \DateTimeImmutable('-1 minute'),
        ));
        $this->entityManager->flush();

        $response = $this->requestJson('POST', '/api/auth/password-reset/confirm', [
            'token' => $rawToken,
            'new_password' => 'newSecret123',
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertStringContainsString('AUTH_RESET_TOKEN_EXPIRED', (string) $response->getContent());
    }

    public function testConsumedTokenReturns400(): void
    {
        $customer = $this->createCustomer('client.reset-consumed@example.test');
        $rawToken = $this->createResetToken($customer);
        $this->singleToken()->consume();
        $this->entityManager->flush();

        $response = $this->requestJson('POST', '/api/auth/password-reset/confirm', [
            'token' => $rawToken,
            'new_password' => 'newSecret123',
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertStringContainsString('AUTH_RESET_TOKEN_ALREADY_USED', (string) $response->getContent());
    }

    public function testWeakNewPasswordReturns422(): void
    {
        $customer = $this->createCustomer('client.reset-weak@example.test');
        $rawToken = $this->createResetToken($customer);

        $response = $this->requestJson('POST', '/api/auth/password-reset/confirm', [
            'token' => $rawToken,
            'new_password' => 'short',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('AUTH_WEAK_PASSWORD', (string) $response->getContent());
    }

    private function createCustomer(string $email, string $password = 'secret123'): User
    {
        $customer = $this->createUser($email, ['ROLE_CUSTOMER'])
            ->setFirstName('Haythem')
            ->setLastName('Mabrouk')
            ->setName('Haythem Mabrouk')
            ->setPhone('+21600000000');

        $customer->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($customer, $password));

        $this->entityManager->flush();

        return $customer;
    }

    private function createResetToken(User $customer): string
    {
        $rawToken = self::getContainer()->get(PasswordResetTokenManager::class)->createForUser($customer);
        $this->entityManager->flush();

        return $rawToken;
    }

    private function tokenSender(): TestPasswordResetTokenSender
    {
        $sender = self::getContainer()->get(TestPasswordResetTokenSender::class);
        self::assertInstanceOf(TestPasswordResetTokenSender::class, $sender);

        return $sender;
    }

    private function singleToken(): PasswordResetToken
    {
        $tokens = $this->allTokens();
        self::assertCount(1, $tokens);

        return $tokens[0];
    }

    private function findTokenByRawToken(string $rawToken): PasswordResetToken
    {
        $token = self::getContainer()
            ->get(PasswordResetTokenRepository::class)
            ->findOneByHash(PasswordResetTokenManager::hashToken($rawToken));
        self::assertInstanceOf(PasswordResetToken::class, $token);

        return $token;
    }

    /**
     * @return list<PasswordResetToken>
     */
    private function allTokens(): array
    {
        $tokens = self::getContainer()->get(PasswordResetTokenRepository::class)->findBy([], ['createdAt' => 'ASC']);

        return array_values($tokens);
    }
}
