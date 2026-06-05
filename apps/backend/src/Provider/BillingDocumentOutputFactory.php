<?php

declare(strict_types=1);

namespace App\Provider;

use App\ApiResource\BillingDocumentOutput;
use App\ApiResource\BillingDocumentReminderScheduleItemOutput;
use App\Billing\PaymentReminderSchedule;
use App\Entity\BillingDocument;
use App\Entity\SubscriptionPaymentReminder;
use App\Enum\SubscriptionPaymentReminderChannel;
use App\Repository\SubscriptionPaymentReminderRepository;

final readonly class BillingDocumentOutputFactory
{
    public function __construct(private SubscriptionPaymentReminderRepository $reminderRepository)
    {
    }

    public function fromBillingDocument(BillingDocument $document): BillingDocumentOutput
    {
        $merchant = $document->getMerchant();
        $subscription = $document->getSubscription();

        return new BillingDocumentOutput(
            id: $document->getId()->toRfc4122(),
            subscriptionId: $subscription->getId()->toRfc4122(),
            merchantId: $merchant->getId()->toRfc4122(),
            merchantEmail: $merchant->getEmail(),
            documentNumber: $document->getDocumentNumber(),
            documentType: $document->getDocumentType()->value,
            documentNatureLabel: $document->getDocumentType()->labelFr(),
            status: $document->getStatus()->value,
            pricingPhase: $document->getPricingPhase()->value,
            currency: $document->getCurrency(),
            billingPeriodStart: $document->getBillingPeriodStart()->format(\DateTimeInterface::ATOM),
            billingPeriodEnd: $document->getBillingPeriodEnd()->format(\DateTimeInterface::ATOM),
            issuedAt: $document->getIssuedAt()?->format(\DateTimeInterface::ATOM),
            dueAt: $document->getDueAt()?->format(\DateTimeInterface::ATOM),
            paidAt: $document->getPaidAt()?->format(\DateTimeInterface::ATOM),
            cancelledAt: $document->getCancelledAt()?->format(\DateTimeInterface::ATOM),
            cancellationReason: $document->getCancellationReason(),
            amountTnd: $document->getAmountTnd(),
            amountPaidTnd: $document->getAmountPaidTnd(),
            amountDueTnd: $document->getAmountDueTnd(),
            reminderSchedule: $this->reminderSchedule($document),
            createdAt: $document->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @return list<BillingDocumentReminderScheduleItemOutput>
     */
    private function reminderSchedule(BillingDocument $document): array
    {
        $dueAt = $document->getDueAt();
        if (null === $dueAt) {
            return [];
        }

        $traces = $this->indexTraces($this->reminderRepository->findForDocument($document));

        return array_map(
            function (array $item) use ($traces): BillingDocumentReminderScheduleItemOutput {
                $stage = $item['stage'];
                $stageValue = $stage->value;
                $emailTrace = $traces[$stageValue][SubscriptionPaymentReminderChannel::Email->value] ?? null;
                $whatsappTrace = $traces[$stageValue][SubscriptionPaymentReminderChannel::WhatsappManual->value]
                    ?? $traces['manual'][SubscriptionPaymentReminderChannel::WhatsappManual->value]
                    ?? null;

                return new BillingDocumentReminderScheduleItemOutput(
                    stage: $stageValue,
                    label: $stage->labelFr(),
                    scheduledAt: $item['scheduled_at']->format(\DateTimeInterface::ATOM),
                    emailStatus: $this->emailStatus($emailTrace),
                    emailSentAt: $emailTrace?->getSentAt()?->format(\DateTimeInterface::ATOM),
                    emailFailedAt: $emailTrace?->getFailedAt()?->format(\DateTimeInterface::ATOM),
                    whatsappContactedAt: $whatsappTrace?->getSentAt()?->format(\DateTimeInterface::ATOM),
                );
            },
            PaymentReminderSchedule::stagesForDueDate($dueAt),
        );
    }

    /**
     * @param list<SubscriptionPaymentReminder> $traces
     *
     * @return array<string, array<string, SubscriptionPaymentReminder>>
     */
    private function indexTraces(array $traces): array
    {
        $indexed = [];
        foreach ($traces as $trace) {
            $indexed[$trace->getStage()][$trace->getChannel()->value] = $trace;
        }

        return $indexed;
    }

    private function emailStatus(?SubscriptionPaymentReminder $trace): string
    {
        if (null !== $trace) {
            return $trace->getStatus()->value;
        }

        return 'planned';
    }
}
