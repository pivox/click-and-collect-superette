<?php

declare(strict_types=1);

namespace App\Provider;

use App\ApiResource\BillingDocumentOutput;
use App\Entity\BillingDocument;

final readonly class BillingDocumentOutputFactory
{
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
            createdAt: $document->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
