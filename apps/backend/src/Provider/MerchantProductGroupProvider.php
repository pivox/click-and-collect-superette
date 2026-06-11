<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\MerchantProductGroupItemOutput;
use App\ApiResource\MerchantProductGroupListOutput;
use App\ApiResource\MerchantProductGroupOutput;
use App\ApiResource\MerchantProductGroupReferenceOutput;
use App\ApiResource\MerchantProductGroupSummaryOutput;
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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<MerchantProductGroupListOutput|MerchantProductGroupOutput>
 */
final readonly class MerchantProductGroupProvider implements ProviderInterface
{
    public function __construct(
        private ShopRepository $shopRepository,
        private ProductGroupRepository $productGroupRepository,
        private MerchantProductRepository $merchantProductRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MerchantProductGroupListOutput|MerchantProductGroupOutput
    {
        $shop = $this->getShop((string) ($uriVariables['storeId'] ?? ''));
        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $groupId = (string) ($uriVariables['groupId'] ?? '');
        if ('' !== $groupId) {
            $group = $this->findVisibleGroup($groupId);

            return $this->toOutput($group, $shop, includeItems: true);
        }

        $items = array_map($this->toListItem(...), $this->findVisibleGroups());

        return new MerchantProductGroupListOutput(
            id: $shop->getId()->toRfc4122(),
            items: $items,
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

    /**
     * @return list<ProductGroup>
     */
    private function findVisibleGroups(): array
    {
        /** @var list<ProductGroup> $groups */
        $groups = $this->productGroupRepository->createQueryBuilder('pg')
            ->andWhere('pg.status = :status')
            ->andWhere('pg.visibility = :visibility')
            ->setParameter('status', ProductGroupStatus::Published)
            ->setParameter('visibility', ProductGroupVisibility::Merchant)
            ->orderBy('pg.sortOrder', 'ASC')
            ->addOrderBy('pg.nameFr', 'ASC')
            ->getQuery()
            ->getResult();

        return $groups;
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
            ->leftJoin('pr.brand', 'b')
            ->addSelect('b')
            ->leftJoin('pr.category', 'c')
            ->addSelect('c')
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

    private function toOutput(ProductGroup $group, Shop $shop, bool $includeItems): MerchantProductGroupOutput
    {
        $items = [];
        if ($includeItems) {
            $items = $group->getSortedItems()
                ->map(fn (ProductGroupItem $item): MerchantProductGroupItemOutput => $this->itemToOutput($item, $shop))
                ->toArray();
        }

        return new MerchantProductGroupOutput(
            id: $group->getId()->toRfc4122(),
            nameFr: $group->getNameFr(),
            nameAr: $group->getNameAr(),
            slug: $group->getSlug(),
            descriptionFr: $group->getDescriptionFr(),
            descriptionAr: $group->getDescriptionAr(),
            marketCountry: $group->getMarketCountry(),
            icon: $group->getIcon(),
            sortOrder: $group->getSortOrder(),
            itemsCount: $group->getItems()->count(),
            items: $items,
        );
    }

    private function toListItem(ProductGroup $group): MerchantProductGroupSummaryOutput
    {
        return new MerchantProductGroupSummaryOutput(
            id: $group->getId()->toRfc4122(),
            nameFr: $group->getNameFr(),
            nameAr: $group->getNameAr(),
            slug: $group->getSlug(),
            descriptionFr: $group->getDescriptionFr(),
            descriptionAr: $group->getDescriptionAr(),
            marketCountry: $group->getMarketCountry(),
            icon: $group->getIcon(),
            sortOrder: $group->getSortOrder(),
            itemsCount: $group->getItems()->count(),
        );
    }

    private function itemToOutput(ProductGroupItem $item, Shop $shop): MerchantProductGroupItemOutput
    {
        $productReference = $item->getProductReference();

        return new MerchantProductGroupItemOutput(
            id: $item->getId()->toRfc4122(),
            sortOrder: $item->getSortOrder(),
            importance: $item->getImportance()->value,
            status: $this->lineStatus($shop, $productReference),
            productReference: $this->referenceToOutput($productReference),
        );
    }

    private function lineStatus(Shop $shop, ProductReference $productReference): string
    {
        if (ProductReferenceStatus::Approved !== $productReference->getStatus()) {
            return 'non_importable';
        }

        if (null !== $this->merchantProductRepository->findOneForShopAndProductReference($shop, $productReference)) {
            return 'already_present';
        }

        return 'new';
    }

    private function referenceToOutput(ProductReference $productReference): MerchantProductGroupReferenceOutput
    {
        return new MerchantProductGroupReferenceOutput(
            id: $productReference->getId()->toRfc4122(),
            nameFr: $productReference->getNameFr(),
            nameAr: $productReference->getNameAr(),
            brandName: $productReference->getBrand()->getCanonicalName(),
            categoryNameFr: $productReference->getCategory()->getNameFr(),
            unit: $productReference->getUnit()->value,
            volume: $productReference->getVolume(),
            status: $productReference->getStatus()->value,
        );
    }
}
