<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\CustomerStoreListOutput;
use App\ApiResource\CustomerStoreOutput;
use App\Entity\CustomerShop;
use App\Entity\User;
use App\Repository\CustomerShopRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<CustomerStoreListOutput>
 */
final readonly class CustomerStoreListProvider implements ProviderInterface
{
    public function __construct(
        private CustomerShopRepository $customerShopRepository,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CustomerStoreListOutput
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        $request = $this->requestStack->getCurrentRequest();
        $page = max(1, (int) ($request?->query->get('page') ?? 1));
        $limit = min(50, max(1, (int) ($request?->query->get('limit') ?? 20)));
        $offset = ($page - 1) * $limit;

        $relations = $this->customerShopRepository->findActiveByCustomerPaginated($user, $limit, $offset);
        $total = $this->customerShopRepository->countActiveByCustomer($user);

        $items = array_map(
            static fn (CustomerShop $cs): CustomerStoreOutput => new CustomerStoreOutput(
                storeId: $cs->getShop()->getId()->toRfc4122(),
                name: $cs->getShop()->getName(),
                slug: $cs->getShop()->getSlug(),
                city: $cs->getShop()->getCity(),
                country: $cs->getShop()->getCountry(),
                isActive: $cs->getShop()->isActive(),
                isFavorite: $cs->isFavorite(),
                source: $cs->getSource()->value,
                firstSeenAt: $cs->getFirstSeenAt()->format(\DateTimeInterface::ATOM),
                lastSeenAt: $cs->getLastSeenAt()->format(\DateTimeInterface::ATOM),
            ),
            $relations,
        );

        return new CustomerStoreListOutput(
            id: 'current',
            items: $items,
            total: $total,
            page: $page,
            limit: $limit,
        );
    }
}
