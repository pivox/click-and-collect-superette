<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminProductGroupItemOutput;
use App\ApiResource\AdminProductGroupOutput;
use App\ApiResource\AdminProductGroupReferenceOutput;
use App\Entity\ProductGroup;
use App\Entity\ProductGroupItem;
use App\Entity\ProductReference;
use App\Repository\AdminProductGroupRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminProductGroupOutput>
 */
final readonly class AdminProductGroupItemProvider implements ProviderInterface
{
    public function __construct(
        private AdminProductGroupRepository $adminProductGroupRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminProductGroupOutput
    {
        $group = $this->findGroup((string) ($uriVariables['groupId'] ?? ''));

        return self::toOutput($group, includeItems: true);
    }

    public function findGroup(string $groupId): ProductGroup
    {
        if (!Uuid::isValid($groupId)) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_GROUP_NOT_FOUND');
        }

        $group = $this->adminProductGroupRepository->findOne($groupId);
        if (null === $group) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_GROUP_NOT_FOUND');
        }

        return $group;
    }

    public static function toOutput(ProductGroup $group, bool $includeItems): AdminProductGroupOutput
    {
        $items = [];
        if ($includeItems) {
            $items = $group->getSortedItems()
                ->map(static fn (ProductGroupItem $item): AdminProductGroupItemOutput => self::itemToOutput($item))
                ->toArray();
        }

        return new AdminProductGroupOutput(
            id: $group->getId()->toRfc4122(),
            nameFr: $group->getNameFr(),
            nameAr: $group->getNameAr(),
            slug: $group->getSlug(),
            descriptionFr: $group->getDescriptionFr(),
            descriptionAr: $group->getDescriptionAr(),
            marketCountry: $group->getMarketCountry(),
            status: $group->getStatus()->value,
            visibility: $group->getVisibility()->value,
            icon: $group->getIcon(),
            sortOrder: $group->getSortOrder(),
            itemsCount: $group->getItems()->count(),
            createdAt: $group->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $group->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            items: $items,
        );
    }

    public static function itemToOutput(ProductGroupItem $item): AdminProductGroupItemOutput
    {
        return new AdminProductGroupItemOutput(
            id: $item->getId()->toRfc4122(),
            sortOrder: $item->getSortOrder(),
            importance: $item->getImportance()->value,
            productReference: self::referenceToOutput($item->getProductReference()),
            createdAt: $item->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $item->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    private static function referenceToOutput(ProductReference $productReference): AdminProductGroupReferenceOutput
    {
        return new AdminProductGroupReferenceOutput(
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
