<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing;

use App\Billing\SubscriptionPaymentReminderDispatcher;
use App\Entity\BillingDocument;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\SubscriptionPaymentReminderChannel;
use App\Enum\SubscriptionPricingPhase;
use App\Message\SendMerchantPaymentReminderMessage;
use App\Repository\BillingDocumentRepository;
use App\Repository\SubscriptionPaymentReminderRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class SubscriptionPaymentReminderDispatcherTest extends TestCase
{
    public function testDispatchesReminderForPayingSubscriptionOnScheduledDay(): void
    {
        $document = $this->document('merchant-paid@example.test', 'MS-2026-000301', '50.000', new \DateTimeImmutable('2026-06-11 23:59:59'));

        $documentRepository = $this->createMock(BillingDocumentRepository::class);
        $documentRepository
            ->expects(self::once())
            ->method('findPaymentReminderCandidates')
            ->willReturn([$document]);
        $reminderRepository = $this->createMock(SubscriptionPaymentReminderRepository::class);
        $reminderRepository
            ->expects(self::once())
            ->method('findOneForDocumentStageChannel')
            ->with($document, 'j_plus_7', SubscriptionPaymentReminderChannel::Email)
            ->willReturn(null);

        $bus = new CapturingMessageBus();

        $sent = (new SubscriptionPaymentReminderDispatcher(
            $documentRepository,
            $reminderRepository,
            $bus,
            new MockClock(new \DateTimeImmutable('2026-06-18 10:00:00')),
        ))->dispatchDueReminders();

        self::assertSame(1, $sent);
        self::assertCount(1, $bus->messages);
        self::assertInstanceOf(SendMerchantPaymentReminderMessage::class, $bus->messages[0]);
        self::assertSame('merchant-paid@example.test', $bus->messages[0]->merchantEmail);
        self::assertSame('50.000', $bus->messages[0]->amountTnd);
        self::assertSame('2026-06-11', $bus->messages[0]->dueDate);
        self::assertSame($document->getId()->toRfc4122(), $bus->messages[0]->billingDocumentId);
        self::assertSame('MS-2026-000301', $bus->messages[0]->documentNumber);
        self::assertSame('j_plus_7', $bus->messages[0]->stage);
    }

    public function testSkipsFreeTrialAndUnscheduledDays(): void
    {
        $unscheduled = $this->document('merchant-unscheduled@example.test', 'MS-2026-000302', '50.000', new \DateTimeImmutable('2026-06-14 23:59:59'));

        $documentRepository = $this->createMock(BillingDocumentRepository::class);
        $documentRepository
            ->expects(self::once())
            ->method('findPaymentReminderCandidates')
            ->willReturn([$unscheduled]);
        $reminderRepository = $this->createMock(SubscriptionPaymentReminderRepository::class);
        $reminderRepository
            ->expects(self::never())
            ->method('findOneForDocumentStageChannel');

        $bus = new CapturingMessageBus();

        $sent = (new SubscriptionPaymentReminderDispatcher(
            $documentRepository,
            $reminderRepository,
            $bus,
            new MockClock(new \DateTimeImmutable('2026-06-18 10:00:00')),
        ))->dispatchDueReminders();

        self::assertSame(0, $sent);
        self::assertCount(0, $bus->messages);
    }

    private function document(string $email, string $documentNumber, string $amount, \DateTimeImmutable $dueAt): BillingDocument
    {
        $merchant = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_MERCHANT'])
            ->setPassword('test-password')
            ->setName('Marchand Test');

        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-01-01 00:00:00'))
            ->setMonthlyPriceTnd($amount);

        return BillingDocument::issueMonthlyStatement(
            subscription: $subscription,
            documentNumber: $documentNumber,
            billingPeriodStart: new \DateTimeImmutable('2026-06-01 00:00:00'),
            billingPeriodEnd: new \DateTimeImmutable('2026-07-01 00:00:00'),
            issuedAt: new \DateTimeImmutable('2026-06-01 09:00:00'),
            dueAt: $dueAt,
            pricingPhase: SubscriptionPricingPhase::Promo,
            amountTnd: $amount,
        );
    }
}

final class CapturingMessageBus implements MessageBusInterface
{
    /** @var list<object> */
    public array $messages = [];

    /**
     * @param array<mixed> $stamps
     */
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->messages[] = $message;

        return new Envelope($message, $stamps);
    }
}
