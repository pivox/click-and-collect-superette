<?php

declare(strict_types=1);

namespace App\Enum;

enum SubscriptionPaymentReminderChannel: string
{
    case Email = 'email';
    case WhatsappManual = 'whatsapp_manual';
}
