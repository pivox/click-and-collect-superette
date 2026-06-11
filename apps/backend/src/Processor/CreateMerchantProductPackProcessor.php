<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantProductPackOutput;
use App\Dto\MerchantProductPackCreateInput;
use App\Entity\ProductPack;
use App\Entity\ProductPackItem;
use App\Factory\ProductPackOutputFactory;
use App\Security\MerchantShopAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<MerchantProductPackCreateInput, MerchantProductPackOutput>
 */
final readonly class CreateMerchantProductPackProcessor implements ProcessorInterface
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
        if (!$data instanceof MerchantProductPackCreateInput) {
            throw new \InvalidArgumentException('MerchantProductPackCreateInput expected.');
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

        // Validate all merchant products FIRST before persisting anything
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

        // All products validated, now create the pack
        $pack = (new ProductPack())
            ->setShop($shop)
            ->setNameFr($data->nameFr)
            ->setNameAr($data->nameAr)
            ->setDescription($data->description);

        $totalPriceTnd = '0.000';

        foreach ($validatedProducts as $validated) {
            $item = (new ProductPackItem())
                ->setMerchantProduct($validated['product'])
                ->setQuantity($validated['quantity']);

            $pack->addItem($item);
            $this->entityManager->persist($item);

            $itemPrice = (string) bcmul($validated['product']->getPriceTnd(), (string) $validated['quantity'], 3);
            $totalPriceTnd = (string) bcadd($totalPriceTnd, $itemPrice, 3);
        }

        $this->entityManager->persist($pack);
        $this->entityManager->flush();

        return $this->outputFactory->toOutput($pack);
    }
}
