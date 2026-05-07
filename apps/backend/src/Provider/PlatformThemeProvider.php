<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\PlatformThemeOutput;
use App\Exception\PlatformThemeUnavailableException;
use App\Mapper\PlatformThemeMapper;
use App\Repository\PlatformThemeRepository;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @implements ProviderInterface<PlatformThemeOutput>
 */
final readonly class PlatformThemeProvider implements ProviderInterface
{
    public function __construct(
        private PlatformThemeRepository $platformThemeRepository,
        private PlatformThemeMapper $platformThemeMapper,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PlatformThemeOutput
    {
        $platformTheme = $this->platformThemeRepository->findDefault();
        if (null === $platformTheme) {
            throw new HttpException(500, (new PlatformThemeUnavailableException())->getMessage());
        }

        return $this->platformThemeMapper->toOutput($platformTheme);
    }
}
