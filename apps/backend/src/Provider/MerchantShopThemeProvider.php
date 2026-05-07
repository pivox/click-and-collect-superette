<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantShopThemeOutput;
use App\Exception\PlatformThemeUnavailableException;
use App\Mapper\ShopThemeMapper;
use App\Repository\PlatformThemeRepository;
use App\Repository\ShopRepository;
use App\Security\Voter\ShopOwnerVoter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<MerchantShopThemeOutput>
 */
final readonly class MerchantShopThemeProvider implements ProviderInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private PlatformThemeRepository $platformThemeRepository,
        private ShopThemeMapper $shopThemeMapper,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MerchantShopThemeOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        if (!$this->authorizationChecker->isGranted(ShopOwnerVoter::SHOP_OWNER, $shop)) {
            throw new AccessDeniedHttpException('SHOP_THEME_FORBIDDEN');
        }

        $theme = $shop->getTheme();
        if (null === $theme) {
            $theme = $this->platformThemeRepository->findDefault();
        }

        if (null === $theme) {
            throw new HttpException(500, (new PlatformThemeUnavailableException())->getMessage());
        }

        return $this->shopThemeMapper->toMerchantOutput($theme, $storeId);
    }
}
