<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminStoreOutput;
use App\ApiResource\AdminStoreOwnerOutput;
use App\Entity\Shop;
use App\Repository\AdminStoreRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminStoreOutput>
 */
final readonly class AdminStoreItemProvider implements ProviderInterface
{
    public function __construct(
        private AdminStoreRepository $adminStoreRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminStoreOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('ADMIN_STORE_NOT_FOUND');
        }

        $shop = $this->adminStoreRepository->findOne($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('ADMIN_STORE_NOT_FOUND');
        }

        return self::toOutput(
            shop: $shop,
            productsCount: $this->adminStoreRepository->countProducts($shop),
            exceptionalClosuresCount: $this->adminStoreRepository->countActiveExceptionalClosures($shop),
            pickupRulesCount: $this->adminStoreRepository->countActivePickupRules($shop),
        );
    }

    public static function toOutput(
        Shop $shop,
        int $productsCount,
        int $exceptionalClosuresCount = 0,
        int $pickupRulesCount = 0,
    ): AdminStoreOutput {
        $owner = $shop->getOwner();
        $theme = $shop->getTheme();

        return new AdminStoreOutput(
            id: $shop->getId()->toRfc4122(),
            name: $shop->getName(),
            slug: $shop->getSlug(),
            city: $shop->getCity(),
            isActive: $shop->isActive(),
            qrCodeToken: $shop->getQrCodeToken(),
            createdAt: $shop->getCreatedAt()->format(\DateTimeInterface::ATOM),
            owner: null === $owner ? null : new AdminStoreOwnerOutput(
                id: $owner->getId()->toRfc4122(),
                email: $owner->getEmail(),
            ),
            productsCount: $productsCount,
            themeId: null === $theme ? null : $theme->getId()->toRfc4122(),
            openingHours: $shop->getOpeningHours(),
            exceptionalClosuresCount: $exceptionalClosuresCount,
            pickupRulesCount: $pickupRulesCount,
        );
    }
}
