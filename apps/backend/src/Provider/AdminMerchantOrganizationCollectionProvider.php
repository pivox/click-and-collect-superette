<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminMerchantOrganizationListOutput;
use App\ApiResource\AdminMerchantOrganizationOutput;
use App\Entity\MerchantMembership;
use App\Entity\MerchantOrganization;
use App\Enum\MerchantMembershipStatus;
use App\Repository\MerchantMembershipRepository;
use App\Repository\MerchantOrganizationRepository;
use App\Repository\ShopRepository;
use App\Repository\SubscriptionRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<AdminMerchantOrganizationListOutput>
 */
final readonly class AdminMerchantOrganizationCollectionProvider implements ProviderInterface
{
    private const MAX_LIMIT = 50;

    public function __construct(
        private MerchantOrganizationRepository $organizationRepository,
        private MerchantMembershipRepository $membershipRepository,
        private ShopRepository $shopRepository,
        private SubscriptionRepository $subscriptionRepository,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminMerchantOrganizationListOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        $page = max(1, (int) ($request?->query->get('page') ?? 1));
        $limit = min(self::MAX_LIMIT, max(1, (int) ($request?->query->get('limit') ?? 20)));

        $organizations = $this->organizationRepository->findBy(
            [],
            ['createdAt' => 'DESC', 'id' => 'DESC'],
            $limit,
            ($page - 1) * $limit,
        );
        $total = $this->organizationRepository->count([]);

        $items = array_map(fn (MerchantOrganization $organization): AdminMerchantOrganizationOutput => $this->toOutput($organization), $organizations);

        return new AdminMerchantOrganizationListOutput(
            id: 'admin-merchant-organizations',
            items: $items,
            page: $page,
            limit: $limit,
            total: $total,
        );
    }

    private function toOutput(MerchantOrganization $organization): AdminMerchantOrganizationOutput
    {
        $primaryAccount = $organization->getPrimaryAccount();

        // Invited accounts count towards the quota, revoked ones do not.
        $accountsCount = \count(array_filter(
            $this->membershipRepository->findBy(['organization' => $organization]),
            static fn (MerchantMembership $membership): bool => MerchantMembershipStatus::Revoked !== $membership->getStatus(),
        ));

        $subscription = $this->subscriptionRepository->findOneByOrganization($organization);
        if (null === $subscription && null !== $primaryAccount) {
            // Transition fallback: not yet backfilled subscriptions.
            $subscription = $this->subscriptionRepository->findOneByMerchant($primaryAccount);
        }

        return new AdminMerchantOrganizationOutput(
            id: $organization->getId()->toRfc4122(),
            name: $organization->getName(),
            active: $organization->isActive(),
            primaryAccount: null === $primaryAccount ? null : [
                'user_id' => $primaryAccount->getId()->toRfc4122(),
                'email' => $primaryAccount->getEmail(),
                'name' => (string) $primaryAccount->getName(),
            ],
            accountsCount: $accountsCount,
            storesCount: \count($this->shopRepository->findBy(['merchantOrganization' => $organization])),
            subscriptionStatus: $subscription?->getLifecycle()->value,
        );
    }
}
