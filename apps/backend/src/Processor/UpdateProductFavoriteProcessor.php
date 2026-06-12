<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\CustomerProductFavoriteOutput;
use App\Dto\CustomerProductFavoriteInput;
use App\Entity\CustomerProductFavorite;
use App\Entity\User;
use App\Repository\CustomerProductFavoriteRepository;
use App\Repository\MerchantProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<CustomerProductFavoriteInput, CustomerProductFavoriteOutput>
 */
final readonly class UpdateProductFavoriteProcessor implements ProcessorInterface
{
    public function __construct(
        private MerchantProductRepository $merchantProductRepository,
        private CustomerProductFavoriteRepository $customerProductFavoriteRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomerProductFavoriteOutput
    {
        if (!$data instanceof CustomerProductFavoriteInput) {
            throw new \InvalidArgumentException('CustomerProductFavoriteInput expected.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        $merchantProductId = (string) ($uriVariables['merchantProductId'] ?? '');
        if (!Uuid::isValid($merchantProductId)) {
            throw new NotFoundHttpException('PRODUCT_NOT_FOUND');
        }

        $merchantProduct = $this->merchantProductRepository->find($merchantProductId);
        // Hidden products must not be discoverable through the favorite endpoint.
        if (null === $merchantProduct || !$merchantProduct->isVisible()) {
            throw new NotFoundHttpException('PRODUCT_NOT_FOUND');
        }

        $favorite = $this->customerProductFavoriteRepository->findOneByCustomerAndProduct($user, $merchantProduct);

        if ($data->isFavorite && null === $favorite) {
            $favorite = new CustomerProductFavorite();
            $favorite->setCustomer($user);
            $favorite->setMerchantProduct($merchantProduct);
            $this->entityManager->persist($favorite);
        }

        if (!$data->isFavorite && null !== $favorite) {
            $this->entityManager->remove($favorite);
        }

        $this->entityManager->flush();

        return new CustomerProductFavoriteOutput(
            merchantProductId: $merchantProduct->getId()->toRfc4122(),
            storeId: $merchantProduct->getShop()->getId()->toRfc4122(),
            isFavorite: $data->isFavorite,
        );
    }
}
