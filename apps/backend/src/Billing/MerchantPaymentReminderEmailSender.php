<?php

declare(strict_types=1);

namespace App\Billing;

use App\Email\TransactionalEmailSenderInterface;

final readonly class MerchantPaymentReminderEmailSender implements MerchantPaymentReminderEmailSenderInterface
{
    public function __construct(
        private MerchantPaymentReminderEmailFactory $factory,
        private TransactionalEmailSenderInterface $emailSender,
    ) {
    }

    public function send(MerchantPaymentReminderContext $context): void
    {
        $this->emailSender->send($this->factory->create($context));
    }
}
