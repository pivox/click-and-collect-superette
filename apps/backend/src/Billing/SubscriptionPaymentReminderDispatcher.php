<?php

declare(strict_types=1);

namespace App\Billing;

use App\Enum\SubscriptionPaymentReminderChannel;
use App\Message\SendMerchantPaymentReminderMessage;
use App\Repository\BillingDocumentRepository;
use App\Repository\SubscriptionPaymentReminderRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class SubscriptionPaymentReminderDispatcher
{
    public function __construct(
        private BillingDocumentRepository $billingDocumentRepository,
        private SubscriptionPaymentReminderRepository $reminderRepository,
        private MessageBusInterface $bus,
        private ClockInterface $clock,
    ) {
    }

    public function dispatchDueReminders(?\DateTimeImmutable $referenceDate = null): int
    {
        $now = $referenceDate ?? \DateTimeImmutable::createFromInterface($this->clock->now());
        $dispatched = 0;

        foreach ($this->billingDocumentRepository->findPaymentReminderCandidates() as $document) {
            $dueAt = $document->getDueAt();
            if (null === $dueAt) {
                continue;
            }

            $stage = PaymentReminderSchedule::resolveStage($dueAt, $now);
            if (null === $stage) {
                continue;
            }

            if (null !== $this->reminderRepository->findOneForDocumentStageChannel(
                $document,
                $stage->value,
                SubscriptionPaymentReminderChannel::Email,
            )) {
                continue;
            }

            $merchant = $document->getMerchant();
            $this->bus->dispatch(new SendMerchantPaymentReminderMessage(
                billingDocumentId: $document->getId()->toRfc4122(),
                documentNumber: $document->getDocumentNumber(),
                merchantEmail: $merchant->getEmail(),
                merchantName: $merchant->getName(),
                shopName: $merchant->getName(),
                dueDate: $dueAt->format('Y-m-d'),
                amountTnd: $document->getAmountDueTnd(),
                stage: $stage->value,
            ));
            ++$dispatched;
        }

        return $dispatched;
    }
}
