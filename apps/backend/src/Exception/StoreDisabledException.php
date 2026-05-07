<?php

declare(strict_types=1);

namespace App\Exception;

final class StoreDisabledException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('STORE_DISABLED');
    }
}
