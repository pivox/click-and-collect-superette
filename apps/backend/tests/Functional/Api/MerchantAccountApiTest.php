<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class MerchantAccountApiTest extends FunctionalApiTestCase
{
    public function testMerchantCanUpdateName(): void
    {
        $merchant = $this->createMerchantWithPassword('merchant-acc@example.test', 'secret123');

        $response = $this->requestJson('PATCH', '/api/merchant/me', ['name' => 'Nouveau Nom'], $merchant);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('Nouveau Nom', $payload['name']);
        self::assertSame('merchant-acc@example.test', $payload['email']);
        self::assertSame(['ROLE_MERCHANT'], $payload['roles']);
    }

    public function testMerchantCanUpdateEmail(): void
    {
        $merchant = $this->createMerchantWithPassword('merchant-acc@example.test', 'secret123');

        $response = $this->requestJson('PATCH', '/api/merchant/me', ['email' => 'New.Email@Example.test'], $merchant);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('new.email@example.test', $payload['email']);

        // Email is the JWT identity claim → a fresh token must be returned so the
        // client can swap it instead of being logged out.
        self::assertArrayHasKey('token', $payload);
        self::assertNotSame('', $payload['token']);

        $this->entityManager->clear();
        $stored = $this->entityManager->getRepository(User::class)->find($merchant->getId());
        self::assertInstanceOf(User::class, $stored);
        self::assertSame('new.email@example.test', $stored->getEmail());
    }

    public function testNameOnlyUpdateDoesNotReturnToken(): void
    {
        $merchant = $this->createMerchantWithPassword('merchant-acc@example.test', 'secret123');

        $response = $this->requestJson('PATCH', '/api/merchant/me', ['name' => 'Sans Email'], $merchant);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertArrayNotHasKey('token', $payload);
    }

    public function testEmailAlreadyTakenReturns409(): void
    {
        $this->createUser('taken@example.test', ['ROLE_MERCHANT']);
        $merchant = $this->createMerchantWithPassword('merchant-acc@example.test', 'secret123');

        $response = $this->requestJson('PATCH', '/api/merchant/me', ['email' => 'taken@example.test'], $merchant);

        self::assertSame(409, $response->getStatusCode());
    }

    public function testBlankNameReturns422(): void
    {
        $merchant = $this->createMerchantWithPassword('merchant-acc@example.test', 'secret123');

        $response = $this->requestJson('PATCH', '/api/merchant/me', ['name' => '   '], $merchant);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testMerchantCanChangePassword(): void
    {
        $merchant = $this->createMerchantWithPassword('merchant-acc@example.test', 'secret123');

        $change = $this->requestJson('PATCH', '/api/merchant/me/password', [
            'current_password' => 'secret123',
            'new_password' => 'brandNewSecret456',
        ], $merchant);
        self::assertSame(204, $change->getStatusCode());

        // Verify at the hash level (env-independent: avoids JWT keypair dependency).
        $this->entityManager->clear();
        $stored = $this->entityManager->getRepository(User::class)->find($merchant->getId());
        self::assertInstanceOf(User::class, $stored);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($stored, 'brandNewSecret456'));
        self::assertFalse($hasher->isPasswordValid($stored, 'secret123'));
    }

    public function testWrongCurrentPasswordReturns422(): void
    {
        $merchant = $this->createMerchantWithPassword('merchant-acc@example.test', 'secret123');

        $response = $this->requestJson('PATCH', '/api/merchant/me/password', [
            'current_password' => 'wrongPassword',
            'new_password' => 'brandNewSecret456',
        ], $merchant);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testWeakNewPasswordReturns422(): void
    {
        $merchant = $this->createMerchantWithPassword('merchant-acc@example.test', 'secret123');

        $response = $this->requestJson('PATCH', '/api/merchant/me/password', [
            'current_password' => 'secret123',
            'new_password' => 'short',
        ], $merchant);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testSuspendedMerchantIsDenied(): void
    {
        $merchant = $this->createMerchantWithPassword('merchant-acc@example.test', 'secret123');
        $merchant->setActive(false);
        $this->entityManager->flush();

        $response = $this->requestJson('PATCH', '/api/merchant/me', ['name' => 'Tentative'], $merchant);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testCustomerIsDenied(): void
    {
        $customer = $this->createUser('customer-acc@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson('PATCH', '/api/merchant/me', ['name' => 'Tentative'], $customer);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testAnonymousIsDenied(): void
    {
        $response = $this->requestJson('PATCH', '/api/merchant/me', ['name' => 'Tentative']);

        self::assertContains($response->getStatusCode(), [401, 403]);
    }

    private function createMerchantWithPassword(string $email, string $password): User
    {
        $merchant = $this->createUser($email, ['ROLE_MERCHANT']);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $merchant->setPassword($hasher->hashPassword($merchant, $password));
        $this->entityManager->flush();

        return $merchant;
    }
}
