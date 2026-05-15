<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class CustomerProfileApiTest extends FunctionalApiTestCase
{
    public function testCustomerCanReadOwnProfile(): void
    {
        $customer = $this->createCustomer('client.profile@example.test');

        $response = $this->requestJson('GET', '/api/me/profile', user: $customer);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame($customer->getId()->toRfc4122(), $payload['id']);
        self::assertSame('client.profile@example.test', $payload['email']);
        self::assertSame(['ROLE_CUSTOMER', 'ROLE_USER'], $payload['roles']);
        self::assertSame('Haythem', $payload['first_name']);
        self::assertSame('Mabrouk', $payload['last_name']);
        self::assertSame('Haythem Mabrouk', $payload['name']);
        self::assertSame('+21600000000', $payload['phone']);
    }

    public function testProfileResponseNeverContainsSensitiveFields(): void
    {
        $customer = $this->createCustomer('client.profile-nohash@example.test');

        $response = $this->requestJson('GET', '/api/me/profile', user: $customer);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        self::assertIsString($content);
        self::assertStringNotContainsString('password', $content);
        self::assertStringNotContainsString('password_hash', $content);
        self::assertStringNotContainsString('reset', $content);
        self::assertStringNotContainsString('active', $content);
    }

    public function testAnonymousCannotReadProfile(): void
    {
        $response = $this->requestJson('GET', '/api/me/profile');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testCustomerCanPatchProfile(): void
    {
        $customer = $this->createCustomer('client.profile-patch@example.test');

        $response = $this->requestJson('PATCH', '/api/me/profile', [
            'first_name' => 'Amina',
            'last_name' => 'Trabelsi',
            'phone' => '+21611111111',
        ], $customer);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame($customer->getId()->toRfc4122(), $payload['id']);
        self::assertSame('client.profile-patch@example.test', $payload['email']);
        self::assertSame(['ROLE_CUSTOMER', 'ROLE_USER'], $payload['roles']);
        self::assertSame('Amina', $payload['first_name']);
        self::assertSame('Trabelsi', $payload['last_name']);
        self::assertSame('Amina Trabelsi', $payload['name']);
        self::assertSame('+21611111111', $payload['phone']);

        $this->entityManager->clear();
        $stored = $this->findUserByEmail('client.profile-patch@example.test');
        self::assertInstanceOf(User::class, $stored);
        self::assertSame('Amina', $stored->getFirstName());
        self::assertSame('Trabelsi', $stored->getLastName());
        self::assertSame('Amina Trabelsi', $stored->getName());
        self::assertSame('+21611111111', $stored->getPhone());
    }

    public function testAnonymousCannotPatchProfile(): void
    {
        $response = $this->requestJson('PATCH', '/api/me/profile', [
            'first_name' => 'Amina',
            'last_name' => 'Trabelsi',
            'phone' => '+21611111111',
        ]);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testCustomerCanPatchDocumentedName(): void
    {
        $customer = $this->createCustomer('client.profile-name@example.test');

        $response = $this->requestJson('PATCH', '/api/me/profile', [
            'name' => 'Client Documente',
        ], $customer);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame('Client Documente', $payload['name']);
        self::assertSame('Haythem', $payload['first_name']);
        self::assertSame('Mabrouk', $payload['last_name']);

        $this->entityManager->clear();
        $stored = $this->findUserByEmail('client.profile-name@example.test');
        self::assertInstanceOf(User::class, $stored);
        self::assertSame('Client Documente', $stored->getName());
        self::assertSame('Haythem', $stored->getFirstName());
        self::assertSame('Mabrouk', $stored->getLastName());
    }

    public function testPatchOnlyPhoneDoesNotAffectOtherFields(): void
    {
        $customer = $this->createCustomer('client.profile-phone-only@example.test');

        $response = $this->requestJson('PATCH', '/api/me/profile', [
            'phone' => '+21699999999',
        ], $customer);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $this->entityManager->clear();
        $stored = $this->findUserByEmail('client.profile-phone-only@example.test');
        self::assertInstanceOf(User::class, $stored);
        self::assertSame('Haythem', $stored->getFirstName());
        self::assertSame('Mabrouk', $stored->getLastName());
        self::assertSame('Haythem Mabrouk', $stored->getName());
        self::assertSame('+21699999999', $stored->getPhone());
    }

    public function testPhoneCanBeClearedExplicitly(): void
    {
        $customer = $this->createCustomer('client.profile-clear-phone@example.test');

        $response = $this->requestJson('PATCH', '/api/me/profile', [
            'phone' => null,
        ], $customer);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertNull($payload['phone']);

        $this->entityManager->clear();
        $stored = $this->findUserByEmail('client.profile-clear-phone@example.test');
        self::assertInstanceOf(User::class, $stored);
        self::assertNull($stored->getPhone());
    }

    #[DataProvider('blankNamePayloadProvider')]
    public function testBlankProfileNamesReturn422(array $payload): void
    {
        $customer = $this->createCustomer('client.profile-blank-name@example.test');

        $response = $this->requestJson('PATCH', '/api/me/profile', $payload, $customer);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testInvalidPhoneReturns422(): void
    {
        $customer = $this->createCustomer('client.profile-invalid-phone@example.test');

        $response = $this->requestJson('PATCH', '/api/me/profile', [
            'phone' => 'hello',
        ], $customer);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testPatchPayloadCannotChangeRoles(): void
    {
        $customer = $this->createCustomer('client.profile-role@example.test');

        $response = $this->requestJson('PATCH', '/api/me/profile', [
            'first_name' => 'Amina',
            'last_name' => 'Trabelsi',
            'roles' => ['ROLE_ADMIN'],
        ], $customer);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame(['ROLE_CUSTOMER', 'ROLE_USER'], $payload['roles']);

        $this->entityManager->clear();
        $stored = $this->findUserByEmail('client.profile-role@example.test');
        self::assertInstanceOf(User::class, $stored);
        self::assertNotContains('ROLE_ADMIN', $stored->getRoles());
        self::assertNotContains('ROLE_MERCHANT', $stored->getRoles());
    }

    public function testPatchPayloadCannotChangeEmail(): void
    {
        $customer = $this->createCustomer('client.profile-email@example.test');

        $response = $this->requestJson('PATCH', '/api/me/profile', [
            'email' => 'changed@example.test',
            'first_name' => 'Amina',
        ], $customer);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame('client.profile-email@example.test', $payload['email']);
        self::assertNull($this->findUserByEmail('changed@example.test'));
        self::assertInstanceOf(User::class, $this->findUserByEmail('client.profile-email@example.test'));
    }

    public function testPatchPayloadCannotChangeId(): void
    {
        $customer = $this->createCustomer('client.profile-id@example.test');
        $originalId = $customer->getId()->toRfc4122();

        $response = $this->requestJson('PATCH', '/api/me/profile', [
            'id' => Uuid::v4()->toRfc4122(),
            'last_name' => 'Trabelsi',
        ], $customer);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertSame($originalId, $payload['id']);

        $this->entityManager->clear();
        $stored = $this->findUserByEmail('client.profile-id@example.test');
        self::assertInstanceOf(User::class, $stored);
        self::assertSame($originalId, $stored->getId()->toRfc4122());
    }

    public function testPatchPayloadCannotChangePassword(): void
    {
        $customer = $this->createCustomer('client.profile-password@example.test');
        $passwordHash = $customer->getPassword();

        $response = $this->requestJson('PATCH', '/api/me/profile', [
            'password' => 'newSecret123',
            'phone' => '+21622222222',
        ], $customer);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $this->entityManager->clear();
        $stored = $this->findUserByEmail('client.profile-password@example.test');
        self::assertInstanceOf(User::class, $stored);
        self::assertSame($passwordHash, $stored->getPassword());

        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertFalse($passwordHasher->isPasswordValid($stored, 'newSecret123'));
    }

    public function testMerchantCannotUseCustomerProfileEndpoint(): void
    {
        $merchant = $this->createUser('merchant.profile@example.test', ['ROLE_MERCHANT']);

        $getResponse = $this->requestJson('GET', '/api/me/profile', user: $merchant);
        $patchResponse = $this->requestJson('PATCH', '/api/me/profile', [
            'first_name' => 'Marchand',
        ], $merchant);

        self::assertSame(Response::HTTP_FORBIDDEN, $getResponse->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $patchResponse->getStatusCode());
    }

    private function createCustomer(string $email): User
    {
        $customer = $this->createUser($email, ['ROLE_CUSTOMER'])
            ->setFirstName('Haythem')
            ->setLastName('Mabrouk')
            ->setName('Haythem Mabrouk')
            ->setPhone('+21600000000');

        $customer->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($customer, 'secret123'));

        $this->entityManager->flush();

        return $customer;
    }

    private function findUserByEmail(string $email): ?User
    {
        return self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function blankNamePayloadProvider(): iterable
    {
        yield 'blank first name' => [['first_name' => '']];
        yield 'blank last name' => [['last_name' => '']];
        yield 'blank name' => [['name' => '']];
    }
}
