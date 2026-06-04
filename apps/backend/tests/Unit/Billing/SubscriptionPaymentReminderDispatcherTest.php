<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing;

use App\Billing\SubscriptionPaymentReminderDispatcher;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\SubscriptionLifecycle;
use App\Message\SendMerchantPaymentReminderMessage;
use App\Repository\SubscriptionRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class SubscriptionPaymentReminderDispatcherTest extends TestCase
{
    public function testDispatchesReminderForPayingSubscriptionOnScheduledDay(): void
    {
        $subscription = $this->subscription(
            email: 'merchant-paid@example.test',
            monthlyPriceTnd: '50.000',
            currentPeriodEndsAt: new \DateTimeImmutable('2026-06-11 00:00:00'),
        );

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository
            ->expects(self::once())
            ->method('findPaymentReminderCandidates')
            ->willReturn([$subscription]);

        $bus = new CapturingMessageBus();

        $sent = (new SubscriptionPaymentReminderDispatcher(
            $repository,
            $bus,
            new MockClock(new \DateTimeImmutable('2026-06-18 10:00:00')),
        ))->dispatchDueReminders();

        self::assertSame(1, $sent);
        self::assertCount(1, $bus->messages);
        self::assertInstanceOf(SendMerchantPaymentReminderMessage::class, $bus->messages[0]);
        self::assertSame('merchant-paid@example.test', $bus->messages[0]->merchantEmail);
        self::assertSame('50.000', $bus->messages[0]->amountTnd);
        self::assertSame('2026-06-11', $bus->messages[0]->dueDate);
        self::assertSame('j_plus_7', $bus->messages[0]->stage);
    }

    public function testSkipsFreeTrialAndUnscheduledDays(): void
    {
        $freeTrial = $this->subscription(
            email: 'merchant-free@example.test',
            monthlyPriceTnd: '0.000',
            currentPeriodEndsAt: new \DateTimeImmutable('2026-06-11 00:00:00'),
        );
        $unscheduled = $this->subscription(
            email: 'merchant-unscheduled@example.test',
            monthlyPriceTnd: '50.000',
            currentPeriodEndsAt: new \DateTimeImmutable('2026-06-15 00:00:00'),
        );

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository
            ->expects(self::once())
            ->method('findPaymentReminderCandidates')
            ->willReturn([$freeTrial, $unscheduled]);

        $bus = new CapturingMessageBus();

        $sent = (new SubscriptionPaymentReminderDispatcher(
            $repository,
            $bus,
            new MockClock(new \DateTimeImmutable('2026-06-18 10:00:00')),
        ))->dispatchDueReminders();

        self::assertSame(0, $sent);
        self::assertCount(0, $bus->messages);
    }

    private function subscription(
        string $email,
        string $monthlyPriceTnd,
        \DateTimeImmutable $currentPeriodEndsAt,
    ): Subscription {
        $merchant = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_MERCHANT'])
            ->setPassword('test-password')
            ->setName('Marchand Test');

        return Subscription::startTrial($merchant, new \DateTimeImmutable('2026-01-01 00:00:00'))
            ->setLifecycle(SubscriptionLifecycle::Active)
            ->setMonthlyPriceTnd($monthlyPriceTnd)
            ->setCurrentPeriodEndsAt($currentPeriodEndsAt);
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
