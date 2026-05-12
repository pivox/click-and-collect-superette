<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\CustomerStoreOutput;
use App\Entity\CustomerShop;
use App\Entity\User;
use App\Repository\CustomerShopRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<CustomerStoreOutput>
 */
final readonly class CustomerStoreCollectionProvider implements ProviderInterface
{
    public function __construct(
        private CustomerShopRepository $customerShopRepository,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return list<CustomerStoreOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        $relations = $this->customerShopRepository->findActiveByCustomer($user);

        return array_map(
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
    }
}
