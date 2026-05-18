<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;

interface PickupReminderNotifierInterface
{
    public function notifyCustomerPickupReminder(Order $order): void;
}
