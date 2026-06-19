<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\AdminAuditLog;
use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantInvitationToken;
use App\Entity\MerchantProduct;
use App\Entity\ProductGroup;
use App\Entity\ProductGroupItem;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\ProductGroupStatus;
use App\Enum\ProductGroupVisibility;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use App\Repository\MerchantInvitationTokenRepository;
use App\Service\MerchantInvitationTokenManager;
use App\Tests\Support\MerchantInvitation\TestMerchantInvitationSender;
use Symfony\Component\Uid\Uuid;

final class AdminMerchantOnboardingApiTest extends FunctionalApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->invitationSender()->reset();
    }

    public function testAdminCreatesMerchantAndStoreWithOwnerAndTemporaryPassword(): void
    {
        $admin = $this->createUser('admin-onboarding-create@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('POST', '/api/admin/merchant-onboarding', [
            'merchant' => [
                'email' => 'onboarded-merchant@example.test',
                'first_name' => 'Sami',
                'last_name' => 'Bouaziz',
                'phone' => '+21622334455',
            ],
            'shop' => [
                'name' => 'Supérette El Hana',
                'address' => '12 rue de Tunis',
                'city' => 'Ariana',
                'phone' => '+21671111222',
            ],
            'first_login_mode' => 'temporary_password',
            'product_group_ids' => [],
        ], user: $admin);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $payload = $this->decodeJson($response);

        self::assertSame('onboarded-merchant@example.test', $payload['merchant']['email']);
        self::assertSame('Sami', $payload['merchant']['first_name']);
        self::assertSame('Bouaziz', $payload['merchant']['last_name']);
        self::assertSame(1, $payload['merchant']['stores_count']);
        self::assertArrayNotHasKey('password', $payload['merchant']);
        self::assertArrayNotHasKey('password_hash', $payload['merchant']);
        self::assertArrayNotHasKey('token', $payload['merchant']);

        self::assertSame('Supérette El Hana', $payload['shop']['name']);
        self::assertSame('Ariana', $payload['shop']['city']);
        self::assertSame($payload['merchant']['id'], $payload['shop']['owner']['id']);

        self::assertSame('temporary_password', $payload['first_login']['mode']);
        self::assertIsString($payload['first_login']['temporary_password']);
        self::assertGreaterThanOrEqual(32, \strlen($payload['first_login']['temporary_password']));
        self::assertSame([
            'added_count' => 0,
            'already_existing_count' => 0,
            'ignored_count' => 0,
            'errors' => [],
        ], $payload['catalog_preload']);

        $merchant = $this->entityManager->getRepository(User::class)->find($payload['merchant']['id']);
        self::assertInstanceOf(User::class, $merchant);
        self::assertContains('ROLE_MERCHANT', $merchant->getRoles());
        self::assertNotNull($merchant->getTemporaryPasswordGeneratedAt());
        self::assertNotNull($merchant->getTemporaryPasswordExpiresAt());
        self::assertGreaterThan(
            $merchant->getTemporaryPasswordGeneratedAt()->getTimestamp(),
            $merchant->getTemporaryPasswordExpiresAt()->getTimestamp(),
        );

        $merchantMeResponse = $this->requestJson('GET', '/api/merchant/me', null, $merchant);
        self::assertSame(200, $merchantMeResponse->getStatusCode());
        self::assertTrue($this->decodeJson($merchantMeResponse)['password_change_required']);

        $shop = $this->entityManager->getRepository(Shop::class)->find($payload['shop']['id']);
        self::assertInstanceOf(Shop::class, $shop);
        self::assertTrue($shop->getOwner()?->getId()->equals($merchant->getId()));

        $loginResponse = $this->requestJson('POST', '/api/auth/login', [
            'email' => 'onboarded-merchant@example.test',
            'password' => $payload['first_login']['temporary_password'],
        ]);
        self::assertSame(200, $loginResponse->getStatusCode(), (string) $loginResponse->getContent());
        self::assertFalse($this->passwordHasher()->isPasswordValid($merchant, $payload['first_login']['temporary_password'].'x'));

        $detailResponse = $this->requestJson('GET', \sprintf('/api/admin/merchants/%s', $merchant->getId()), user: $admin);
        self::assertSame(200, $detailResponse->getStatusCode());
        self::assertStringNotContainsString($payload['first_login']['temporary_password'], (string) $detailResponse->getContent());

        foreach (['merchant.create', 'shop.create', 'merchant.owner.attach', 'merchant.temporary_password.create'] as $action) {
            self::assertNotNull($this->findAuditLog($action), $action);
        }
    }

    public function testAdminCreatesMerchantAndStoreWithEmailInvitationWithoutExposingSecret(): void
    {
        $admin = $this->createUser('admin-onboarding-email-invitation@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('POST', '/api/admin/merchant-onboarding', [
            'merchant' => [
                'email' => 'onboarded-invited-merchant@example.test',
                'first_name' => 'Rania',
                'last_name' => 'Mansour',
                'phone' => '+21622112233',
            ],
            'shop' => [
                'name' => 'Supérette Invitation',
                'address' => '8 avenue Habib Bourguiba',
                'city' => 'Tunis',
                'phone' => '+21671123456',
            ],
            'first_login_mode' => 'email_invitation',
            'product_group_ids' => [],
        ], user: $admin);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $payload = $this->decodeJson($response);

        self::assertSame('onboarded-invited-merchant@example.test', $payload['merchant']['email']);
        self::assertSame('Supérette Invitation', $payload['shop']['name']);
        self::assertSame('email_invitation', $payload['first_login']['mode']);
        self::assertNull($payload['first_login']['temporary_password'] ?? null);
        self::assertSame('sent', $payload['first_login']['invitation_status']);
        self::assertArrayHasKey('expires_at', $payload['first_login']);
        self::assertIsString($payload['first_login']['expires_at']);
        self::assertArrayNotHasKey('token', $payload['first_login']);
        self::assertArrayNotHasKey('token_hash', $payload['first_login']);

        $this->assertNoSensitiveKey($payload);

        $merchant = $this->entityManager->getRepository(User::class)->find($payload['merchant']['id']);
        self::assertInstanceOf(User::class, $merchant);
        self::assertContains('ROLE_MERCHANT', $merchant->getRoles());
        self::assertTrue($merchant->isPasswordChangeRequired());
        self::assertNull($merchant->getTemporaryPasswordGeneratedAt());
        self::assertNull($merchant->getTemporaryPasswordExpiresAt());

        $rawToken = $this->invitationSender()->tokenFor('onboarded-invited-merchant@example.test');
        self::assertIsString($rawToken);
        self::assertGreaterThanOrEqual(32, \strlen($rawToken));

        $tokens = $this->allInvitationTokens();
        self::assertCount(1, $tokens);
        $storedToken = $tokens[0];
        self::assertSame($merchant->getId()->toRfc4122(), $storedToken->getMerchant()->getId()->toRfc4122());
        self::assertSame($admin->getId()->toRfc4122(), $storedToken->getCreatedBy()?->getId()->toRfc4122());
        self::assertSame(MerchantInvitationTokenManager::hashToken($rawToken), $storedToken->getTokenHash());
        self::assertNotSame($rawToken, $storedToken->getTokenHash());
        self::assertNull($storedToken->getUsedAt());
        self::assertNull($storedToken->getRevokedAt());

        $auditLog = $this->findAuditLog('merchant.invitation.create');
        self::assertNotNull($auditLog);
        self::assertStringNotContainsString($rawToken, json_encode($auditLog->getMetadata(), \JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString($rawToken, (string) $auditLog->getSummary());
        self::assertNull($this->findAuditLog('merchant.temporary_password.create'));
    }

    public function testAdminCreatesMerchantAndStoreWhenEmailInvitationDeliveryFailsWithoutExposingSecret(): void
    {
        $admin = $this->createUser('admin-onboarding-email-delivery-failed@example.test', ['ROLE_ADMIN']);
        $this->invitationSender()->failNextSend();

        $response = $this->requestJson('POST', '/api/admin/merchant-onboarding', [
            'merchant' => [
                'email' => 'onboarded-delivery-failed@example.test',
                'first_name' => 'Maha',
                'last_name' => 'Bouzid',
            ],
            'shop' => [
                'name' => 'Supérette Delivery Failed',
            ],
            'first_login_mode' => 'email_invitation',
            'product_group_ids' => [],
        ], user: $admin);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $payload = $this->decodeJson($response);
        self::assertSame('email_invitation', $payload['first_login']['mode']);
        self::assertSame('delivery_failed', $payload['first_login']['invitation_status']);
        self::assertNull($payload['first_login']['temporary_password'] ?? null);
        self::assertArrayNotHasKey('token', $payload['first_login']);
        $this->assertNoSensitiveKey($payload);

        $merchant = $this->entityManager->getRepository(User::class)->find($payload['merchant']['id']);
        self::assertInstanceOf(User::class, $merchant);
        $rawToken = $this->invitationSender()->tokenFor('onboarded-delivery-failed@example.test');
        self::assertIsString($rawToken);
        self::assertCount(1, $this->allInvitationTokens());

        $auditLog = $this->findAuditLog('merchant.invitation.delivery_failed');
        self::assertNotNull($auditLog);
        self::assertStringNotContainsString($rawToken, json_encode($auditLog->getMetadata(), \JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString($rawToken, (string) $auditLog->getSummary());
    }

    public function testInvalidFirstLoginModeReturnsValidationError(): void
    {
        $admin = $this->createUser('admin-onboarding-invalid-mode@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('POST', '/api/admin/merchant-onboarding', [
            'merchant' => [
                'email' => 'invalid-mode-onboarding@example.test',
                'first_name' => 'Sami',
                'last_name' => 'Bouaziz',
            ],
            'shop' => [
                'name' => 'Supérette mode invalide',
            ],
            'first_login_mode' => 'sms_invitation',
            'product_group_ids' => [],
        ], user: $admin);

        self::assertSame(422, $response->getStatusCode());
        self::assertNull($this->entityManager->getRepository(User::class)->findOneBy([
            'email' => 'invalid-mode-onboarding@example.test',
        ]));
    }

    public function testNonAdminCannotCreateMerchantOnboarding(): void
    {
        $merchant = $this->createUser('merchant-onboarding-forbidden@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson('POST', '/api/admin/merchant-onboarding', [
            'merchant' => [
                'email' => 'blocked-onboarding@example.test',
                'first_name' => 'Sami',
                'last_name' => 'Bouaziz',
            ],
            'shop' => [
                'name' => 'Supérette interdite',
            ],
            'first_login_mode' => 'temporary_password',
            'product_group_ids' => [],
        ], user: $merchant);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testDuplicateMerchantEmailReturnsClearError(): void
    {
        $admin = $this->createUser('admin-onboarding-duplicate@example.test', ['ROLE_ADMIN']);
        $this->createUser('existing-onboarding@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson('POST', '/api/admin/merchant-onboarding', [
            'merchant' => [
                'email' => 'existing-onboarding@example.test',
                'first_name' => 'Sami',
                'last_name' => 'Bouaziz',
            ],
            'shop' => [
                'name' => 'Supérette doublon',
            ],
            'first_login_mode' => 'temporary_password',
            'product_group_ids' => [],
        ], user: $admin);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('ADMIN_MERCHANT_EMAIL_ALREADY_EXISTS', (string) $response->getContent());
    }

    public function testIncompletePayloadReturnsValidationError(): void
    {
        $admin = $this->createUser('admin-onboarding-invalid@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('POST', '/api/admin/merchant-onboarding', [
            'merchant' => [
                'email' => 'invalid-onboarding@example.test',
                'first_name' => '',
                'last_name' => 'Bouaziz',
            ],
            'shop' => [
                'name' => '',
            ],
            'first_login_mode' => 'temporary_password',
        ], user: $admin);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testWhitespaceMerchantNameReturnsValidationError(): void
    {
        $admin = $this->createUser('admin-onboarding-whitespace-name@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('POST', '/api/admin/merchant-onboarding', [
            'merchant' => [
                'email' => 'whitespace-name-onboarding@example.test',
                'first_name' => '   ',
                'last_name' => '   ',
            ],
            'shop' => [
                'name' => 'Supérette nom invalide',
            ],
            'first_login_mode' => 'temporary_password',
            'product_group_ids' => [],
        ], user: $admin);

        self::assertSame(422, $response->getStatusCode());
        self::assertNull($this->entityManager->getRepository(User::class)->findOneBy([
            'email' => 'whitespace-name-onboarding@example.test',
        ]));
    }

    public function testAnotherMerchantCannotAccessCreatedStore(): void
    {
        $admin = $this->createUser('admin-onboarding-owner-acl@example.test', ['ROLE_ADMIN']);
        $otherMerchant = $this->createUser('other-onboarding-owner-acl@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson('POST', '/api/admin/merchant-onboarding', [
            'merchant' => [
                'email' => 'owner-onboarding@example.test',
                'first_name' => 'Noura',
                'last_name' => 'Kacem',
            ],
            'shop' => [
                'name' => 'Supérette Owner',
            ],
            'first_login_mode' => 'temporary_password',
            'product_group_ids' => [],
        ], user: $admin);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $storeId = $this->decodeJson($response)['shop']['id'];

        $forbiddenResponse = $this->requestJson('GET', \sprintf('/api/merchant/stores/%s/product-groups', $storeId), user: $otherMerchant);

        self::assertSame(403, $forbiddenResponse->getStatusCode());
    }

    public function testAdminOnboardingPreloadsSeveralProductGroupsWithoutDuplicates(): void
    {
        $admin = $this->createUser('admin-onboarding-preload@example.test', ['ROLE_ADMIN']);
        $milk = $this->createProductReference('Lait UHT');
        $water = $this->createProductReference('Eau 1.5L');
        $draftReference = $this->createProductReference('Produit brouillon', ProductReferenceStatus::Draft);
        $essentials = $this->createGroup('Premières nécessités', 'onboarding-necessites', ProductGroupStatus::Published, ProductGroupVisibility::Merchant);
        $breakfast = $this->createGroup('Petit déjeuner', 'onboarding-breakfast', ProductGroupStatus::Published, ProductGroupVisibility::Merchant);
        $this->addItem($essentials, $milk);
        $this->addItem($essentials, $water);
        $this->addItem($essentials, $draftReference);
        $this->addItem($breakfast, $milk);
        $this->entityManager->flush();

        $response = $this->requestJson('POST', '/api/admin/merchant-onboarding', [
            'merchant' => [
                'email' => 'preloaded-merchant@example.test',
                'first_name' => 'Meriem',
                'last_name' => 'Trabelsi',
            ],
            'shop' => [
                'name' => 'Supérette Préchargée',
            ],
            'first_login_mode' => 'temporary_password',
            'product_group_ids' => [
                $essentials->getId()->toRfc4122(),
                $breakfast->getId()->toRfc4122(),
            ],
        ], user: $admin);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $payload = $this->decodeJson($response);
        self::assertSame(2, $payload['catalog_preload']['added_count']);
        self::assertSame(1, $payload['catalog_preload']['already_existing_count']);
        self::assertSame(1, $payload['catalog_preload']['ignored_count']);
        self::assertSame('PRODUCT_REFERENCE_NOT_APPROVED', $payload['catalog_preload']['errors'][0]['code']);

        $shop = $this->entityManager->getRepository(Shop::class)->find($payload['shop']['id']);
        self::assertInstanceOf(Shop::class, $shop);
        $products = $this->entityManager->getRepository(MerchantProduct::class)->findBy(['shop' => $shop]);
        self::assertCount(2, $products);
        foreach ($products as $product) {
            self::assertSame('0.000', $product->getPriceTnd());
            self::assertFalse($product->isVisible());
            self::assertTrue($product->isAvailable());
        }

        $catalogResponse = $this->requestJson('GET', \sprintf('/api/stores/%s/catalog', $shop->getId()));
        self::assertSame(200, $catalogResponse->getStatusCode());
        self::assertSame([], $this->decodeJson($catalogResponse)['items']);

        $auditLog = $this->findAuditLog('catalog.preload.apply');
        self::assertNotNull($auditLog);
        self::assertSame(2, $auditLog->getMetadata()['added_count'] ?? null);
        self::assertSame(1, $auditLog->getMetadata()['already_existing_count'] ?? null);
        self::assertSame(1, $auditLog->getMetadata()['ignored_count'] ?? null);
    }

    public function testUnknownProductGroupReturnsNotFound(): void
    {
        $admin = $this->createUser('admin-onboarding-unknown-group@example.test', ['ROLE_ADMIN']);

        $response = $this->requestJson('POST', '/api/admin/merchant-onboarding', [
            'merchant' => [
                'email' => 'unknown-group-merchant@example.test',
                'first_name' => 'Sami',
                'last_name' => 'Bouaziz',
            ],
            'shop' => [
                'name' => 'Supérette inconnue',
            ],
            'first_login_mode' => 'temporary_password',
            'product_group_ids' => [Uuid::v4()->toRfc4122()],
        ], user: $admin);

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('ADMIN_PRODUCT_GROUP_NOT_FOUND', (string) $response->getContent());
    }

    private function findAuditLog(string $action): ?AdminAuditLog
    {
        return $this->entityManager->getRepository(AdminAuditLog::class)->findOneBy(['action' => $action]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertNoSensitiveKey(array $payload): void
    {
        foreach ($payload as $key => $value) {
            self::assertNotContains($key, ['token', 'token_hash', 'hash', 'password', 'secret']);
            if (\is_array($value)) {
                $this->assertNoSensitiveKey($value);
            }
        }
    }

    /**
     * @return list<MerchantInvitationToken>
     */
    private function allInvitationTokens(): array
    {
        return array_values(self::getContainer()->get(MerchantInvitationTokenRepository::class)->findBy([], ['createdAt' => 'ASC']));
    }

    private function invitationSender(): TestMerchantInvitationSender
    {
        $sender = self::getContainer()->get(TestMerchantInvitationSender::class);
        self::assertInstanceOf(TestMerchantInvitationSender::class, $sender);

        return $sender;
    }

    private function createGroup(
        string $nameFr,
        string $slug,
        ProductGroupStatus $status,
        ProductGroupVisibility $visibility,
    ): ProductGroup {
        $group = (new ProductGroup())
            ->setNameFr($nameFr)
            ->setSlug($slug)
            ->setStatus($status)
            ->setVisibility($visibility);

        $this->entityManager->persist($group);

        return $group;
    }

    private function addItem(ProductGroup $group, ProductReference $reference): ProductGroupItem
    {
        $item = (new ProductGroupItem())->setProductReference($reference);
        $group->addItem($item);
        $this->entityManager->persist($item);

        return $item;
    }

    private function createProductReference(
        string $nameFr,
        ProductReferenceStatus $status = ProductReferenceStatus::Approved,
    ): ProductReference {
        $suffix = substr(str_replace('-', '', Uuid::v4()->toRfc4122()), 0, 12);
        $brand = (new Brand())
            ->setCanonicalName('Onboarding Brand '.$suffix)
            ->setSlug('onboarding-brand-'.$suffix);
        $category = (new Category())
            ->setNameFr('Onboarding Category '.$suffix)
            ->setSlug('onboarding-category-'.$suffix);
        $reference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr($nameFr)
            ->setUnit(ProductUnit::Piece)
            ->setStatus($status);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($reference);

        return $reference;
    }

    private function passwordHasher(): \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface
    {
        return self::getContainer()->get(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class);
    }
}
