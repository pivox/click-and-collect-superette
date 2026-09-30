<?php

declare(strict_types=1);

namespace App\Service\Push;

enum PushSendStatus: string
{
    case Success = 'success';
    case TemporaryFailure = 'temporary_failure';
    case PermanentFailure = 'permanent_failure';
}
