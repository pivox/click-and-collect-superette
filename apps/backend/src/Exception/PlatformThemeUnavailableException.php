<?php

declare(strict_types=1);

namespace App\Exception;

final class PlatformThemeUnavailableException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('PLATFORM_THEME_UNAVAILABLE');
    }
}
