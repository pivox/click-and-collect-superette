<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\ResolvedThemeOutput;
use App\Exception\PlatformThemeUnavailableException;
use App\Exception\StoreDisabledException;
use App\Exception\StoreNotFoundException;
use App\Mapper\ThemeCssVariablesMapper;
use App\Service\ThemeResolver;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<ResolvedThemeOutput>
 */
final readonly class StoreThemeProvider implements ProviderInterface
{
    public function __construct(
        private ThemeResolver $themeResolver,
        private ThemeCssVariablesMapper $themeCssVariablesMapper,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ResolvedThemeOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');

        try {
            return $this->themeCssVariablesMapper->map(
                $this->themeResolver->resolveForStore($storeId),
                $storeId,
            );
        } catch (StoreNotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        } catch (StoreDisabledException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        } catch (PlatformThemeUnavailableException $exception) {
            throw new HttpException(500, $exception->getMessage(), $exception);
        }
    }
}
