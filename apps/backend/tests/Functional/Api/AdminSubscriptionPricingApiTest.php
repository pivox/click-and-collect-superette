<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\AdminAuditLog;
use App\Entity\BillingDocument;
use App\Entity\Subscription;
use App\Enum\SubscriptionPricingPhase;

final class AdminSubscriptionPricingApiTest extends FunctionalApiTestCase
{
    public function testPreviewCreationAndDuplicateProtection(): void
    {
        $admin = $this->createUser('pricing-admin@test.com', ['ROLE_ADMIN']);
        $merchant = $this->createUser('pricing-merchant@test.com', ['ROLE_MERCHANT']);
        $input = $this->input() + ['merchant_id' => (string) $merchant->getId()];
        $preview = $this->requestJson('POST', '/api/admin/subscriptions/pricing-preview', $input, $admin);
        self::assertSame(200, $preview->getStatusCode());
        self::assertSame('375.000', $this->decodeJson($preview)['period_price_tnd']);
        self::assertSame(0, $this->entityManager->getRepository(Subscription::class)->count([]));
        self::assertSame(0, $this->entityManager->getRepository(AdminAuditLog::class)->count([]));
        $created = $this->requestJson('POST', '/api/admin/subscriptions', $input, $admin);
        self::assertSame(201, $created->getStatusCode());
        $payload = $this->decodeJson($created);
        self::assertSame('annual', $payload['billing_interval']);
        self::assertSame('375.000', $payload['period_price_tnd']);
        self::assertCount(12, $payload['period_breakdown']);
        self::assertSame(409, $this->requestJson('POST', '/api/admin/subscriptions', $input, $admin)->getStatusCode());
    }

    public function testSchedulingAndPreviewPreserveCurrentPriceAndIssuedDocument(): void
    {
        $admin = $this->createUser('pricing-admin@test.com', ['ROLE_ADMIN']);
        $merchant = $this->createUser('pricing-merchant@test.com', ['ROLE_MERCHANT']);
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('first day of last month midnight'));
        $document = BillingDocument::issueMonthlyStatement($subscription, 'TEST-PRICING', new \DateTimeImmutable('-1 month'), new \DateTimeImmutable(), new \DateTimeImmutable(), new \DateTimeImmutable('+7 days'), SubscriptionPricingPhase::Standard, '50.000');
        $this->entityManager->persist($subscription);
        $this->entityManager->persist($document);
        $this->entityManager->flush();
        $url = '/api/admin/subscriptions/'.$subscription->getId();
        $preview = $this->requestJson('POST', $url.'/pricing-preview', $this->input(), $admin);
        self::assertSame(200, $preview->getStatusCode());
        self::assertSame('375.000', $this->decodeJson($preview)['period_price_tnd']);
        $before = $this->decodeJson($this->requestJson('GET', $url, user: $admin));
        self::assertNull($before['scheduled_pricing']);
        $response = $this->requestJson('PUT', $url.'/pricing', $this->input(), $admin);
        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame($before['period_price_tnd'], $payload['period_price_tnd']);
        self::assertSame('annual', $payload['scheduled_pricing']['billing_interval']);
        self::assertSame($before['current_period_ends_at'], $payload['scheduled_pricing']['effective_at']);
        $this->entityManager->clear();
        self::assertSame('50.000', $this->entityManager->find(BillingDocument::class, $document->getId())->getAmountDueTnd());
        self::assertSame(1, $this->entityManager->getRepository(AdminAuditLog::class)->count([]));
    }

    public function testWriteRoutesRejectMerchantAndInvalidPricing(): void
    {
        $admin = $this->createUser('pricing-admin@test.com', ['ROLE_ADMIN']);
        $merchant = $this->createUser('pricing-merchant@test.com', ['ROLE_MERCHANT']);
        $input = $this->input() + ['merchant_id' => (string) $merchant->getId()];
        self::assertSame(403, $this->requestJson('POST', '/api/admin/subscriptions', $input, $merchant)->getStatusCode());
        self::assertSame(401, $this->requestJson('POST', '/api/admin/subscriptions', $input)->getStatusCode());
        $input['discounts'][1]['from_month'] = 3;
        self::assertSame(422, $this->requestJson('POST', '/api/admin/subscriptions', $input, $admin)->getStatusCode());
        $input['discounts'] = [];
        self::assertSame(422, $this->requestJson('POST', '/api/admin/subscriptions', $input, $admin)->getStatusCode());
        $input['pricing_mode'] = 'free';
        $input['free_months'] = 0;
        self::assertSame(422, $this->requestJson('POST', '/api/admin/subscriptions', $input, $admin)->getStatusCode());
    }

    public function testAdminIssuesAnnualDocumentIdempotentlyAndFreePeriodIsRejected(): void
    {
        $admin = $this->createUser('document-admin@test.com', ['ROLE_ADMIN']);
        $merchant = $this->createUser('document-merchant@test.com', ['ROLE_MERCHANT']);
        $input = $this->input() + ['merchant_id' => (string) $merchant->getId()];
        $created = $this->decodeJson($this->requestJson('POST', '/api/admin/subscriptions', $input, $admin));
        $url = '/api/admin/subscriptions/'.$created['id'].'/billing-document';
        self::assertSame(403, $this->requestJson('POST', $url, [], $merchant)->getStatusCode());
        $issued = $this->requestJson('POST', $url, [], $admin);
        self::assertSame(200, $issued->getStatusCode());
        $document = $this->decodeJson($issued);
        self::assertSame('375.000', $document['amount_tnd']);
        self::assertSame('annual_statement', $document['document_type']);
        self::assertSame('375.000', $document['pricing_snapshot']['period_price_tnd']);
        self::assertCount(12, $document['pricing_snapshot']['period_breakdown']);
        self::assertSame($created['current_period_ends_at'], $document['billing_period_end']);
        $again = $this->decodeJson($this->requestJson('POST', $url, [], $admin));
        self::assertSame($document['id'], $again['id']);
        self::assertSame(1, $this->entityManager->getRepository(BillingDocument::class)->count([]));
        self::assertSame(2, $this->entityManager->getRepository(AdminAuditLog::class)->count([]));
        $other = $this->createUser('free-document@test.com', ['ROLE_MERCHANT']);
        $input['merchant_id'] = (string) $other->getId();
        $input['pricing_mode'] = 'free';
        $input['discounts'] = [];
        $free = $this->decodeJson($this->requestJson('POST', '/api/admin/subscriptions', $input, $admin));
        self::assertSame(422, $this->requestJson('POST', '/api/admin/subscriptions/'.$free['id'].'/billing-document', [], $admin)->getStatusCode());
    }

    public function testOrganizationUsesPrimaryContactAndRejectsSecondAccountDuplicate(): void
    {
        $admin = $this->createUser('org-admin@test.com', ['ROLE_ADMIN']);
        $primary = $this->createUser('org-primary@test.com', ['ROLE_MERCHANT']);
        $secondary = $this->createUser('org-secondary@test.com', ['ROLE_MERCHANT']);
        $organization = (new \App\Entity\MerchantOrganization())->setName('Supérette équipe')->setPrimaryAccount($primary);
        $this->entityManager->persist($organization);
        foreach ([$primary, $secondary] as $user) {
            $this->entityManager->persist((new \App\Entity\MerchantMembership())->setUser($user)->setOrganization($organization)->activate());
        }
        $this->entityManager->flush();
        $input = $this->input() + ['merchant_id' => (string) $secondary->getId()];
        $response = $this->requestJson('POST', '/api/admin/subscriptions', $input, $admin);
        self::assertSame(201, $response->getStatusCode());
        $payload = $this->decodeJson($response);
        self::assertSame((string) $primary->getId(), $payload['merchant_id']);
        $subscription = $this->entityManager->find(Subscription::class, $payload['id']);
        self::assertSame((string) $organization->getId(), (string) $subscription->getMerchantOrganization()->getId());
        $input['merchant_id'] = (string) $primary->getId();
        self::assertSame(409, $this->requestJson('POST', '/api/admin/subscriptions', $input, $admin)->getStatusCode());
        $merchantRead = $this->decodeJson($this->requestJson('GET', '/api/merchant/subscription', user: $secondary));
        self::assertSame($payload['id'], $merchantRead['id']);
        self::assertSame('375.000', $merchantRead['period_price_tnd']);
        self::assertArrayNotHasKey('reason', $merchantRead['pricing_terms']);
    }

    public function testScheduledReplacementIsAuditedAndAllExistingWriteRoutesAreAdminOnly(): void
    {
        $admin = $this->createUser('replace-admin@test.com', ['ROLE_ADMIN']);
        $merchant = $this->createUser('replace-merchant@test.com', ['ROLE_MERCHANT']);
        $created = $this->decodeJson($this->requestJson('POST', '/api/admin/subscriptions', $this->input() + ['merchant_id' => (string) $merchant->getId()], $admin));
        $url = '/api/admin/subscriptions/'.$created['id'];
        foreach ([['PUT', '/pricing'], ['POST', '/pricing-preview']] as [$method, $suffix]) {
            self::assertSame(403, $this->requestJson($method, $url.$suffix, $this->input(), $merchant)->getStatusCode());
            self::assertSame(401, $this->requestJson($method, $url.$suffix, $this->input())->getStatusCode());
        }
        $input = $this->input();
        $input['base_price_tnd'] = '500';
        self::assertSame(200, $this->requestJson('PUT', $url.'/pricing', $input, $admin)->getStatusCode());
        $input['base_price_tnd'] = '400';
        $changed = $this->decodeJson($this->requestJson('PUT', $url.'/pricing', $input, $admin));
        self::assertSame('375.000', $changed['period_price_tnd']);
        self::assertSame('400.000', $changed['scheduled_pricing']['base_price_tnd']);
        $subscription = $this->entityManager->find(Subscription::class, $created['id']);
        self::assertCount(2, $subscription->getPricingVersions());
        self::assertSame(3, $this->entityManager->getRepository(AdminAuditLog::class)->count([]));
        $subscription->setLifecycle(\App\Enum\SubscriptionLifecycle::Cancelled);
        $this->entityManager->flush();
        self::assertSame(409, $this->requestJson('PUT', $url.'/pricing', $input, $admin)->getStatusCode());
        self::assertSame(409, $this->requestJson('POST', $url.'/billing-document', [], $admin)->getStatusCode());
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        return ['billing_interval' => 'annual', 'pricing_mode' => 'discounted', 'base_price_tnd' => '600.000', 'free_months' => null, 'reason' => 'Accord commercial', 'discounts' => [
            ['from_month' => 1, 'to_month' => 3, 'type' => 'percentage', 'value' => '100'],
            ['from_month' => 4, 'to_month' => 6, 'type' => 'percentage', 'value' => '50'],
        ]];
    }
}
