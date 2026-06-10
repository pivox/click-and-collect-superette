<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantProductPackOutput;
use App\Dto\MerchantProductPackUpdateInput;
use App\Entity\ProductPackItem;
use App\Factory\ProductPackOutputFactory;
use App\Security\MerchantShopAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantProductPackUpdateInput, MerchantProductPackOutput>
 */
final readonly class UpdateMerchantProductPackProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private ProductPackOutputFactory $outputFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantProductPackOutput
    {
        if (!$data instanceof MerchantProductPackUpdateInput) {
            throw new \InvalidArgumentException('MerchantProductPackUpdateInput expected.');
        }

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->entityManager->getRepository('App\Entity\Shop')->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $packId = (string) ($uriVariables['packId'] ?? '');
        if (!Uuid::isValid($packId)) {
            throw new NotFoundHttpException('PRODUCT_PACK_NOT_FOUND');
        }

        $pack = $this->entityManager->getRepository('App\Entity\ProductPack')->find($packId);
        if (null === $pack || !$pack->getShop()->getId()->equals($shop->getId())) {
            throw new NotFoundHttpException('PRODUCT_PACK_NOT_FOUND');
        }

        if (null !== $data->nameFr) {
            $pack->setNameFr($data->nameFr);
        }

        if (null !== $data->nameAr) {
            $pack->setNameAr($data->nameAr);
        }

        if (null !== $data->description) {
            $pack->setDescription($data->description);
        }

        if (null !== $data->isActive) {
            $pack->setIsActive($data->isActive);
        }

        if (null !== $data->items) {
            // Validate all items FIRST before making any changes
            $validatedProducts = [];
            foreach ($data->items as $itemInput) {
                $merchantProductId = $itemInput->merchantProductId;
                if (!Uuid::isValid($merchantProductId)) {
                    throw new NotFoundHttpException('MERCHANT_PRODUCT_NOT_FOUND');
                }

                $merchantProduct = $this->entityManager->getRepository('App\Entity\MerchantProduct')->find($merchantProductId);
                if (null === $merchantProduct || !$merchantProduct->getShop()->getId()->equals($shop->getId())) {
                    throw new NotFoundHttpException('MERCHANT_PRODUCT_NOT_FOUND');
                }

                $validatedProducts[] = ['product' => $merchantProduct, 'quantity' => $itemInput->quantity];
            }

            // Index existing items by product ID
            $existingItems = [];
            foreach ($pack->getItems() as $existingItem) {
                $existingItems[$existingItem->getMerchantProduct()->getId()->toRfc4122()] = $existingItem;
            }

            // Update in-place or insert new items
            $newProductIds = [];
            foreach ($validatedProducts as $validated) {
                $productId = $validated['product']->getId()->toRfc4122();
                $newProductIds[$productId] = true;

                if (isset($existingItems[$productId])) {
                    $existingItems[$productId]->setQuantity($validated['quantity']);
                } else {
                    $item = (new ProductPackItem())
                        ->setMerchantProduct($validated['product'])
                        ->setQuantity($validated['quantity']);
                    $pack->addItem($item);
                    $this->entityManager->persist($item);
                }
            }

            // Remove items that are no longer in the request
            foreach ($existingItems as $productId => $existingItem) {
                if (!isset($newProductIds[$productId])) {
                    $pack->removeItem($existingItem);
                }
            }
        }

        $this->entityManager->flush();

        return $this->outputFactory->toOutput($pack);
    }
}
