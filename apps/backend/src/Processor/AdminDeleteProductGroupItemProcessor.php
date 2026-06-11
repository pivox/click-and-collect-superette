<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Provider\AdminProductGroupItemProvider;
use App\Repository\AdminProductGroupRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class AdminDeleteProductGroupItemProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminProductGroupItemProvider $itemProvider,
        private AdminProductGroupRepository $adminProductGroupRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $group = $this->itemProvider->findGroup((string) ($uriVariables['groupId'] ?? ''));
        $itemId = (string) ($uriVariables['itemId'] ?? '');
        if (!Uuid::isValid($itemId)) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_GROUP_NOT_FOUND');
        }

        $item = $this->adminProductGroupRepository->findItem($group, $itemId);
        if (null === $item) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_GROUP_NOT_FOUND');
        }

        $this->adminProductGroupRepository->remove($item);
    }
}
