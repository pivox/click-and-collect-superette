<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CustomerRegistrationApiTest extends FunctionalApiTestCase
{
    public function testVisitorCanRegisterCustomerAccount(): void
    {
        $response = $this->requestJson('POST', '/api/auth/register/customer', $this->validRegistrationPayload());

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertIsString($payload['id']);
        self::assertSame('client.registration@example.test', $payload['email']);
        self::assertSame(['ROLE_CUSTOMER'], $payload['roles']);
        self::assertSame('Haythem', $payload['first_name']);
        self::assertSame('Mabrouk', $payload['last_name']);
        self::assertSame('+21600000000', $payload['phone']);

        $user = $this->findUserByEmail('client.registration@example.test');
        self::assertInstanceOf(User::class, $user);
        self::assertContains('ROLE_CUSTOMER', $user->getRoles());
        self::assertNotContains('ROLE_ADMIN', $user->getRoles());
        self::assertNotSame('secret123', $user->getPassword());
        self::assertSame('Haythem Mabrouk', $user->getName());
    }

    public function testRegistrationResponseNeverContainsPasswordHash(): void
    {
        $response = $this->requestJson('POST', '/api/auth/register/customer', $this->validRegistrationPayload([
            'email' => 'client.nohash@example.test',
        ]));

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $content = $response->getContent();
        self::assertIsString($content);
        self::assertStringNotContainsString('password', $content);
        self::assertStringNotContainsString('password_hash', $content);
        self::assertStringNotContainsString('active', $content);
    }

    public function testRegistrationNormalizesEmailToLowercase(): void
    {
        $response = $this->requestJson('POST', '/api/auth/register/customer', $this->validRegistrationPayload([
            'email' => '  Client.MixedCase@Example.TEST  ',
        ]));

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame('client.mixedcase@example.test', $payload['email']);
        self::assertInstanceOf(User::class, $this->findUserByEmail('client.mixedcase@example.test'));
    }

    public function testDuplicateEmailReturns409(): void
    {
        $this->createUser('client.duplicate@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson('POST', '/api/auth/register/customer', $this->validRegistrationPayload([
            'email' => ' CLIENT.DUPLICATE@example.test ',
        ]));

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertStringContainsString('AUTH_EMAIL_ALREADY_EXISTS', (string) $response->getContent());
    }

    public function testInvalidEmailReturns422(): void
    {
        $response = $this->requestJson('POST', '/api/auth/register/customer', $this->validRegistrationPayload([
            'email' => 'not-an-email',
        ]));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testWeakOrEmptyPasswordReturns422(): void
    {
        $weakPasswordResponse = $this->requestJson('POST', '/api/auth/register/customer', $this->validRegistrationPayload([
            'email' => 'client.weak-password@example.test',
            'password' => 'short',
        ]));
        $emptyPasswordResponse = $this->requestJson('POST', '/api/auth/register/customer', $this->validRegistrationPayload([
            'email' => 'client.empty-password@example.test',
            'password' => '',
        ]));

        self::assertSame(422, $weakPasswordResponse->getStatusCode());
        self::assertSame(422, $emptyPasswordResponse->getStatusCode());
    }

    public function testPayloadCannotCreateAdminRole(): void
    {
        $response = $this->requestJson('POST', '/api/auth/register/customer', $this->validRegistrationPayload([
            'email' => 'client.role-injection@example.test',
            'roles' => ['ROLE_ADMIN'],
        ]));

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame(['ROLE_CUSTOMER'], $payload['roles']);

        $user = $this->findUserByEmail('client.role-injection@example.test');
        self::assertInstanceOf(User::class, $user);
        self::assertNotContains('ROLE_ADMIN', $user->getRoles());
        self::assertNotContains('ROLE_MERCHANT', $user->getRoles());
    }

    public function testCreatedCustomerCanLoginWithExistingJwtEndpoint(): void
    {
        $this->requestJson('POST', '/api/auth/register/customer', $this->validRegistrationPayload([
            'email' => 'client.jwt-login@example.test',
            'password' => 'secret123',
        ]));

        $loginResponse = $this->requestJson('POST', '/api/auth/login', [
            'email' => 'client.jwt-login@example.test',
            'password' => 'secret123',
        ]);

        self::assertSame(Response::HTTP_OK, $loginResponse->getStatusCode());

        $payload = $this->decodeJson($loginResponse);
        self::assertArrayHasKey('token', $payload);
        self::assertIsString($payload['token']);
        self::assertNotSame('', $payload['token']);
    }

    public function testCreatedCustomerCannotAccessMerchantRoute(): void
    {
        $this->requestJson('POST', '/api/auth/register/customer', $this->validRegistrationPayload([
            'email' => 'client.no-merchant-access@example.test',
        ]));
        $customer = $this->findUserByEmail('client.no-merchant-access@example.test');
        self::assertInstanceOf(User::class, $customer);

        $shop = $this->createShop($this->createUser('merchant.registration-route@example.test', ['ROLE_MERCHANT']));

        $response = $this->requestJson(
            'GET',
            \sprintf('/api/merchant/stores/%s/dashboard/today', $shop->getId()),
            user: $customer,
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testPersistedPasswordIsValidHash(): void
    {
        $this->requestJson('POST', '/api/auth/register/customer', $this->validRegistrationPayload([
            'email' => 'client.password-hash@example.test',
            'password' => 'secret123',
        ]));

        $user = $this->findUserByEmail('client.password-hash@example.test');
        self::assertInstanceOf(User::class, $user);

        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($passwordHasher->isPasswordValid($user, 'secret123'));
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function validRegistrationPayload(array $overrides = []): array
    {
        return array_replace([
            'email' => 'client.registration@example.test',
            'password' => 'secret123',
            'first_name' => 'Haythem',
            'last_name' => 'Mabrouk',
            'phone' => '+21600000000',
        ], $overrides);
    }

    private function findUserByEmail(string $email): ?User
    {
        return self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
    }
}
