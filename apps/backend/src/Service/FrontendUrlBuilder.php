<?php

declare(strict_types=1);

namespace App\Service;

final readonly class FrontendUrlBuilder
{
    public function __construct(private PlatformSettingsManager $platformSettingsManager)
    {
    }

    public function build(string $path): string
    {
        return $this->platformSettingsManager->current()->getFrontendOrigin().'/'.ltrim($path, '/');
    }
}
