<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\MerchantCatalogCreateInput;
use App\Entity\MerchantProduct;
use App\Enum\ProductReferenceStatus;
use App\Repository\MerchantCategoryRepository;
use App\Repository\MerchantProductRepository;
use App\Repository\ProductReferenceRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantCatalogCreateInput, void>
 */
final readonly class CreateMerchantCatalogProductProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private ProductReferenceRepository $productReferenceRepository,
        private MerchantProductRepository $merchantProductRepository,
        private MerchantCategoryRepository $merchantCategoryRepository,
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
        if (!$data instanceof MerchantCatalogCreateInput) {
            throw new \InvalidArgumentException('MerchantCatalogCreateInput expected.');
        }

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $productReference = $this->productReferenceRepository->find($data->productReferenceId);
        if (null === $productReference) {
            throw new NotFoundHttpException('PRODUCT_REFERENCE_NOT_FOUND');
        }

        if (ProductReferenceStatus::Approved !== $productReference->getStatus()) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'PRODUCT_REFERENCE_NOT_APPROVED');
        }

        if (null !== $this->merchantProductRepository->findOneForShopAndProductReference($shop, $productReference)) {
            throw new ConflictHttpException('MERCHANT_PRODUCT_ALREADY_EXISTS');
        }

        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd($data->priceTnd)
            ->setAvailable($data->isAvailable)
            ->setVisible($data->isVisible)
            ->setMerchantNote($data->merchantNote);

        if (null !== $data->merchantCategoryId) {
            $merchantCategory = $this->merchantCategoryRepository->find($data->merchantCategoryId);
            if (null === $merchantCategory) {
                throw new NotFoundHttpException('MERCHANT_CATEGORY_NOT_FOUND');
            }
            if (!$merchantCategory->getShop()->getId()->equals($shop->getId())) {
                throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'MERCHANT_CATEGORY_SHOP_INVALID');
            }
            if (!$merchantCategory->isActive()) {
                throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'MERCHANT_CATEGORY_INACTIVE');
            }

            $merchantProduct->setMerchantCategory($merchantCategory);
        }

        $this->entityManager->persist($merchantProduct);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('MERCHANT_PRODUCT_ALREADY_EXISTS');
        }
    }
}
