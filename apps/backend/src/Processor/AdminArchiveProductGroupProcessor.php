<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminProductGroupOutput;
use App\Enum\ProductGroupStatus;
use App\Provider\AdminProductGroupItemProvider;
use App\Repository\AdminProductGroupRepository;

/**
 * @implements ProcessorInterface<mixed, AdminProductGroupOutput>
 */
final readonly class AdminArchiveProductGroupProcessor implements ProcessorInterface
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
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminProductGroupOutput
    {
        $group = $this->itemProvider->findGroup((string) ($uriVariables['groupId'] ?? ''));
        $group->setStatus(ProductGroupStatus::Archived);

        $this->adminProductGroupRepository->save($group);

        return AdminProductGroupItemProvider::toOutput($group, includeItems: true);
    }
}
