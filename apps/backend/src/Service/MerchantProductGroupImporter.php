<?php

declare(strict_types=1);

namespace App\Service;

use App\ApiResource\MerchantProductGroupImportErrorOutput;
use App\ApiResource\MerchantProductGroupImportOutput;
use App\Dto\MerchantProductGroupImportInput;
use App\Entity\ProductGroup;
use App\Entity\ProductGroupItem;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Enum\ProductGroupStatus;
use App\Enum\ProductGroupVisibility;
use App\Enum\ProductReferenceStatus;
use App\Repository\MerchantProductRepository;
use App\Repository\ProductGroupRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final readonly class MerchantProductGroupImporter
{
    public function __construct(
        private ShopRepository $shopRepository,
        private ProductGroupRepository $productGroupRepository,
        private MerchantProductRepository $merchantProductRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function import(string $storeId, MerchantProductGroupImportInput $input): MerchantProductGroupImportOutput
    {
        $shop = $this->getShop($storeId);
        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $group = $this->findVisibleGroup($input->groupId);
        $referencesById = $this->groupReferencesById($group);
        $selectedIds = $this->uniqueIds($input->selectedProductReferenceIds);

        $created = 0;
        $alreadyInCatalog = 0;
        $skipped = 0;
        $errors = [];
        $defaultAvailability = (bool) $input->defaultAvailability;

        foreach ($selectedIds as $productReferenceId) {
            $productReference = $referencesById[$productReferenceId] ?? null;
            if (null === $productReference) {
                ++$skipped;
                $errors[] = new MerchantProductGroupImportErrorOutput(
                    productReferenceId: $productReferenceId,
                    code: 'PRODUCT_REFERENCE_NOT_IN_GROUP',
                    message: 'Selected product reference does not belong to the product group.',
                );
                continue;
            }

            if (ProductReferenceStatus::Approved !== $productReference->getStatus()) {
                ++$skipped;
                $errors[] = new MerchantProductGroupImportErrorOutput(
                    productReferenceId: $productReferenceId,
                    code: 'PRODUCT_REFERENCE_NOT_APPROVED',
                    message: 'Selected product reference is not approved for merchant import.',
                );
                continue;
            }

            if (null !== $this->merchantProductRepository->findOneForShopAndProductReference($shop, $productReference)) {
                ++$alreadyInCatalog;
                continue;
            }

            try {
                $this->insertMerchantProduct($shop, $productReference, $defaultAvailability);
                ++$created;
            } catch (UniqueConstraintViolationException) {
                ++$alreadyInCatalog;
            }
        }

        return new MerchantProductGroupImportOutput(
            created: $created,
            alreadyInCatalog: $alreadyInCatalog,
            skipped: $skipped,
            requiresPriceCompletion: $created,
            errors: $errors,
        );
    }

    private function getShop(string $storeId): Shop
    {
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        return $shop;
    }

    private function findVisibleGroup(string $groupId): ProductGroup
    {
        if (!Uuid::isValid($groupId)) {
            throw new NotFoundHttpException('MERCHANT_PRODUCT_GROUP_NOT_FOUND');
        }

        /** @var ProductGroup|null $group */
        $group = $this->productGroupRepository->createQueryBuilder('pg')
            ->leftJoin('pg.items', 'pgi')
            ->addSelect('pgi')
            ->leftJoin('pgi.productReference', 'pr')
            ->addSelect('pr')
            ->andWhere('pg.id = :groupId')
            ->andWhere('pg.status = :status')
            ->andWhere('pg.visibility = :visibility')
            ->setParameter('groupId', $groupId, 'uuid')
            ->setParameter('status', ProductGroupStatus::Published)
            ->setParameter('visibility', ProductGroupVisibility::Merchant)
            ->getQuery()
            ->getOneOrNullResult();

        if (null === $group) {
            throw new NotFoundHttpException('MERCHANT_PRODUCT_GROUP_NOT_FOUND');
        }

        return $group;
    }

    /**
     * @return array<string, ProductReference>
     */
    private function groupReferencesById(ProductGroup $group): array
    {
        $referencesById = [];
        foreach ($group->getItems() as $item) {
            \assert($item instanceof ProductGroupItem);
            $productReference = $item->getProductReference();
            $referencesById[$productReference->getId()->toRfc4122()] = $productReference;
        }

        return $referencesById;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function uniqueIds(array $ids): array
    {
        $normalizedIds = array_map(
            static fn (string $id): string => Uuid::fromString($id)->toRfc4122(),
            $ids,
        );

        return array_values(array_unique($normalizedIds));
    }

    private function insertMerchantProduct(Shop $shop, ProductReference $productReference, bool $defaultAvailability): void
    {
        $now = new \DateTimeImmutable();

        $this->entityManager->getConnection()->insert(
            'merchant_products',
            [
                'id' => Uuid::v4(),
                'shop_id' => $shop->getId(),
                'product_reference_id' => $productReference->getId(),
                'price_tnd' => '0.000',
                'is_available' => $defaultAvailability,
                'is_visible' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 'uuid',
                'shop_id' => 'uuid',
                'product_reference_id' => 'uuid',
                'price_tnd' => Types::DECIMAL,
                'is_available' => Types::BOOLEAN,
                'is_visible' => Types::BOOLEAN,
                'created_at' => Types::DATETIME_IMMUTABLE,
                'updated_at' => Types::DATETIME_IMMUTABLE,
            ],
        );
    }
}
