<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\PlatformThemeOutput;
use App\Dto\ThemeWriteInput;
use App\Exception\PlatformThemeUnavailableException;
use App\Mapper\PlatformThemeMapper;
use App\Repository\PlatformThemeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<ThemeWriteInput, PlatformThemeOutput>
 */
final readonly class UpdatePlatformThemeProcessor implements ProcessorInterface
{
    public function __construct(
        private PlatformThemeRepository $platformThemeRepository,
        private PlatformThemeMapper $platformThemeMapper,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PlatformThemeOutput
    {
        if (!$data instanceof ThemeWriteInput) {
            throw new \InvalidArgumentException('ThemeWriteInput expected.');
        }

        $platformTheme = $this->platformThemeRepository->findDefault();
        if (null === $platformTheme) {
            throw new PlatformThemeUnavailableException();
        }

        $this->platformThemeMapper->applyWriteInput($platformTheme, $data);
        $this->entityManager->flush();

        return $this->platformThemeMapper->toOutput($platformTheme);
    }
}
