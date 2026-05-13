<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

// bcmath polyfill for environments where the extension is not installed.
if (!function_exists('bcadd')) {
    function bcadd(string $num1, string $num2, int $scale = 0): string
    {
        return number_format((float) $num1 + (float) $num2, $scale, '.', '');
    }

    function bcmul(string $num1, string $num2, int $scale = 0): string
    {
        return number_format((float) $num1 * (float) $num2, $scale, '.', '');
    }
}

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
