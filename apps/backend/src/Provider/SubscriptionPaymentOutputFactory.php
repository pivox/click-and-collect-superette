<?php

declare(strict_types=1);

namespace App\Provider;

use App\ApiResource\SubscriptionPaymentOutput;
use App\Entity\SubscriptionPayment;

final readonly class SubscriptionPaymentOutputFactory
{
    public function fromSubscriptionPayment(SubscriptionPayment $payment): SubscriptionPaymentOutput
    {
        $merchant = $payment->getMerchant();

        return new SubscriptionPaymentOutput(
            id: $payment->getId()->toRfc4122(),
            billingDocumentId: $payment->getBillingDocument()->getId()->toRfc4122(),
            subscriptionId: $payment->getSubscription()->getId()->toRfc4122(),
            merchantId: $merchant->getId()->toRfc4122(),
            merchantEmail: $merchant->getEmail(),
            amountTnd: $payment->getAmountTnd(),
            currency: $payment->getCurrency(),
            method: $payment->getMethod()->value,
            reference: $payment->getReference(),
            status: $payment->getStatus()->value,
            paidAt: $payment->getPaidAt()->format(\DateTimeInterface::ATOM),
            periodStart: $payment->getPeriodStart()->format(\DateTimeInterface::ATOM),
            periodEnd: $payment->getPeriodEnd()->format(\DateTimeInterface::ATOM),
            validatedByAdminId: $payment->getValidatedByAdmin()->getId()->toRfc4122(),
            validatedAt: $payment->getValidatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
