<?php

declare(strict_types=1);

namespace App\Exception;

final class AiBudgetPolicyUnavailableException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('AI_BUDGET_POLICY_UNAVAILABLE');
    }
}
