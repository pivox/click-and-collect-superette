<?php

declare(strict_types=1);

namespace App\Billing;

use App\Message\SendMerchantPaymentReminderMessage;
use App\Repository\SubscriptionRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class SubscriptionPaymentReminderDispatcher
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private MessageBusInterface $bus,
        private ClockInterface $clock,
    ) {
    }

    public function dispatchDueReminders(?\DateTimeImmutable $referenceDate = null): int
    {
        $now = $referenceDate ?? \DateTimeImmutable::createFromInterface($this->clock->now());
        $dispatched = 0;

        foreach ($this->subscriptionRepository->findPaymentReminderCandidates() as $subscription) {
            if (bccomp($subscription->getMonthlyPriceTnd(), '0.000', 3) <= 0) {
                continue;
            }

            $stage = PaymentReminderSchedule::resolveStage($subscription->getCurrentPeriodEndsAt(), $now);
            if (null === $stage) {
                continue;
            }

            $merchant = $subscription->getMerchant();
            $this->bus->dispatch(new SendMerchantPaymentReminderMessage(
                merchantEmail: $merchant->getEmail(),
                merchantName: $merchant->getName(),
                shopName: $merchant->getName(),
                dueDate: $subscription->getCurrentPeriodEndsAt()->format('Y-m-d'),
                amountTnd: $subscription->getMonthlyPriceTnd(),
                stage: $stage->value,
            ));
            ++$dispatched;
        }

        return $dispatched;
    }
}
