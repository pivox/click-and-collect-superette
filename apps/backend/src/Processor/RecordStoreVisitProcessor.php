<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\CustomerStoreOutput;
use App\Dto\CustomerStoreVisitInput;
use App\Entity\CustomerShop;
use App\Entity\User;
use App\Repository\CustomerShopRepository;
use App\Repository\ShopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<CustomerStoreVisitInput, CustomerStoreOutput>
 */
final readonly class RecordStoreVisitProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private CustomerShopRepository $customerShopRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomerStoreOutput
    {
        if (!$data instanceof CustomerStoreVisitInput) {
            throw new \InvalidArgumentException('CustomerStoreVisitInput expected.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop || !$shop->isActive()) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $relation = $this->customerShopRepository->findOneByCustomerAndShop($user, $shop);

        if (null === $relation) {
            $relation = (new CustomerShop())
                ->setCustomer($user)
                ->setShop($shop)
                ->setSource($data->source);

            $this->entityManager->persist($relation);
        } else {
            $relation->touchLastSeenAt();
        }

        $this->entityManager->flush();

        return new CustomerStoreOutput(
            storeId: $relation->getShop()->getId()->toRfc4122(),
            name: $relation->getShop()->getName(),
            slug: $relation->getShop()->getSlug(),
            city: $relation->getShop()->getCity(),
            country: $relation->getShop()->getCountry(),
            isActive: $relation->getShop()->isActive(),
            isFavorite: $relation->isFavorite(),
            source: $relation->getSource()->value,
            firstSeenAt: $relation->getFirstSeenAt()->format(\DateTimeInterface::ATOM),
            lastSeenAt: $relation->getLastSeenAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
