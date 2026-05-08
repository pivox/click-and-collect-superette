<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantCatalogProductOutput;
use App\Dto\MerchantCatalogCreateInput;
use App\Entity\MerchantProduct;
use App\Mapper\MerchantCatalogProductMapper;
use App\Repository\MerchantProductRepository;
use App\Repository\ProductReferenceRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantCatalogCreateInput, MerchantCatalogProductOutput>
 */
final readonly class CreateMerchantCatalogProductProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private ProductReferenceRepository $productReferenceRepository,
        private MerchantProductRepository $merchantProductRepository,
        private MerchantCatalogProductMapper $merchantCatalogProductMapper,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantCatalogProductOutput
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

        $this->entityManager->persist($merchantProduct);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('MERCHANT_PRODUCT_ALREADY_EXISTS');
        }

        return $this->merchantCatalogProductMapper->toOutput($merchantProduct);
    }
}
