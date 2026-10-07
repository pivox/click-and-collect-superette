<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\CatalogPhotoImportImage;
use App\Entity\MerchantMembership;
use App\Entity\MerchantOrganization;
use App\Entity\Shop;
use App\Entity\User;
use App\Service\CatalogPhotoQuotaLedger;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * CATALOG-AI-001 (issue #638) — session persistence and the shared 10-photo
 * quota ledger. Real file storage (#639) and AI dispatch (#641) are out of
 * scope; this covers the quota math, draft lifecycle and multi-account access.
 */
final class CatalogPhotoImportApiTest extends FunctionalApiTestCase
{
    public function testFreshShopGetsTenAvailableCreditsLazily(): void
    {
        $merchant = $this->createUser('quota-fresh@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $response = $this->requestJson('GET', $this->quotaUrl($shop), user: $merchant);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($shop->getId()->toRfc4122(), $payload['store_id']);
        self::assertSame(10, $payload['granted']);
        self::assertSame(0, $payload['reserved']);
        self::assertSame(0, $payload['consumed']);
        self::assertSame(10, $payload['available']);
    }

    public function testCreatingASecondDraftSessionDoesNotRecreateTheAllowance(): void
    {
        $merchant = $this->createUser('quota-no-double-grant@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);

        $this->createDraftSession($shop, $merchant);
        $this->createDraftSession($shop, $merchant);

        $response = $this->requestJson('GET', $this->quotaUrl($shop), user: $merchant);
        self::assertSame(10, $this->decodeJson($response)['granted']);
    }

    public function testQuotaAndSessionsAreForbiddenToAForeignMerchant(): void
    {
        $owner = $this->createUser('session-owner@example.test', ['ROLE_MERCHANT']);
        $foreign = $this->createUser('session-foreign@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($owner);

        self::assertSame(403, $this->requestJson('GET', $this->quotaUrl($shop), user: $foreign)->getStatusCode());
        self::assertSame(403, $this->requestJson('GET', $this->sessionsUrl($shop), user: $foreign)->getStatusCode());
        self::assertSame(
            403,
            $this->requestJson('POST', $this->sessionsUrl($shop), [], $foreign)->getStatusCode(),
        );
    }

    public function testBothAccountsOfTheSameOrganizationShareSessionsAndQuota(): void
    {
        $primary = $this->createUser('team-primary@example.test', ['ROLE_MERCHANT']);
        $secondary = $this->createUser('team-secondary@example.test', ['ROLE_MERCHANT']);
        $organization = (new MerchantOrganization())->setName('Org photo import')->setPrimaryAccount($primary);
        $this->entityManager->persist($organization);
        $this->membership($organization, $primary)->activate();
        $this->membership($organization, $secondary)->activate();
        $shop = $this->createShop($primary);
        $shop->setMerchantOrganization($organization);
        $this->entityManager->flush();

        $created = $this->createDraftSession($shop, $primary);
        $sessionId = $this->decodeJson($created)['id'];

        $asSecondary = $this->requestJson('GET', $this->sessionUrl($shop, $sessionId), user: $secondary);
        self::assertSame(200, $asSecondary->getStatusCode());
        self::assertSame($sessionId, $this->decodeJson($asSecondary)['id']);

        $list = $this->requestJson('GET', $this->sessionsUrl($shop), user: $secondary);
        self::assertSame(1, $this->decodeJson($list)['total']);

        $quota = $this->requestJson('GET', $this->quotaUrl($shop), user: $secondary);
        self::assertSame(10, $this->decodeJson($quota)['available']);
    }

    public function testDraftSessionCanBeUpdatedAndStaleVersionIsRejected(): void
    {
        $merchant = $this->createUser('session-update@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $session = $this->decodeJson($this->createDraftSession($shop, $merchant));

        $response = $this->requestJson(
            'PATCH',
            $this->sessionUrl($shop, $session['id']),
            ['version' => $session['version'], 'mode' => 'shelf', 'campaign_id' => 'ramadan-2027'],
            $merchant,
        );
        self::assertSame(200, $response->getStatusCode());
        $updated = $this->decodeJson($response);
        self::assertSame('shelf', $updated['mode']);
        self::assertSame('ramadan-2027', $updated['campaign_id']);
        self::assertSame($session['version'] + 1, $updated['version']);

        // Replaying the original (now stale) version is a conflict, not a silent overwrite.
        $stale = $this->requestJson(
            'PATCH',
            $this->sessionUrl($shop, $session['id']),
            ['version' => $session['version'], 'mode' => 'receipt'],
            $merchant,
        );
        self::assertSame(409, $stale->getStatusCode());
        self::assertSame('CATALOG_PHOTO_IMPORT_SESSION_VERSION_CONFLICT', $this->decodeJson($stale)['detail']);
    }

    public function testRegisteringAPhotoReservesExactlyOneCreditAndIsIdempotentOnRetry(): void
    {
        $merchant = $this->createUser('session-register@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $session = $this->decodeJson($this->createDraftSession($shop, $merchant));

        $first = $this->decodeJson($this->registerImage($shop, $session['id'], $merchant, 'identical-bytes'));
        self::assertSame(1, $first['active_image_count']);

        $quotaAfterOne = $this->decodeJson($this->requestJson('GET', $this->quotaUrl($shop), user: $merchant));
        self::assertSame(9, $quotaAfterOne['available']);
        self::assertSame(1, $quotaAfterOne['reserved']);

        // Retry with the exact same bytes (double tap / network redelivery): no second debit.
        $retry = $this->decodeJson($this->registerImage($shop, $session['id'], $merchant, 'identical-bytes'));
        self::assertSame(1, $retry['active_image_count']);

        $quotaAfterRetry = $this->decodeJson($this->requestJson('GET', $this->quotaUrl($shop), user: $merchant));
        self::assertSame(9, $quotaAfterRetry['available']);
    }

    public function testQuotaExhaustionBlocksTheEleventhPhotoWithoutGoingNegative(): void
    {
        $merchant = $this->createUser('session-exhaust@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $session = $this->decodeJson($this->createDraftSession($shop, $merchant));

        for ($i = 0; $i < 10; ++$i) {
            $response = $this->registerImage($shop, $session['id'], $merchant, 'photo-'.$i);
            self::assertSame(201, $response->getStatusCode());
        }

        $eleventh = $this->registerImage($shop, $session['id'], $merchant, 'photo-10');
        self::assertSame(422, $eleventh->getStatusCode());
        self::assertSame('CATALOG_PHOTO_QUOTA_EXHAUSTED', $this->decodeJson($eleventh)['detail']);

        $quota = $this->decodeJson($this->requestJson('GET', $this->quotaUrl($shop), user: $merchant));
        self::assertSame(0, $quota['available']);
        self::assertSame(10, $quota['reserved']);
    }

    public function testRemovingAnImageReleasesItsCredit(): void
    {
        $merchant = $this->createUser('session-remove-image@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $session = $this->decodeJson($this->createDraftSession($shop, $merchant));
        $withImage = $this->decodeJson($this->registerImage($shop, $session['id'], $merchant, 'to-be-removed'));
        $imageId = $withImage['images'][0]['id'];

        $deleteResponse = $this->requestJson(
            'DELETE',
            \sprintf('%s/images/%s', $this->sessionUrl($shop, $session['id']), $imageId),
            user: $merchant,
        );
        self::assertSame(204, $deleteResponse->getStatusCode());

        $quota = $this->decodeJson($this->requestJson('GET', $this->quotaUrl($shop), user: $merchant));
        self::assertSame(10, $quota['available']);
        self::assertSame(0, $quota['reserved']);

        $afterDelete = $this->decodeJson($this->requestJson('GET', $this->sessionUrl($shop, $session['id']), user: $merchant));
        self::assertSame(0, $afterDelete['active_image_count']);
    }

    public function testCancellingADraftSessionReleasesAllOutstandingReservations(): void
    {
        $merchant = $this->createUser('session-cancel@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $session = $this->decodeJson($this->createDraftSession($shop, $merchant));
        $this->registerImage($shop, $session['id'], $merchant, 'cancel-1');
        $this->registerImage($shop, $session['id'], $merchant, 'cancel-2');

        $cancelResponse = $this->requestJson(
            'POST',
            $this->sessionUrl($shop, $session['id']).'/cancel',
            [],
            $merchant,
        );
        self::assertSame(200, $cancelResponse->getStatusCode());
        $cancelled = $this->decodeJson($cancelResponse);
        self::assertSame('cancelled', $cancelled['status']);
        self::assertNotNull($cancelled['cancelled_at']);
        self::assertSame(0, $cancelled['active_image_count']);

        $quota = $this->decodeJson($this->requestJson('GET', $this->quotaUrl($shop), user: $merchant));
        self::assertSame(10, $quota['available']);
    }

    public function testActionsOnANonDraftSessionAreRejected(): void
    {
        $merchant = $this->createUser('session-not-draft@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $session = $this->decodeJson($this->createDraftSession($shop, $merchant));
        $this->requestJson('POST', $this->sessionUrl($shop, $session['id']).'/cancel', [], $merchant);

        $patch = $this->requestJson(
            'PATCH',
            $this->sessionUrl($shop, $session['id']),
            ['version' => $session['version'], 'mode' => 'shelf'],
            $merchant,
        );
        self::assertSame(409, $patch->getStatusCode());
        self::assertSame('CATALOG_PHOTO_IMPORT_SESSION_NOT_DRAFT', $this->decodeJson($patch)['detail']);

        $register = $this->registerImage($shop, $session['id'], $merchant, 'after-cancel');
        self::assertSame(409, $register->getStatusCode());

        $cancelAgain = $this->requestJson('POST', $this->sessionUrl($shop, $session['id']).'/cancel', [], $merchant);
        self::assertSame(409, $cancelAgain->getStatusCode());
    }

    public function testFourProvidersConsumingTheSameReservationDebitOnlyOneCreditEach(): void
    {
        $merchant = $this->createUser('ledger-consume@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $session = $this->decodeJson($this->createDraftSession($shop, $merchant));

        $images = [];
        for ($i = 0; $i < 10; ++$i) {
            $registered = $this->decodeJson($this->registerImage($shop, $session['id'], $merchant, 'consume-'.$i));
            $images[] = $registered['images'][\count($registered['images']) - 1]['id'];
        }

        $ledger = self::getContainer()->get(CatalogPhotoQuotaLedger::class);
        $imageRepository = $this->entityManager->getRepository(CatalogPhotoImportImage::class);

        foreach ($images as $imageId) {
            $image = $imageRepository->find($imageId);
            self::assertInstanceOf(CatalogPhotoImportImage::class, $image);
            // Four providers "finish" against the same reservation — simulates
            // CATALOG-AI-004 calling consumeForImage once per provider branch.
            $ledger->consumeForImage($image);
            $ledger->consumeForImage($image);
            $ledger->consumeForImage($image);
            $ledger->consumeForImage($image);
        }

        $shopReloaded = $this->entityManager->getRepository(Shop::class)->find($shop->getId());
        self::assertInstanceOf(Shop::class, $shopReloaded);
        $balance = $ledger->getBalance($shopReloaded);
        self::assertSame(10, $balance->consumed);
        self::assertSame(0, $balance->available);
        self::assertSame(0, $balance->reserved);

        // A consumed credit is never recredited, even if release is attempted afterwards.
        $firstImage = $imageRepository->find($images[0]);
        self::assertInstanceOf(CatalogPhotoImportImage::class, $firstImage);
        $ledger->releaseForImage($firstImage);
        self::assertSame(0, $ledger->getBalance($shopReloaded)->available);
    }

    // Fixtures

    private function createDraftSession(Shop $shop, User $user): Response
    {
        return $this->requestJson('POST', $this->sessionsUrl($shop), [], $user);
    }

    private function registerImage(Shop $shop, string $sessionId, User $user, string $bytes): Response
    {
        $photoPath = tempnam(sys_get_temp_dir(), 'catalog-photo-import-session-');
        self::assertIsString($photoPath);
        file_put_contents($photoPath, $bytes);

        $request = Request::create(
            \sprintf('%s/images', $this->sessionUrl($shop, $sessionId)),
            'POST',
            files: ['photo' => new UploadedFile($photoPath, 'photo.jpg', 'image/jpeg', null, true)],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_TEST_USER' => $user->getEmail(),
            ],
        );

        return self::$kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
    }

    private function membership(MerchantOrganization $organization, User $user): MerchantMembership
    {
        $membership = (new MerchantMembership())->setOrganization($organization)->setUser($user);
        $this->entityManager->persist($membership);

        return $membership;
    }

    private function quotaUrl(Shop $shop): string
    {
        return \sprintf('/api/merchant/stores/%s/catalog/photo-import/quota', $shop->getId());
    }

    private function sessionsUrl(Shop $shop): string
    {
        return \sprintf('/api/merchant/stores/%s/catalog/photo-import/sessions', $shop->getId());
    }

    private function sessionUrl(Shop $shop, string $sessionId): string
    {
        return \sprintf('%s/%s', $this->sessionsUrl($shop), $sessionId);
    }
}
