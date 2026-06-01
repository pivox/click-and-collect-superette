<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\PickupSlot;
use App\Entity\PickupSlotRule;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Enum\ProductReferenceStatus;
use Symfony\Component\Uid\Uuid;

final class MerchantOnboardingApiTest extends FunctionalApiTestCase
{
    public function testMerchantWithNoShopSeesAllStepsFalse(): void
    {
        $merchant = $this->createUser('onboarding-no-shop@example.test', ['ROLE_MERCHANT']);

        $response = $this->requestJson('GET', '/api/merchant/onboarding', null, $merchant);

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertFalse($payload['completed']);
        self::assertNull($payload['completed_at']);
        self::assertCount(5, $payload['steps']);

        $byKey = $this->indexStepsByKey($payload['steps']);
        self::assertFalse($byKey['store_profile']['completed']);
        self::assertFalse($byKey['theme']['completed']);
        self::assertFalse($byKey['catalog']['completed']);
        self::assertFalse($byKey['pickup_slots']['completed']);
        self::assertFalse($byKey['qr_code']['completed']);
    }

    public function testStoreProfileAndQrCodeTrueWhenActiveShopExists(): void
    {
        $merchant = $this->createUser('onboarding-shop-only@example.test', ['ROLE_MERCHANT']);
        $this->createShop($merchant, active: true);

        $response = $this->requestJson('GET', '/api/merchant/onboarding', null, $merchant);

        self::assertSame(200, $response->getStatusCode());

        $byKey = $this->indexStepsByKey($this->decodeJson($response)['steps']);
        self::assertTrue($byKey['store_profile']['completed']);
        self::assertTrue($byKey['qr_code']['completed']);
        self::assertFalse($byKey['theme']['completed']);
        self::assertFalse($byKey['catalog']['completed']);
        self::assertFalse($byKey['pickup_slots']['completed']);
    }

    public function testInactiveShopDoesNotSatisfyStoreProfile(): void
    {
        $merchant = $this->createUser('onboarding-inactive-shop@example.test', ['ROLE_MERCHANT']);
        $this->createShop($merchant, active: false);

        $response = $this->requestJson('GET', '/api/merchant/onboarding', null, $merchant);

        self::assertSame(200, $response->getStatusCode());

        $byKey = $this->indexStepsByKey($this->decodeJson($response)['steps']);
        self::assertFalse($byKey['store_profile']['completed']);
        self::assertFalse($byKey['qr_code']['completed']);
    }

    public function testThemeTrueWhenShopThemeConfigured(): void
    {
        $merchant = $this->createUser('onboarding-theme@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $this->createShopTheme($shop);

        $response = $this->requestJson('GET', '/api/merchant/onboarding', null, $merchant);

        self::assertSame(200, $response->getStatusCode());

        $byKey = $this->indexStepsByKey($this->decodeJson($response)['steps']);
        self::assertTrue($byKey['theme']['completed']);
    }

    public function testCatalogTrueWhenVisibleProductExists(): void
    {
        $merchant = $this->createUser('onboarding-catalog@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $this->createVisibleMerchantProduct($shop);

        $response = $this->requestJson('GET', '/api/merchant/onboarding', null, $merchant);

        self::assertSame(200, $response->getStatusCode());

        $byKey = $this->indexStepsByKey($this->decodeJson($response)['steps']);
        self::assertTrue($byKey['catalog']['completed']);
    }

    public function testCatalogFalseWhenProductIsNotVisible(): void
    {
        $merchant = $this->createUser('onboarding-catalog-invisible@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $this->createVisibleMerchantProduct($shop, isVisible: false);

        $response = $this->requestJson('GET', '/api/merchant/onboarding', null, $merchant);

        self::assertSame(200, $response->getStatusCode());

        $byKey = $this->indexStepsByKey($this->decodeJson($response)['steps']);
        self::assertFalse($byKey['catalog']['completed']);
    }

    public function testPickupSlotsTrueWhenActiveRuleExists(): void
    {
        $merchant = $this->createUser('onboarding-slots-rule@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $this->createActivePickupSlotRule($shop);

        $response = $this->requestJson('GET', '/api/merchant/onboarding', null, $merchant);

        self::assertSame(200, $response->getStatusCode());

        $byKey = $this->indexStepsByKey($this->decodeJson($response)['steps']);
        self::assertTrue($byKey['pickup_slots']['completed']);
    }

    public function testPickupSlotsTrueWhenOnlyLongGenerationRangeRuleExists(): void
    {
        $merchant = $this->createUser('onboarding-slots-legacy-long-rule@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $this->createActivePickupSlotRule($shop, '09:00', '12:00');

        $response = $this->requestJson('GET', '/api/merchant/onboarding', null, $merchant);

        self::assertSame(200, $response->getStatusCode());

        $byKey = $this->indexStepsByKey($this->decodeJson($response)['steps']);
        self::assertTrue($byKey['pickup_slots']['completed']);
    }

    public function testPickupSlotsTrueWhenFuturePickupSlotExists(): void
    {
        $merchant = $this->createUser('onboarding-slots-future@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $this->createFuturePickupSlot($shop);

        $response = $this->requestJson('GET', '/api/merchant/onboarding', null, $merchant);

        self::assertSame(200, $response->getStatusCode());

        $byKey = $this->indexStepsByKey($this->decodeJson($response)['steps']);
        self::assertTrue($byKey['pickup_slots']['completed']);
    }

    public function testPickupSlotsFalseWhenOnlyLegacyLongFuturePickupSlotExists(): void
    {
        $merchant = $this->createUser('onboarding-slots-legacy-long-slot@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $this->createFuturePickupSlot($shop, durationHours: 3);

        $response = $this->requestJson('GET', '/api/merchant/onboarding', null, $merchant);

        self::assertSame(200, $response->getStatusCode());

        $byKey = $this->indexStepsByKey($this->decodeJson($response)['steps']);
        self::assertFalse($byKey['pickup_slots']['completed']);
    }

    public function testPickupSlotsFalseWhenOnlyPastPickupSlotExists(): void
    {
        $merchant = $this->createUser('onboarding-slots-past@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $this->createPastPickupSlot($shop);

        $response = $this->requestJson('GET', '/api/merchant/onboarding', null, $merchant);

        self::assertSame(200, $response->getStatusCode());

        $byKey = $this->indexStepsByKey($this->decodeJson($response)['steps']);
        self::assertFalse($byKey['pickup_slots']['completed']);
    }

    public function testPatchCompleteMarksOnboardingDone(): void
    {
        $merchant = $this->createUser('onboarding-complete@example.test', ['ROLE_MERCHANT']);

        $before = new \DateTimeImmutable();
        $response = $this->requestJson('PATCH', '/api/merchant/onboarding/complete', [], $merchant);
        $after = new \DateTimeImmutable();

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertTrue($payload['completed']);
        self::assertNotNull($payload['completed_at']);

        // SQLite truncates to second precision — compare at truncated granularity.
        $beforeTrunc = new \DateTimeImmutable('@'.(string) $before->getTimestamp());
        $completedAt = new \DateTimeImmutable($payload['completed_at']);
        self::assertGreaterThanOrEqual($beforeTrunc, $completedAt);
        self::assertLessThanOrEqual($after, $completedAt);
    }

    public function testPatchCompleteIsIdempotent(): void
    {
        $merchant = $this->createUser('onboarding-idempotent@example.test', ['ROLE_MERCHANT']);

        $first = $this->requestJson('PATCH', '/api/merchant/onboarding/complete', [], $merchant);
        self::assertSame(200, $first->getStatusCode());
        $firstPayload = $this->decodeJson($first);

        $second = $this->requestJson('PATCH', '/api/merchant/onboarding/complete', [], $merchant);
        self::assertSame(200, $second->getStatusCode());
        $secondPayload = $this->decodeJson($second);

        self::assertSame($firstPayload['completed_at'], $secondPayload['completed_at']);
    }

    public function testGetRequiresAuthentication(): void
    {
        $response = $this->requestJson('GET', '/api/merchant/onboarding');

        self::assertSame(401, $response->getStatusCode());
    }

    public function testGetForbiddenForCustomer(): void
    {
        $customer = $this->createUser('onboarding-customer-forbidden@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson('GET', '/api/merchant/onboarding', null, $customer);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testPatchCompleteRequiresAuthentication(): void
    {
        $response = $this->requestJson('PATCH', '/api/merchant/onboarding/complete', []);

        self::assertSame(401, $response->getStatusCode());
    }

    public function testPatchCompleteForbiddenForCustomer(): void
    {
        $customer = $this->createUser('onboarding-complete-customer@example.test', ['ROLE_CUSTOMER']);

        $response = $this->requestJson('PATCH', '/api/merchant/onboarding/complete', [], $customer);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testMerchantOnlySeeTheirOwnShops(): void
    {
        $merchantA = $this->createUser('onboarding-isolation-a@example.test', ['ROLE_MERCHANT']);
        $merchantB = $this->createUser('onboarding-isolation-b@example.test', ['ROLE_MERCHANT']);
        $this->createShop($merchantA, active: true);

        $responseB = $this->requestJson('GET', '/api/merchant/onboarding', null, $merchantB);
        self::assertSame(200, $responseB->getStatusCode());

        $byKey = $this->indexStepsByKey($this->decodeJson($responseB)['steps']);
        self::assertFalse($byKey['store_profile']['completed'], 'Merchant B should not see merchant A\'s shop.');
    }

    // --- fixture helpers ---

    private function createVisibleMerchantProduct(Shop $shop, bool $isVisible = true): MerchantProduct
    {
        $id = Uuid::v4()->toRfc4122();
        $brand = (new Brand())->setCanonicalName('Brand '.$id)->setSlug('brand-'.$id);
        $category = (new Category())->setNameFr('Cat '.$id)->setSlug('cat-'.$id);
        $ref = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr('Produit test')
            ->setStatus(ProductReferenceStatus::Approved);
        $product = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($ref)
            ->setPriceTnd('2.000')
            ->setVisible($isVisible);

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($ref);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }

    private function createActivePickupSlotRule(Shop $shop, string $startTime = '09:00', string $endTime = '10:00'): PickupSlotRule
    {
        $rule = (new PickupSlotRule())
            ->setShop($shop)
            ->setWeekday(1)
            ->setStartTime(new \DateTimeImmutable('1970-01-01 '.$startTime.':00'))
            ->setEndTime(new \DateTimeImmutable('1970-01-01 '.$endTime.':00'))
            ->setCapacity(5)
            ->setActive(true);

        $this->entityManager->persist($rule);
        $this->entityManager->flush();

        return $rule;
    }

    private function createFuturePickupSlot(Shop $shop, int $durationHours = 1): PickupSlot
    {
        $now = new \DateTimeImmutable();
        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('+2 hours'))
            ->setEndsAt($now->modify(\sprintf('+%d hours', 2 + $durationHours)))
            ->setCapacity(5)
            ->setActive(true);

        $this->entityManager->persist($slot);
        $this->entityManager->flush();

        return $slot;
    }

    private function createPastPickupSlot(Shop $shop): PickupSlot
    {
        $now = new \DateTimeImmutable();
        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($now->modify('-3 hours'))
            ->setEndsAt($now->modify('-2 hours'))
            ->setCapacity(5);

        $this->entityManager->persist($slot);
        $this->entityManager->flush();

        return $slot;
    }

    /**
     * @param list<array<string, mixed>> $steps
     *
     * @return array<string, array<string, mixed>>
     */
    private function indexStepsByKey(array $steps): array
    {
        $indexed = [];
        foreach ($steps as $step) {
            $indexed[$step['key']] = $step;
        }

        return $indexed;
    }
}
