<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\MerchantCategory;
use App\Repository\MerchantCategoryRepository;
use App\Repository\MerchantProductRepository;
use App\Security\MerchantShopAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class DeleteMerchantCategoryProcessor implements ProcessorInterface
{
    public function __construct(
        private MerchantCategoryRepository $merchantCategoryRepository,
        private MerchantProductRepository $merchantProductRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $merchantCategory = $this->findMerchantCategory((string) ($uriVariables['merchantCategoryId'] ?? ''));
        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($merchantCategory->getShop());

        $attachedProducts = $this->merchantProductRepository->findBy(['merchantCategory' => $merchantCategory]);
        foreach ($attachedProducts as $merchantProduct) {
            $merchantProduct->setMerchantCategory(null);
        }

        $this->entityManager->remove($merchantCategory);
        $this->entityManager->flush();
    }

    private function findMerchantCategory(string $merchantCategoryId): MerchantCategory
    {
        if (!Uuid::isValid($merchantCategoryId)) {
            throw new NotFoundHttpException('MERCHANT_CATEGORY_NOT_FOUND');
        }

        $merchantCategory = $this->merchantCategoryRepository->find($merchantCategoryId);
        if (null === $merchantCategory) {
            throw new NotFoundHttpException('MERCHANT_CATEGORY_NOT_FOUND');
        }

        return $merchantCategory;
    }
}
