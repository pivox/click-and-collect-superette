<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantMeOutput;
use App\Entity\MerchantMembership;
use App\Entity\Shop;
use App\Entity\User;
use App\Repository\MerchantMembershipRepository;
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
        private MerchantMembershipRepository $membershipRepository,
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

        // MERCHANT-TEAM-003: the current store comes from the organization of
        // the active membership, so secondary accounts bootstrap the same shop
        // as the primary one. Shops not yet backfilled fall back to ownership.
        $membership = $this->membershipRepository->findOneActiveByUser($merchant);
        $organization = $membership?->getOrganization();

        if (null !== $organization && !$organization->isActive()) {
            throw new AccessDeniedHttpException('MERCHANT_ACCOUNT_INACTIVE');
        }

        $activeShops = null !== $organization
            ? $this->shopRepository->findBy(
                ['merchantOrganization' => $organization, 'active' => true],
                ['createdAt' => 'ASC'],
                2,
            )
            : $this->shopRepository->findActiveByOwner($merchant, limit: 2);

        if ([] === $activeShops) {
            throw new NotFoundHttpException('MERCHANT_ACTIVE_STORE_NOT_FOUND');
        }

        if (\count($activeShops) > 1) {
            // V1 keeps a single active shop per context; a picker is a
            // dedicated issue — never silently invent a choice.
            throw new ConflictHttpException('MERCHANT_MULTIPLE_ACTIVE_STORES');
        }

        /** @var Shop $shop */
        $shop = $activeShops[0];

        /* @var ?MerchantMembership $membership */
        return MerchantMeOutput::fromUserAndShop($merchant, $shop, $membership);
    }
}
