<?php

declare(strict_types=1);

namespace App\Service\Push;

use App\Entity\MobileDevice;

/**
 * MOBILE-PUSH #620: provider abstraction (pattern #9 — the interface is the
 * mockable seam, the adapters stay final). Adding direct FCM/APNs = adding an
 * adapter and routing by MobileDevice::getProvider(), without touching the
 * business code. No test may ever call a real adapter — always mock this
 * interface.
 */
interface PushSenderInterface
{
    public function send(PushMessage $message, MobileDevice $device): PushSendResult;
}
