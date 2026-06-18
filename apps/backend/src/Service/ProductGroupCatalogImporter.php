<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ProductGroup;
use App\Entity\ProductGroupItem;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Enum\ProductGroupStatus;
use App\Enum\ProductGroupVisibility;
use App\Enum\ProductReferenceStatus;
use App\Repository\MerchantProductRepository;
use App\Repository\ProductGroupRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final readonly class ProductGroupCatalogImporter
{
    public function __construct(
        private ProductGroupRepository $productGroupRepository,
        private MerchantProductRepository $merchantProductRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<string> $groupIds
     */
    public function importGroupsForAdmin(Shop $shop, array $groupIds): ProductGroupCatalogImportResult
    {
        $summary = new MutableProductGroupCatalogImportSummary();

        foreach ($this->uniqueIds($groupIds) as $groupId) {
            $group = $this->findVisibleGroup($groupId, 'ADMIN_PRODUCT_GROUP_NOT_FOUND');
            $selectedIds = [];

            foreach ($group->getSortedItems() as $item) {
                \assert($item instanceof ProductGroupItem);
                $selectedIds[] = $item->getProductReference()->getId()->toRfc4122();
            }

            $this->importSelectedReferences($shop, $group, $selectedIds, true, $summary);
        }

        return $summary->toResult();
    }

    /**
     * @param list<string> $selectedProductReferenceIds
     */
    public function importSelectedGroupReferences(
        Shop $shop,
        string $groupId,
        array $selectedProductReferenceIds,
        bool $defaultAvailability,
    ): ProductGroupCatalogImportResult {
        $group = $this->findVisibleGroup($groupId, 'MERCHANT_PRODUCT_GROUP_NOT_FOUND');
        $summary = new MutableProductGroupCatalogImportSummary();
        $this->importSelectedReferences($shop, $group, $selectedProductReferenceIds, $defaultAvailability, $summary);

        return $summary->toResult();
    }

    /**
     * @param list<string> $selectedProductReferenceIds
     */
    private function importSelectedReferences(
        Shop $shop,
        ProductGroup $group,
        array $selectedProductReferenceIds,
        bool $defaultAvailability,
        MutableProductGroupCatalogImportSummary $summary,
    ): void {
        $referencesById = $this->groupReferencesById($group);

        foreach ($this->uniqueIds($selectedProductReferenceIds) as $productReferenceId) {
            $productReference = $referencesById[$productReferenceId] ?? null;
            if (null === $productReference) {
                $summary->skipped(new ProductGroupCatalogImportError(
                    productReferenceId: $productReferenceId,
                    code: 'PRODUCT_REFERENCE_NOT_IN_GROUP',
                    message: 'Selected product reference does not belong to the product group.',
                    productGroupId: $group->getId()->toRfc4122(),
                ));
                continue;
            }

            if (ProductReferenceStatus::Approved !== $productReference->getStatus()) {
                $summary->skipped(new ProductGroupCatalogImportError(
                    productReferenceId: $productReferenceId,
                    code: 'PRODUCT_REFERENCE_NOT_APPROVED',
                    message: 'Selected product reference is not approved for merchant import.',
                    productGroupId: $group->getId()->toRfc4122(),
                ));
                continue;
            }

            if (null !== $this->merchantProductRepository->findOneForShopAndProductReference($shop, $productReference)) {
                $summary->alreadyInCatalog();
                continue;
            }

            try {
                $this->insertMerchantProduct($shop, $productReference, $defaultAvailability);
                $summary->created();
            } catch (UniqueConstraintViolationException) {
                $summary->alreadyInCatalog();
            }
        }
    }

    private function findVisibleGroup(string $groupId, string $notFoundCode): ProductGroup
    {
        if (!Uuid::isValid($groupId)) {
            throw new NotFoundHttpException($notFoundCode);
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
            throw new NotFoundHttpException($notFoundCode);
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

final class MutableProductGroupCatalogImportSummary
{
    private int $created = 0;
    private int $alreadyInCatalog = 0;
    private int $skipped = 0;

    /** @var list<ProductGroupCatalogImportError> */
    private array $errors = [];

    public function created(): void
    {
        ++$this->created;
    }

    public function alreadyInCatalog(): void
    {
        ++$this->alreadyInCatalog;
    }

    public function skipped(ProductGroupCatalogImportError $error): void
    {
        ++$this->skipped;
        $this->errors[] = $error;
    }

    public function toResult(): ProductGroupCatalogImportResult
    {
        return new ProductGroupCatalogImportResult(
            created: $this->created,
            alreadyInCatalog: $this->alreadyInCatalog,
            skipped: $this->skipped,
            requiresPriceCompletion: $this->created,
            errors: $this->errors,
        );
    }
}
