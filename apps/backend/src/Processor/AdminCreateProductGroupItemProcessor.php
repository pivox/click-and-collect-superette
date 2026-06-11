<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminProductGroupItemOutput;
use App\Dto\AdminCreateProductGroupItemInput;
use App\Entity\ProductGroupItem;
use App\Enum\ProductGroupItemImportance;
use App\Provider\AdminProductGroupItemProvider;
use App\Repository\AdminProductGroupRepository;
use App\Repository\AdminProductReferenceRepository;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<AdminCreateProductGroupItemInput, AdminProductGroupItemOutput>
 */
final readonly class AdminCreateProductGroupItemProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminProductGroupItemProvider $itemProvider,
        private AdminProductGroupRepository $adminProductGroupRepository,
        private AdminProductReferenceRepository $adminProductReferenceRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminProductGroupItemOutput
    {
        if (!$data instanceof AdminCreateProductGroupItemInput) {
            throw new \InvalidArgumentException('AdminCreateProductGroupItemInput expected.');
        }

        $group = $this->itemProvider->findGroup((string) ($uriVariables['groupId'] ?? ''));
        $productReference = null !== $data->productReferenceId
            ? $this->adminProductReferenceRepository->findOne($data->productReferenceId)
            : null;
        if (null === $productReference) {
            throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_GROUP_PRODUCT_REFERENCE_NOT_FOUND');
        }

        if (null !== $this->adminProductGroupRepository->findItemByProductReference($group, $productReference)) {
            throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_GROUP_ITEM_DUPLICATE');
        }

        $item = (new ProductGroupItem())
            ->setProductReference($productReference)
            ->setSortOrder(null !== $data->sortOrder ? (int) $data->sortOrder : 0)
            ->setImportance(null !== $data->importance ? ProductGroupItemImportance::from($data->importance) : ProductGroupItemImportance::Recommended);
        $group->addItem($item);

        $this->adminProductGroupRepository->save($item);

        return AdminProductGroupItemProvider::itemToOutput($item);
    }
}
