<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class MerchantAccountApiTest extends FunctionalApiTestCase
{
    public function testMerchantCanUpdateAllowedProfileFields(): void
    {
        $merchant = $this->createMerchantWithPassword('merchant-acc@example.test', 'secret123');

        $response = $this->requestJson('PATCH', '/api/merchant/me', [
            'first_name' => 'Ali',
            'last_name' => 'Ben Salah',
            'phone' => '+21620111222',
        ], $merchant);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame('Ali', $payload['first_name']);
        self::assertSame('Ben Salah', $payload['last_name']);
        self::assertSame('+21620111222', $payload['phone']);
        self::assertSame('Ali Ben Salah', $payload['name']);
        self::assertSame('merchant-acc@example.test', $payload['email']);

        $this->entityManager->clear();
        $stored = $this->entityManager->getRepository(User::class)->find($merchant->getId());
        self::assertInstanceOf(User::class, $stored);
        self::assertSame('Ali', $stored->getFirstName());
        self::assertSame('Ben Salah', $stored->getLastName());
        self::assertSame('+21620111222', $stored->getPhone());
        self::assertSame('Ali Ben Salah', $stored->getName());
    }

    public function testMerchantCannotUpdateEmailFromAccountProfile(): void
    {
        $merchant = $this->createMerchantWithPassword('merchant-acc@example.test', 'secret123');

        $response = $this->requestJson('PATCH', '/api/merchant/me', ['email' => 'New.Email@Example.test'], $merchant);

        self::assertSame(422, $response->getStatusCode());

        $this->entityManager->clear();
        $stored = $this->entityManager->getRepository(User::class)->find($merchant->getId());
        self::assertInstanceOf(User::class, $stored);
        self::assertSame('merchant-acc@example.test', $stored->getEmail());
    }

    public function testProfileUpdateDoesNotReturnSensitiveFields(): void
    {
        $merchant = $this->createMerchantWithPassword('merchant-acc@example.test', 'secret123');

        $response = $this->requestJson('PATCH', '/api/merchant/me', ['first_name' => 'Ali'], $merchant);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertArrayNotHasKey('roles', $payload);
        self::assertArrayNotHasKey('token', $payload);
        self::assertArrayNotHasKey('password', $payload);
        self::assertArrayNotHasKey('passwordHash', $payload);
        self::assertArrayNotHasKey('password_hash', $payload);
        self::assertArrayNotHasKey('resetToken', $payload);
        self::assertArrayNotHasKey('reset_token', $payload);
        self::assertArrayNotHasKey('invitationToken', $payload);
        self::assertArrayNotHasKey('invitation_token', $payload);
        self::assertArrayNotHasKey('temporaryPassword', $payload);
        self::assertArrayNotHasKey('temporary_password', $payload);
        self::assertArrayNotHasKey('owner', $payload);
        self::assertArrayNotHasKey('shopOwner', $payload);
        self::assertArrayNotHasKey('shop_id', $payload);
        self::assertArrayNotHasKey('status', $payload);
        self::assertArrayNotHasKey('active', $payload);
        self::assertArrayNotHasKey('is_active', $payload);
        self::assertArrayNotHasKey('deletedAt', $payload);
        self::assertArrayNotHasKey('deleted_at', $payload);
    }

    #[DataProvider('forbiddenProfileFieldProvider')]
    public function testSensitiveProfileFieldsAreRejected(string $field, mixed $value): void
    {
        $merchant = $this->createMerchantWithPassword('merchant-acc@example.test', 'secret123');
        $originalPassword = $merchant->getPassword();

        $response = $this->requestJson('PATCH', '/api/merchant/me', [$field => $value], $merchant);

        self::assertContains($response->getStatusCode(), [400, 422], $field);

        $this->entityManager->clear();
        $stored = $this->entityManager->getRepository(User::class)->find($merchant->getId());
        self::assertInstanceOf(User::class, $stored);
        self::assertSame(['ROLE_MERCHANT', 'ROLE_USER'], $stored->getRoles());
        self::assertTrue($stored->isActive());
        self::assertSame($originalPassword, $stored->getPassword());
    }

    /**
     * @return iterable<string, array{0: string, 1: mixed}>
     */
    public static function forbiddenProfileFieldProvider(): iterable
    {
        yield 'id' => ['id', 'user-2'];
        yield 'user_id' => ['user_id', 'user-2'];
        yield 'name' => ['name', 'Nouveau Nom'];
        yield 'roles' => ['roles', ['ROLE_ADMIN']];
        yield 'is_active' => ['is_active', false];
        yield 'active' => ['active', false];
        yield 'status' => ['status', 'suspended'];
        yield 'owner' => ['owner', 'other-user'];
        yield 'shop_owner' => ['shopOwner', 'other-user'];
        yield 'shop_id' => ['shop_id', 'shop-1'];
        yield 'passwordHash' => ['passwordHash', 'hash'];
        yield 'password' => ['password', 'secret456'];
        yield 'plainPassword' => ['plainPassword', 'secret456'];
        yield 'resetToken' => ['resetToken', 'reset-token'];
        yield 'invitationToken' => ['invitationToken', 'invitation-token'];
        yield 'temporaryPassword' => ['temporaryPassword', 'temporary-password'];
        yield 'deleted_at' => ['deleted_at', '2026-06-18T10:00:00+00:00'];
        yield 'last_login_at' => ['last_login_at', '2026-06-18T10:00:00+00:00'];
    }

    public function testBlankFirstNameReturns422(): void
    {
        $merchant = $this->createMerchantWithPassword('merchant-acc@example.test', 'secret123');

        $response = $this->requestJson('PATCH', '/api/merchant/me', ['first_name' => '   '], $merchant);

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
        self::assertSame('', (string) $change->getContent());

        // Verify at the hash level (env-independent: avoids JWT keypair dependency).
        $this->entityManager->clear();
        $stored = $this->entityManager->getRepository(User::class)->find($merchant->getId());
        self::assertInstanceOf(User::class, $stored);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($stored, 'brandNewSecret456'));
        self::assertFalse($hasher->isPasswordValid($stored, 'secret123'));
    }

    public function testNewPasswordAllowsLoginAndOldPasswordIsRejectedAfterChange(): void
    {
        $merchant = $this->createMerchantWithPassword('merchant-login-after-change@example.test', 'secret123');

        $change = $this->requestJson('PATCH', '/api/merchant/me/password', [
            'current_password' => 'secret123',
            'new_password' => 'brandNewSecret456',
        ], $merchant);
        self::assertSame(204, $change->getStatusCode());

        $oldLogin = $this->requestJson('POST', '/api/auth/login', [
            'email' => 'merchant-login-after-change@example.test',
            'password' => 'secret123',
        ]);
        self::assertSame(401, $oldLogin->getStatusCode());

        $newLogin = $this->requestJson('POST', '/api/auth/login', [
            'email' => 'merchant-login-after-change@example.test',
            'password' => 'brandNewSecret456',
        ]);
        self::assertSame(200, $newLogin->getStatusCode());
        $payload = $this->decodeJson($newLogin);
        self::assertArrayHasKey('token', $payload);
        self::assertArrayNotHasKey('password', $payload);
        self::assertArrayNotHasKey('passwordHash', $payload);
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
