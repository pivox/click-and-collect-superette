<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantMeOutput;
use App\Entity\User;
use App\Repository\ShopRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<MerchantMeOutput>
 */
final readonly class MerchantMeProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private ShopRepository $shopRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MerchantMeOutput
    {
        $merchant = $this->security->getUser();
        if (!$merchant instanceof User) {
            throw new AccessDeniedHttpException('MERCHANT_ACCESS_REQUIRED');
        }

        if (!$merchant->isActive()) {
            throw new AccessDeniedHttpException('MERCHANT_ACCOUNT_INACTIVE');
        }

        $activeShops = $this->shopRepository->findActiveByOwner($merchant, limit: 2);
        if ([] === $activeShops) {
            throw new NotFoundHttpException('MERCHANT_ACTIVE_STORE_NOT_FOUND');
        }

        if (\count($activeShops) > 1) {
            throw new ConflictHttpException('MERCHANT_MULTIPLE_ACTIVE_STORES');
        }

        return MerchantMeOutput::fromUserAndShop($merchant, $activeShops[0]);
    }
}
