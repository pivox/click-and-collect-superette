<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BulkLocalProductCreatedOutput;
use App\Dto\BulkLocalProductCreateInput;
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
 * @implements ProcessorInterface<BulkLocalProductCreateInput, BulkLocalProductCreatedOutput>
 */
final readonly class CreateBulkLocalProductProcessor implements ProcessorInterface
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
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BulkLocalProductCreatedOutput
    {
        if (!$data instanceof BulkLocalProductCreateInput) {
            throw new \InvalidArgumentException('BulkLocalProductCreateInput expected.');
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

        $merchantCategory = null;
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
        }

        $baseNameFr = trim($data->baseNameFr);
        $baseNameAr = $this->normalizeOptionalText($data->baseNameAr);
        $brandName = $this->normalizeOptionalText($data->brandName);
        $defaultCategoryName = $this->normalizeOptionalText($data->defaultCategoryName);

        /** @var list<array{merchant_product_id: string, local_product_id: string, name_fr: string, price_tnd: string}> $items */
        $items = [];

        $this->entityManager->wrapInTransaction(function () use ($data, $shop, $merchantCategory, $baseNameFr, $baseNameAr, $brandName, $defaultCategoryName, &$items): void {
            foreach ($data->formats as $format) {
                $localProduct = (new MerchantLocalProduct())
                    ->setShop($shop)
                    ->setNameFr($baseNameFr)
                    ->setNameAr($baseNameAr)
                    ->setBrandName($brandName)
                    ->setVolume($this->normalizeDecimalText($format->volume))
                    ->setUnit($format->unit)
                    ->setBarcode($this->normalizeOptionalText($format->barcode))
                    ->setDefaultCategoryName($defaultCategoryName)
                    ->setPackQuantity($format->packQuantity);

                $merchantProduct = (new MerchantProduct())
                    ->setShop($shop)
                    ->setLocalProduct($localProduct)
                    ->setPriceTnd($this->normalizeDecimalText($format->priceTnd) ?? $format->priceTnd)
                    ->setAvailable($format->isAvailable)
                    ->setVisible($format->isVisible)
                    ->setMerchantNote($this->normalizeOptionalText($format->merchantNote));

                if (null !== $merchantCategory) {
                    $merchantProduct->setMerchantCategory($merchantCategory);
                }

                if (!$merchantProduct->hasExactlyOneProductSource()) {
                    throw new \LogicException('Merchant product must have exactly one product source.');
                }

                $this->entityManager->persist($localProduct);
                $this->entityManager->persist($merchantProduct);

                $items[] = [
                    'merchant_product_id' => $merchantProduct->getId()->toRfc4122(),
                    'local_product_id' => $localProduct->getId()->toRfc4122(),
                    'name_fr' => $localProduct->getNameFr(),
                    'price_tnd' => $merchantProduct->getPriceTnd(),
                ];
            }

            $this->entityManager->flush();
        });

        return new BulkLocalProductCreatedOutput(
            id: 'bulk',
            createdCount: \count($items),
            items: $items,
        );
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
