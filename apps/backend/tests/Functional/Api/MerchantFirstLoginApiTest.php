<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Shop;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class MerchantFirstLoginApiTest extends FunctionalApiTestCase
{
    public function testLoginAndMerchantMeExposePasswordChangeRequired(): void
    {
        [$merchant, $temporaryPassword] = $this->createOnboardedMerchant('merchant-first-login@example.test');

        $loginResponse = $this->requestJson('POST', '/api/auth/login', [
            'email' => 'merchant-first-login@example.test',
            'password' => $temporaryPassword,
        ]);
        self::assertSame(200, $loginResponse->getStatusCode(), (string) $loginResponse->getContent());
        self::assertTrue($this->decodeJson($loginResponse)['password_change_required']);

        $meResponse = $this->requestJson('GET', '/api/merchant/me', null, $merchant);
        self::assertSame(200, $meResponse->getStatusCode());
        $payload = $this->decodeJson($meResponse);
        self::assertTrue($payload['password_change_required']);
        self::assertArrayNotHasKey('password', $payload);
        self::assertArrayNotHasKey('passwordHash', $payload);
        self::assertArrayNotHasKey('password_hash', $payload);
        self::assertArrayNotHasKey('temporary_password', $payload);
        self::assertArrayNotHasKey('token', $payload);
    }

    public function testMerchantWithRequiredPasswordChangeCannotAccessBusinessEndpoint(): void
    {
        [$merchant, , $shop] = $this->createOnboardedMerchant('merchant-first-login-blocked@example.test');

        $response = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/dashboard/today', $shop->getId()), null, $merchant);

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('MERCHANT_PASSWORD_CHANGE_REQUIRED', (string) $response->getContent());
    }

    public function testWrongTemporaryPasswordIsRejected(): void
    {
        [$merchant] = $this->createOnboardedMerchant('merchant-first-login-wrong@example.test');

        $response = $this->requestJson('POST', '/api/merchant/first-login/change-password', [
            'current_password' => 'wrongSecret123',
            'new_password' => 'definitiveSecret456',
            'new_password_confirmation' => 'definitiveSecret456',
        ], $merchant);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('MERCHANT_CURRENT_PASSWORD_INVALID', (string) $response->getContent());
    }

    public function testWeakNewPasswordIsRejected(): void
    {
        [$merchant, $temporaryPassword] = $this->createOnboardedMerchant('merchant-first-login-weak@example.test');

        $response = $this->requestJson('POST', '/api/merchant/first-login/change-password', [
            'current_password' => $temporaryPassword,
            'new_password' => 'short',
            'new_password_confirmation' => 'short',
        ], $merchant);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testPasswordConfirmationMismatchIsRejected(): void
    {
        [$merchant, $temporaryPassword] = $this->createOnboardedMerchant('merchant-first-login-confirm@example.test');

        $response = $this->requestJson('POST', '/api/merchant/first-login/change-password', [
            'current_password' => $temporaryPassword,
            'new_password' => 'definitiveSecret456',
            'new_password_confirmation' => 'anotherSecret456',
        ], $merchant);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('MERCHANT_PASSWORD_CONFIRMATION_MISMATCH', (string) $response->getContent());
    }

    public function testSuccessfulFirstLoginPasswordChangeClearsFlagAndAllowsBusinessEndpoint(): void
    {
        [$merchant, $temporaryPassword, $shop] = $this->createOnboardedMerchant('merchant-first-login-success@example.test');

        $changeResponse = $this->requestJson('POST', '/api/merchant/first-login/change-password', [
            'current_password' => $temporaryPassword,
            'new_password' => 'definitiveSecret456',
            'new_password_confirmation' => 'definitiveSecret456',
        ], $merchant);
        self::assertSame(204, $changeResponse->getStatusCode(), (string) $changeResponse->getContent());
        self::assertSame('', (string) $changeResponse->getContent());

        $this->entityManager->clear();
        $stored = $this->entityManager->getRepository(User::class)->find($merchant->getId());
        self::assertInstanceOf(User::class, $stored);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertFalse($hasher->isPasswordValid($stored, $temporaryPassword));
        self::assertTrue($hasher->isPasswordValid($stored, 'definitiveSecret456'));

        $oldLogin = $this->requestJson('POST', '/api/auth/login', [
            'email' => 'merchant-first-login-success@example.test',
            'password' => $temporaryPassword,
        ]);
        self::assertSame(401, $oldLogin->getStatusCode());

        $newLogin = $this->requestJson('POST', '/api/auth/login', [
            'email' => 'merchant-first-login-success@example.test',
            'password' => 'definitiveSecret456',
        ]);
        self::assertSame(200, $newLogin->getStatusCode(), (string) $newLogin->getContent());
        self::assertFalse($this->decodeJson($newLogin)['password_change_required']);

        $meResponse = $this->requestJson('GET', '/api/merchant/me', null, $stored);
        self::assertSame(200, $meResponse->getStatusCode());
        self::assertFalse($this->decodeJson($meResponse)['password_change_required']);

        $dashboardResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/dashboard/today', $shop->getId()), null, $stored);
        self::assertSame(200, $dashboardResponse->getStatusCode(), (string) $dashboardResponse->getContent());
    }

    public function testFirstLoginEndpointRequiresPendingPasswordChange(): void
    {
        $merchant = $this->createMerchantWithPassword('merchant-first-login-not-required@example.test', 'currentSecret123');
        $this->createShop($merchant);

        $response = $this->requestJson('POST', '/api/merchant/first-login/change-password', [
            'current_password' => 'currentSecret123',
            'new_password' => 'definitiveSecret456',
            'new_password_confirmation' => 'definitiveSecret456',
        ], $merchant);

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('MERCHANT_PASSWORD_CHANGE_NOT_REQUIRED', (string) $response->getContent());
    }

    private function createMerchantWithPassword(string $email, string $password): User
    {
        $merchant = $this->createUser($email, ['ROLE_MERCHANT']);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $merchant->setPassword($hasher->hashPassword($merchant, $password));
        $this->entityManager->flush();

        return $merchant;
    }

    /**
     * @return array{0: User, 1: string, 2: Shop}
     */
    private function createOnboardedMerchant(string $email): array
    {
        $admin = $this->createUser('admin-'.$email, ['ROLE_ADMIN']);

        $response = $this->requestJson('POST', '/api/admin/merchant-onboarding', [
            'merchant' => [
                'email' => $email,
                'first_name' => 'Ali',
                'last_name' => 'Ben Salah',
            ],
            'shop' => [
                'name' => 'Supérette '.$email,
            ],
            'first_login_mode' => 'temporary_password',
            'product_group_ids' => [],
        ], $admin);
        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $payload = $this->decodeJson($response);

        $merchant = $this->entityManager->getRepository(User::class)->find($payload['merchant']['id']);
        $shop = $this->entityManager->getRepository(Shop::class)->find($payload['shop']['id']);
        self::assertInstanceOf(User::class, $merchant);
        self::assertInstanceOf(Shop::class, $shop);

        return [$merchant, $payload['first_login']['temporary_password'], $shop];
    }
}
