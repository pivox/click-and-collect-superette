<?php

declare(strict_types=1);

namespace App\Billing;

interface MerchantPaymentReminderEmailSenderInterface
{
    public function send(MerchantPaymentReminderContext $context): void;
}
