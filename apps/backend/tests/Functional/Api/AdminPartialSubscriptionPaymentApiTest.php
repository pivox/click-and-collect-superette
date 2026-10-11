<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\AdminAuditLog;
use App\Entity\BillingDocument;
use App\Entity\Subscription;
use App\Entity\SubscriptionPayment;
use App\Entity\User;
use App\Enum\BillingDocumentStatus;
use App\Enum\SubscriptionLifecycle;
use App\Enum\SubscriptionPricingPhase;
use Symfony\Component\Uid\Uuid;

final class AdminPartialSubscriptionPaymentApiTest extends FunctionalApiTestCase
{
    public function testSuccessivePaymentsKeepSuspensionUntilLastMillime(): void
    {
        [$admin, $subscription, $document] = $this->fixture('50.000');
        $document->markOverdue(new \DateTimeImmutable());
        $this->entityManager->flush();

        $first = $this->requestJson('POST', '/api/admin/subscription-payments', $this->payload($document, '20.000'), $admin);
        self::assertSame(201, $first->getStatusCode());
        self::assertSame($document->getDocumentNumber(), $this->decodeJson($first)['document_number']);
        $document = $this->reloadDocument($document);
        $subscription = $this->reloadSubscription($subscription);
        self::assertSame('20.000', $document->getAmountPaidTnd());
        self::assertSame('30.000', $document->getAmountDueTnd());
        self::assertSame(BillingDocumentStatus::Overdue, $document->getStatus());
        self::assertNull($document->getPaidAt());
        self::assertSame(SubscriptionLifecycle::Suspended, $subscription->getLifecycle());
        self::assertSame(0, $this->entityManager->getRepository(AdminAuditLog::class)->count(['action' => 'subscription.reactivated']));

        $excess = $this->requestJson('POST', '/api/admin/subscription-payments', $this->payload($document, '30.001'), $admin);
        self::assertSame(422, $excess->getStatusCode());
        self::assertStringContainsString('SUBSCRIPTION_PAYMENT_AMOUNT_MISMATCH', (string) $excess->getContent());
        $document = $this->reloadDocument($document);
        self::assertSame('30.000', $document->getAmountDueTnd());

        foreach (['29.999', '0.001'] as $amount) {
            $response = $this->requestJson('POST', '/api/admin/subscription-payments', $this->payload($document, $amount), $admin);
            self::assertSame(201, $response->getStatusCode());
        }
        $document = $this->reloadDocument($document);
        $subscription = $this->reloadSubscription($subscription);
        self::assertSame('50.000', $document->getAmountPaidTnd());
        self::assertSame('0.000', $document->getAmountDueTnd());
        self::assertSame(BillingDocumentStatus::Paid, $document->getStatus());
        self::assertSame(SubscriptionLifecycle::Active, $subscription->getLifecycle());
        self::assertSame(3, $this->entityManager->getRepository(SubscriptionPayment::class)->count([]));
        self::assertSame(1, $this->entityManager->getRepository(AdminAuditLog::class)->count(['action' => 'subscription.reactivated']));
    }

    public function testFullPaymentDoesNotReactivateWhileAnotherDocumentIsDue(): void
    {
        [$admin, $subscription, $document] = $this->fixture();
        $other = $this->document($subscription, 'SECOND-DUE', '10.000');
        $future = $this->document($subscription, 'FUTURE', '50.000', new \DateTimeImmutable('+1 month'));
        $this->entityManager->flush();

        foreach ([$document, $other] as $index => $target) {
            $response = $this->requestJson('POST', '/api/admin/subscription-payments', $this->payload($target, '10.000'), $admin);
            self::assertSame(201, $response->getStatusCode());
            $subscription = $this->reloadSubscription($subscription);
            self::assertSame(0 === $index ? SubscriptionLifecycle::Suspended : SubscriptionLifecycle::Active, $subscription->getLifecycle());
        }
        self::assertSame('50.000', $future->getAmountDueTnd());
    }

    public function testIdempotencyReplaysPaidDocumentAndRejectsChangedPayloadOrAdmin(): void
    {
        [$admin, , $document] = $this->fixture();
        $otherAdmin = $this->createUser('other-admin@example.test', ['ROLE_ADMIN']);
        $payload = $this->payload($document, '10.000') + ['idempotency_key' => Uuid::v4()->toRfc4122()];
        $first = $this->requestJson('POST', '/api/admin/subscription-payments', $payload, $admin);
        self::assertSame(201, $first->getStatusCode());
        $retry = $this->requestJson('POST', '/api/admin/subscription-payments', $payload, $admin);
        self::assertSame(201, $retry->getStatusCode());
        self::assertSame($this->decodeJson($first)['id'], $this->decodeJson($retry)['id']);
        self::assertSame(1, $this->entityManager->getRepository(SubscriptionPayment::class)->count([]));
        self::assertSame(1, $this->entityManager->getRepository(AdminAuditLog::class)->count(['action' => 'subscription_payment.confirmed']));

        foreach (['billing_document_id' => Uuid::v4()->toRfc4122(), 'amount_tnd' => '9.000', 'reference' => 'OTHER', 'method' => 'bank_transfer', 'paid_at' => '2026-01-02T10:00:00+01:00'] as $field => $value) {
            $changed = $this->requestJson('POST', '/api/admin/subscription-payments', array_replace($payload, [$field => $value]), $admin);
            self::assertSame(409, $changed->getStatusCode());
            self::assertStringContainsString('SUBSCRIPTION_PAYMENT_IDEMPOTENCY_CONFLICT', (string) $changed->getContent());
        }
        $response = $this->requestJson('POST', '/api/admin/subscription-payments', $payload, $otherAdmin);
        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('SUBSCRIPTION_PAYMENT_IDEMPOTENCY_CONFLICT', (string) $response->getContent());
    }

    public function testDraftCancelledSubscriptionAndInvalidAmountsAreRejected(): void
    {
        [$admin, $subscription, $document] = $this->fixture();
        foreach (['0.000', '10.001', '0.0001', '-1.000'] as $amount) {
            $response = $this->requestJson('POST', '/api/admin/subscription-payments', $this->payload($document, $amount), $admin);
            self::assertSame(422, $response->getStatusCode());
        }
        $response = $this->requestJson('POST', '/api/admin/subscription-payments', array_replace($this->payload($document, '1.000'), ['paid_at' => (new \DateTimeImmutable('+1 day'))->format(\DateTimeInterface::ATOM)]), $admin);
        self::assertSame(422, $response->getStatusCode());
        $response = $this->requestJson('POST', '/api/admin/subscription-payments', $this->payload($document, '1.000') + ['idempotency_key' => 'invalid'], $admin);
        self::assertSame(422, $response->getStatusCode());

        $document = $this->reloadDocument($document);
        $this->setPrivateProperty($document, 'status', BillingDocumentStatus::Draft);
        $this->entityManager->flush();
        $response = $this->requestJson('POST', '/api/admin/subscription-payments', $this->payload($document, '10.000'), $admin);
        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('SUBSCRIPTION_PAYMENT_DOCUMENT_NOT_ISSUED', (string) $response->getContent());
        $document = $this->reloadDocument($document);
        $subscription = $this->reloadSubscription($subscription);
        $this->setPrivateProperty($document, 'status', BillingDocumentStatus::Issued);
        $subscription->setLifecycle(SubscriptionLifecycle::Cancelled);
        $this->entityManager->flush();
        $response = $this->requestJson('POST', '/api/admin/subscription-payments', $this->payload($document, '10.000'), $admin);
        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('SUBSCRIPTION_PAYMENT_SUBSCRIPTION_CANCELLED', (string) $response->getContent());
        self::assertSame(0, $this->entityManager->getRepository(SubscriptionPayment::class)->count([]));
    }

    public function testAdminFiltersApplyBeforePaginationAndReturnMatchingTotals(): void
    {
        [$admin, $subscription, $document] = $this->fixture('50.000');
        $second = $this->document($subscription, 'SECOND-DUE', '20.000');
        $cancelled = $this->document($subscription, 'CANCELLED', '20.000');
        $cancelled->cancel('Test', new \DateTimeImmutable());
        $merchant = $this->createUser('unrelated@example.test', ['ROLE_MERCHANT']);
        $unrelatedSubscription = Subscription::startTrial($merchant, new \DateTimeImmutable('-1 month'));
        $this->entityManager->persist($unrelatedSubscription);
        $unrelated = $this->document($unrelatedSubscription, 'UNRELATED', '10.000');
        $this->entityManager->flush();
        foreach ([[$document, '10.000'], [$document, '5.000'], [$second, '20.000'], [$unrelated, '10.000']] as [$target, $amount]) {
            $response = $this->requestJson('POST', '/api/admin/subscription-payments', $this->payload($target, $amount), $admin);
            self::assertSame(201, $response->getStatusCode());
        }
        $subscriptionId = $subscription->getId()->toRfc4122();
        $documentId = $document->getId()->toRfc4122();
        $cases = [
            ['billing-documents', 'subscription_id='.$subscriptionId.'&outstanding=true', 1, $documentId],
            ['billing-documents', 'subscription_id='.$subscriptionId.'&outstanding=false', 2, null],
            ['billing-documents', 'q=SECOND-DUE', 1, $second->getId()->toRfc4122()],
            ['billing-documents', 'q=partial-merchant', 3, null],
            ['subscription-payments', 'subscription_id='.$subscriptionId, 3, null],
            ['subscription-payments', 'billing_document_id='.$documentId, 2, null],
            ['subscription-payments', 'q=SECOND-DUE', 1, null],
            ['subscription-payments', 'q=partial-merchant', 3, null],
            ['subscription-payments', 'q=Receipt', 4, null],
        ];
        foreach ($cases as [$resource, $filter, $total, $id]) {
            $response = $this->requestJson('GET', '/api/admin/'.$resource.'?'.$filter.'&limit=1', user: $admin);
            self::assertSame(200, $response->getStatusCode());
            $result = $this->decodeJson($response);
            self::assertSame($total, $result['total'], $resource.'?'.$filter);
            self::assertCount(1, $result['items']);
            if (null !== $id) {
                self::assertSame($id, $result['items'][0]['id']);
            }
            $emptyPage = $this->decodeJson($this->requestJson('GET', '/api/admin/'.$resource.'?'.$filter.'&limit=1&page='.($total + 1), user: $admin));
            self::assertSame($total, $emptyPage['total']);
            self::assertSame([], $emptyPage['items']);
        }
    }

    public function testInvalidFiltersReturn400AndNonAdminsCannotReadAdminLists(): void
    {
        [$admin, $subscription] = $this->fixture();
        foreach (['billing-documents', 'subscription-payments'] as $resource) {
            $filters = ['subscription_id=invalid', 'subscription_id[]=invalid', 'q[]=bad'];
            $filters[] = 'billing-documents' === $resource ? 'outstanding=invalid' : 'billing_document_id=invalid';
            foreach ($filters as $filter) {
                $response = $this->requestJson('GET', '/api/admin/'.$resource.'?'.$filter, user: $admin);
                self::assertSame(400, $response->getStatusCode(), $resource.'?'.$filter);
            }
            $response = $this->requestJson('GET', '/api/admin/'.$resource, user: $subscription->getMerchant());
            self::assertSame(403, $response->getStatusCode());
            $response = $this->requestJson('GET', '/api/admin/'.$resource);
            self::assertSame(401, $response->getStatusCode());
        }
    }

    private function reloadDocument(BillingDocument $document): BillingDocument
    {
        $reloaded = $this->entityManager->find(BillingDocument::class, $document->getId());
        self::assertNotNull($reloaded);
        $this->entityManager->refresh($reloaded);

        return $reloaded;
    }

    private function reloadSubscription(Subscription $subscription): Subscription
    {
        $reloaded = $this->entityManager->find(Subscription::class, $subscription->getId());
        self::assertNotNull($reloaded);
        $this->entityManager->refresh($reloaded);

        return $reloaded;
    }

    /** @return array{User, Subscription, BillingDocument} */
    private function fixture(string $amount = '10.000'): array
    {
        $admin = $this->createUser('partial-admin@example.test', ['ROLE_ADMIN']);
        $merchant = $this->createUser('partial-merchant@example.test', ['ROLE_MERCHANT']);
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('-2 months'));
        $subscription->setLifecycle(SubscriptionLifecycle::Suspended);
        $this->entityManager->persist($subscription);
        $document = $this->document($subscription, 'PARTIAL-001', $amount);
        $this->entityManager->flush();

        return [$admin, $subscription, $document];
    }

    private function document(Subscription $subscription, string $number, string $amount, ?\DateTimeImmutable $dueAt = null): BillingDocument
    {
        $document = BillingDocument::issueMonthlyStatement(
            $subscription,
            $number,
            new \DateTimeImmutable('-2 months'),
            new \DateTimeImmutable('-1 month'),
            new \DateTimeImmutable('-2 months'),
            $dueAt ?? new \DateTimeImmutable('-1 month'),
            SubscriptionPricingPhase::Promo,
            $amount,
        );
        $this->entityManager->persist($document);

        return $document;
    }

    /** @return array<string, string> */
    private function payload(BillingDocument $document, string $amount): array
    {
        return [
            'billing_document_id' => $document->getId()->toRfc4122(),
            'amount_tnd' => $amount,
            'method' => 'cash',
            'paid_at' => '2026-01-01T10:00:00+01:00',
            'reference' => 'Receipt',
        ];
    }
}
