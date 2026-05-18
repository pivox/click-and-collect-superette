<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\User;
use Symfony\Component\Uid\Uuid;

final class MerchantAdminApiTest extends FunctionalApiTestCase
{
    public function testAdminSeesMerchantList(): void
    {
        $admin = $this->createUser('admin-merchants-list@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant(
            'merchant-list@example.test',
            new \DateTimeImmutable('2026-05-18T10:00:00+00:00'),
            firstName: 'Ali',
            lastName: 'Ben Salah',
            phone: '+21600000001',
        );
        $this->createShop($merchant);
        $this->createShop($merchant);
        $this->createUser('customer-not-listed@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson('GET', '/api/admin/merchants', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame(1, $payload['total']);
        self::assertSame(1, $payload['page']);
        self::assertSame(20, $payload['limit']);
        self::assertCount(1, $payload['items']);
        self::assertSame($merchant->getId()->toRfc4122(), $payload['items'][0]['id']);
        self::assertSame('merchant-list@example.test', $payload['items'][0]['email']);
        self::assertSame('Ali', $payload['items'][0]['first_name']);
        self::assertSame('Ben Salah', $payload['items'][0]['last_name']);
        self::assertSame('+21600000001', $payload['items'][0]['phone']);
        self::assertTrue($payload['items'][0]['is_active']);
        self::assertSame(2, $payload['items'][0]['stores_count']);
        self::assertArrayHasKey('created_at', $payload['items'][0]);
        self::assertArrayNotHasKey('password', $payload['items'][0]);
        self::assertArrayNotHasKey('password_hash', $payload['items'][0]);
        self::assertArrayNotHasKey('token', $payload['items'][0]);
    }

    public function testAdminSeesMerchantDetail(): void
    {
        $admin = $this->createUser('admin-merchant-detail@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant(
            'merchant-detail@example.test',
            new \DateTimeImmutable('2026-05-18T09:00:00+00:00'),
            firstName: 'Meriem',
            lastName: 'Trabelsi',
            phone: '+21600000002',
        );
        $merchant->setActive(false);
        $this->createShop($merchant);
        $this->entityManager->flush();

        $response = $this->requestJson('GET', \sprintf('/api/admin/merchants/%s', $merchant->getId()), user: $admin);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($merchant->getId()->toRfc4122(), $payload['id']);
        self::assertSame('merchant-detail@example.test', $payload['email']);
        self::assertSame('Meriem', $payload['first_name']);
        self::assertSame('Trabelsi', $payload['last_name']);
        self::assertSame('+21600000002', $payload['phone']);
        self::assertFalse($payload['is_active']);
        self::assertSame(1, $payload['stores_count']);
        self::assertArrayNotHasKey('password', $payload);
        self::assertArrayNotHasKey('password_hash', $payload);
        self::assertArrayNotHasKey('token', $payload);
    }

    public function testMerchantListPaginationAndDescendingSort(): void
    {
        $admin = $this->createUser('admin-merchants-pagination@example.test', ['ROLE_ADMIN']);
        $oldest = $this->createMerchant('merchant-oldest@example.test', new \DateTimeImmutable('2026-05-18T08:00:00+00:00'));
        $middle = $this->createMerchant('merchant-middle@example.test', new \DateTimeImmutable('2026-05-18T09:00:00+00:00'));
        $newest = $this->createMerchant('merchant-newest@example.test', new \DateTimeImmutable('2026-05-18T10:00:00+00:00'));

        $pageOneResponse = $this->requestJson('GET', '/api/admin/merchants?page=1&limit=2', user: $admin);
        $pageTwoResponse = $this->requestJson('GET', '/api/admin/merchants?page=2&limit=2', user: $admin);

        self::assertSame(200, $pageOneResponse->getStatusCode());
        self::assertSame(200, $pageTwoResponse->getStatusCode());

        $pageOne = $this->decodeJson($pageOneResponse);
        self::assertSame(3, $pageOne['total']);
        self::assertSame(1, $pageOne['page']);
        self::assertSame(2, $pageOne['limit']);
        self::assertSame($newest->getId()->toRfc4122(), $pageOne['items'][0]['id']);
        self::assertSame($middle->getId()->toRfc4122(), $pageOne['items'][1]['id']);

        $pageTwo = $this->decodeJson($pageTwoResponse);
        self::assertSame(3, $pageTwo['total']);
        self::assertSame(2, $pageTwo['page']);
        self::assertSame(2, $pageTwo['limit']);
        self::assertCount(1, $pageTwo['items']);
        self::assertSame($oldest->getId()->toRfc4122(), $pageTwo['items'][0]['id']);
    }

    public function testLimitIsCappedAt50(): void
    {
        $admin = $this->createUser('admin-limit-cap@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('GET', '/api/admin/merchants?limit=100', user: $admin);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(50, $this->decodeJson($response)['limit']);
    }

    public function testMalformedPaginationQueryParamsReturnBadRequest(): void
    {
        $admin = $this->createUser('admin-invalid-admin-merchant-query@example.test', ['ROLE_ADMIN']);

        $pageResponse = $this->requestJson('GET', '/api/admin/merchants?page=abc', user: $admin);
        $limitResponse = $this->requestJson('GET', '/api/admin/merchants?limit=0', user: $admin);

        self::assertSame(400, $pageResponse->getStatusCode());
        self::assertSame(400, $limitResponse->getStatusCode());
    }

    public function testCustomerIsForbidden(): void
    {
        $customer = $this->createUser('customer-admin-merchants-forbidden@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createMerchant('merchant-admin-customer-forbidden@example.test', new \DateTimeImmutable());

        $listResponse = $this->requestJson('GET', '/api/admin/merchants', user: $customer);
        $detailResponse = $this->requestJson('GET', \sprintf('/api/admin/merchants/%s', $merchant->getId()), user: $customer);

        self::assertSame(403, $listResponse->getStatusCode());
        self::assertSame(403, $detailResponse->getStatusCode());
    }

    public function testMerchantIsForbidden(): void
    {
        $merchant = $this->createMerchant('merchant-admin-forbidden@example.test', new \DateTimeImmutable());

        $listResponse = $this->requestJson('GET', '/api/admin/merchants', user: $merchant);
        $detailResponse = $this->requestJson('GET', \sprintf('/api/admin/merchants/%s', $merchant->getId()), user: $merchant);

        self::assertSame(403, $listResponse->getStatusCode());
        self::assertSame(403, $detailResponse->getStatusCode());
    }

    public function testAnonymousIsUnauthorized(): void
    {
        $merchant = $this->createMerchant('merchant-admin-anonymous@example.test', new \DateTimeImmutable());

        $listResponse = $this->requestJson('GET', '/api/admin/merchants');
        $detailResponse = $this->requestJson('GET', \sprintf('/api/admin/merchants/%s', $merchant->getId()));

        self::assertSame(401, $listResponse->getStatusCode());
        self::assertSame(401, $detailResponse->getStatusCode());
    }

    public function testNonExistingMerchantReturnsNotFound(): void
    {
        $admin = $this->createUser('admin-merchant-missing@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('GET', \sprintf('/api/admin/merchants/%s', Uuid::v4()), user: $admin);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testExistingNonMerchantUserReturnsNotFound(): void
    {
        $admin = $this->createUser('admin-non-merchant-detail@example.test', ['ROLE_ADMIN']);
        $customer = $this->createUser('customer-non-merchant-detail@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson('GET', \sprintf('/api/admin/merchants/%s', $customer->getId()), user: $admin);

        self::assertSame(404, $response->getStatusCode());
    }

    // --- S5-004 write operation tests ---

    public function testAdminCreatesMerchant(): void
    {
        $admin = $this->createUser('admin-creates-merchant@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('POST', '/api/admin/merchants', [
            'email' => 'new-merchant@example.test',
            'first_name' => 'Sami',
            'last_name' => 'Bouaziz',
            'phone' => '+21622334455',
            'is_active' => true,
        ], $admin);

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertArrayHasKey('id', $payload);
        self::assertSame('new-merchant@example.test', $payload['email']);
        self::assertSame('Sami', $payload['first_name']);
        self::assertSame('Bouaziz', $payload['last_name']);
        self::assertSame('+21622334455', $payload['phone']);
        self::assertTrue($payload['is_active']);
        self::assertSame(0, $payload['stores_count']);
        self::assertArrayHasKey('created_at', $payload);
        // Password must never appear in the response
        self::assertArrayNotHasKey('password', $payload);
        self::assertArrayNotHasKey('password_hash', $payload);
        self::assertArrayNotHasKey('token', $payload);
    }

    public function testAdminCreatesMerchantWithDefaultsWhenOptionalFieldsAbsent(): void
    {
        $admin = $this->createUser('admin-creates-minimal-merchant@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('POST', '/api/admin/merchants', [
            'email' => 'minimal-merchant@example.test',
            'first_name' => 'Ines',
            'last_name' => 'Karray',
        ], $admin);

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertTrue($payload['is_active']); // default is true
        self::assertNull($payload['phone']);      // phone is optional
    }

    public function testDuplicateEmailReturns422(): void
    {
        $admin = $this->createUser('admin-duplicate-email@example.test', ['ROLE_ADMIN']);
        $this->createMerchant('duplicate@example.test', new \DateTimeImmutable());

        $response = $this->requestJson('POST', '/api/admin/merchants', [
            'email' => 'duplicate@example.test',
            'first_name' => 'Another',
            'last_name' => 'Merchant',
        ], $admin);

        self::assertThat(
            $response->getStatusCode(),
            self::logicalOr(self::equalTo(422), self::equalTo(409)),
        );
    }

    public function testAdminUpdatesMerchant(): void
    {
        $admin = $this->createUser('admin-updates-merchant@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant(
            'merchant-to-update@example.test',
            new \DateTimeImmutable(),
            firstName: 'OldFirst',
            lastName: 'OldLast',
            phone: '+21600000000',
        );

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/merchants/%s', $merchant->getId()),
            [
                'first_name' => 'NewFirst',
                'last_name' => 'NewLast',
                'phone' => '+21699887766',
            ],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($merchant->getId()->toRfc4122(), $payload['id']);
        self::assertSame('NewFirst', $payload['first_name']);
        self::assertSame('NewLast', $payload['last_name']);
        self::assertSame('+21699887766', $payload['phone']);
        self::assertSame('merchant-to-update@example.test', $payload['email']);
        self::assertArrayNotHasKey('password', $payload);
    }

    public function testAdminSuspendsMerchant(): void
    {
        $admin = $this->createUser('admin-suspends-merchant@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-to-suspend@example.test', new \DateTimeImmutable());
        self::assertTrue($merchant->isActive());

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/merchants/%s/suspend', $merchant->getId()),
            [],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertFalse($payload['is_active']);
        self::assertSame($merchant->getId()->toRfc4122(), $payload['id']);
    }

    public function testAdminActivatesMerchant(): void
    {
        $admin = $this->createUser('admin-activates-merchant@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-to-activate@example.test', new \DateTimeImmutable());
        $merchant->setActive(false);
        $this->entityManager->flush();

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/merchants/%s/activate', $merchant->getId()),
            [],
            $admin,
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertTrue($payload['is_active']);
        self::assertSame($merchant->getId()->toRfc4122(), $payload['id']);
    }

    public function testCustomerForbiddenOnPost(): void
    {
        $customer = $this->createUser('customer-forbidden-post@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson('POST', '/api/admin/merchants', [
            'email' => 'should-not-be-created@example.test',
            'first_name' => 'Test',
            'last_name' => 'User',
        ], $customer);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testMerchantForbiddenOnPost(): void
    {
        $merchant = $this->createMerchant('merchant-forbidden-post@example.test', new \DateTimeImmutable());

        $response = $this->requestJson('POST', '/api/admin/merchants', [
            'email' => 'should-not-be-created-by-merchant@example.test',
            'first_name' => 'Test',
            'last_name' => 'User',
        ], $merchant);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testAnonymousUnauthorizedOnPost(): void
    {
        $response = $this->requestJson('POST', '/api/admin/merchants', [
            'email' => 'anon-merchant@example.test',
            'first_name' => 'Anon',
            'last_name' => 'User',
        ]);

        self::assertSame(401, $response->getStatusCode());
    }

    public function testMissingMerchantReturns404OnPatch(): void
    {
        $admin = $this->createUser('admin-patch-missing@example.test', ['ROLE_ADMIN']);
        $unknownId = Uuid::v4()->toRfc4122();

        $patchResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/merchants/%s', $unknownId),
            ['first_name' => 'Ghost'],
            $admin,
        );
        $suspendResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/merchants/%s/suspend', $unknownId),
            [],
            $admin,
        );
        $activateResponse = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/merchants/%s/activate', $unknownId),
            [],
            $admin,
        );

        self::assertSame(404, $patchResponse->getStatusCode());
        self::assertSame(404, $suspendResponse->getStatusCode());
        self::assertSame(404, $activateResponse->getStatusCode());
    }

    public function testInvalidPayloadReturns422(): void
    {
        $admin = $this->createUser('admin-invalid-payload@example.test', ['ROLE_ADMIN']);

        // Missing required first_name and last_name
        $missingFieldsResponse = $this->requestJson('POST', '/api/admin/merchants', [
            'email' => 'valid@example.test',
        ], $admin);

        // Invalid email format
        $badEmailResponse = $this->requestJson('POST', '/api/admin/merchants', [
            'email' => 'not-an-email',
            'first_name' => 'Test',
            'last_name' => 'User',
        ], $admin);

        self::assertSame(422, $missingFieldsResponse->getStatusCode());
        self::assertSame(422, $badEmailResponse->getStatusCode());
    }

    public function testPasswordAndTokenNeverExposedOnCreate(): void
    {
        $admin = $this->createUser('admin-password-check@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('POST', '/api/admin/merchants', [
            'email' => 'password-check@example.test',
            'first_name' => 'Safe',
            'last_name' => 'Merchant',
        ], $admin);

        self::assertSame(201, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);

        // Ensure none of the sensitive field names appear anywhere in the JSON body
        self::assertStringNotContainsStringIgnoringCase('"password"', $content);
        self::assertStringNotContainsStringIgnoringCase('"password_hash"', $content);
        self::assertStringNotContainsStringIgnoringCase('"token"', $content);
        self::assertStringNotContainsStringIgnoringCase('"roles"', $content);
    }

    public function testBlankFirstNameOnPatchReturns422(): void
    {
        $admin = $this->createUser('admin-blank-name@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createMerchant('merchant-blank-name@example.test', new \DateTimeImmutable());

        $response = $this->requestJson(
            'PATCH',
            \sprintf('/api/admin/merchants/%s', $merchant->getId()),
            ['first_name' => ''],
            $admin,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testAnonymousUnauthorizedOnPatch(): void
    {
        $merchant = $this->createMerchant('merchant-patch-anon@example.test', new \DateTimeImmutable());

        $patchResponse = $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s', $merchant->getId()), ['first_name' => 'Ghost']);
        $suspendResponse = $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/suspend', $merchant->getId()));
        $activateResponse = $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/activate', $merchant->getId()));

        self::assertSame(401, $patchResponse->getStatusCode());
        self::assertSame(401, $suspendResponse->getStatusCode());
        self::assertSame(401, $activateResponse->getStatusCode());
    }

    public function testCustomerForbiddenOnPatch(): void
    {
        $customer = $this->createUser('customer-patch-forbidden@example.test', ['ROLE_CUSTOMER']);
        $merchant = $this->createMerchant('merchant-patch-customer@example.test', new \DateTimeImmutable());

        $patchResponse = $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s', $merchant->getId()), ['first_name' => 'Ghost'], $customer);
        $suspendResponse = $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/suspend', $merchant->getId()), [], $customer);
        $activateResponse = $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/activate', $merchant->getId()), [], $customer);

        self::assertSame(403, $patchResponse->getStatusCode());
        self::assertSame(403, $suspendResponse->getStatusCode());
        self::assertSame(403, $activateResponse->getStatusCode());
    }

    public function testMerchantForbiddenOnPatch(): void
    {
        $target = $this->createMerchant('merchant-patch-target@example.test', new \DateTimeImmutable());
        $actor = $this->createMerchant('merchant-patch-actor@example.test', new \DateTimeImmutable());

        $patchResponse = $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s', $target->getId()), ['first_name' => 'Ghost'], $actor);
        $suspendResponse = $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/suspend', $target->getId()), [], $actor);
        $activateResponse = $this->requestJson('PATCH', \sprintf('/api/admin/merchants/%s/activate', $target->getId()), [], $actor);

        self::assertSame(403, $patchResponse->getStatusCode());
        self::assertSame(403, $suspendResponse->getStatusCode());
        self::assertSame(403, $activateResponse->getStatusCode());
    }

    private function createMerchant(
        string $email,
        \DateTimeImmutable $createdAt,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $phone = null,
    ): User {
        $merchant = $this->createUser($email, ['ROLE_MERCHANT'])
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setPhone($phone);

        $this->setPrivateProperty($merchant, 'createdAt', $createdAt);
        $this->setPrivateProperty($merchant, 'updatedAt', $createdAt);
        $this->entityManager->flush();

        return $merchant;
    }
}
