<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantTeamAccountListOutput;
use App\ApiResource\MerchantTeamAccountOutput;
use App\Entity\MerchantMembership;
use App\Enum\MerchantMembershipStatus;
use App\Repository\MerchantMembershipRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantTeamManagementChecker;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<MerchantTeamAccountListOutput>
 */
final readonly class MerchantTeamAccountCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private MerchantMembershipRepository $membershipRepository,
        private MerchantTeamManagementChecker $teamManagementChecker,
        private int $merchantTeamAccountLimit,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MerchantTeamAccountListOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('MERCHANT_STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('MERCHANT_STORE_NOT_FOUND');
        }

        ['organization' => $organization] = $this->teamManagementChecker->denyUnlessPrimaryAccountOfShop($shop);

        $memberships = $this->membershipRepository->findBy(
            ['organization' => $organization],
            ['createdAt' => 'ASC'],
        );

        $activeOrInvited = \count(array_filter(
            $memberships,
            static fn (MerchantMembership $membership): bool => MerchantMembershipStatus::Revoked !== $membership->getStatus(),
        ));

        return new MerchantTeamAccountListOutput(
            storeId: $shop->getId()->toRfc4122(),
            organizationId: $organization->getId()->toRfc4122(),
            limit: $this->merchantTeamAccountLimit,
            activeOrInvitedCount: $activeOrInvited,
            items: array_map(
                static fn (MerchantMembership $membership): MerchantTeamAccountOutput => MerchantTeamAccountOutput::fromMembership($membership),
                $memberships,
            ),
        );
    }
}
