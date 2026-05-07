<?php

declare(strict_types=1);

namespace App\Exception;

final class StoreNotFoundException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('STORE_NOT_FOUND');
    }
}
