<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantLocalProductOutput;
use App\Dto\MerchantLocalProductCreateInput;
use App\Entity\MerchantLocalProduct;
use App\Entity\MerchantProduct;
use App\Repository\MerchantCategoryRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantLocalProductCreateInput, MerchantLocalProductOutput>
 */
final readonly class CreateMerchantLocalProductProcessor implements ProcessorInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private MerchantCategoryRepository $merchantCategoryRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantLocalProductOutput
    {
        if (!$data instanceof MerchantLocalProductCreateInput) {
            throw new \InvalidArgumentException('MerchantLocalProductCreateInput expected.');
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

        $localProduct = (new MerchantLocalProduct())
            ->setShop($shop)
            ->setNameFr($this->normalizeRequiredText($data->nameFr))
            ->setNameAr($this->normalizeOptionalText($data->nameAr))
            ->setBrandName($this->normalizeOptionalText($data->brandName))
            ->setVolume($this->normalizeDecimalText($data->volume))
            ->setUnit($data->unit)
            ->setBarcode($this->normalizeOptionalText($data->barcode))
            ->setDefaultCategoryName($this->normalizeOptionalText($data->defaultCategoryName))
            ->setPackQuantity($data->packQuantity);

        $merchantProduct = (new MerchantProduct())
            ->setShop($shop)
            ->setLocalProduct($localProduct)
            ->setPriceTnd($this->normalizeDecimalText($data->priceTnd) ?? $data->priceTnd)
            ->setAvailable($data->isAvailable)
            ->setVisible($data->isVisible)
            ->setMerchantNote($this->normalizeOptionalText($data->merchantNote));

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

        if (!$merchantProduct->hasExactlyOneProductSource()) {
            throw new \LogicException('Merchant product must have exactly one product source.');
        }

        $this->entityManager->persist($localProduct);
        $this->entityManager->persist($merchantProduct);
        $this->entityManager->flush();

        return new MerchantLocalProductOutput(
            merchantProductId: $merchantProduct->getId()->toRfc4122(),
            localProductId: $localProduct->getId()->toRfc4122(),
            nameFr: $localProduct->getNameFr(),
            nameAr: $localProduct->getNameAr(),
            brand: $localProduct->getBrandName(),
            category: $localProduct->getCatalogCategoryName(),
            volume: $localProduct->getVolume(),
            unit: $localProduct->getUnit()->value,
            priceTnd: $merchantProduct->getPriceTnd(),
            isAvailable: $merchantProduct->isAvailable(),
            isVisible: $merchantProduct->isVisible(),
            merchantNote: $merchantProduct->getMerchantNote(),
            packQuantity: $localProduct->getPackQuantity(),
        );
    }

    private function normalizeRequiredText(string $value): string
    {
        return trim($value);
    }

    private function normalizeOptionalText(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }

    private function normalizeDecimalText(?string $value): ?string
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        return bcadd($value, '0', 3);
    }
}
