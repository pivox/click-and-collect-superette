<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Repository\AdminBrandRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Logical delete if the brand is linked to product references, physical delete otherwise.
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
final readonly class AdminDeleteBrandProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminBrandRepository $adminBrandRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $brandId = (string) ($uriVariables['brandId'] ?? '');
        if (!Uuid::isValid($brandId)) {
            throw new NotFoundHttpException('ADMIN_BRAND_NOT_FOUND');
        }

        $brand = $this->adminBrandRepository->findOne($brandId);
        if (null === $brand) {
            throw new NotFoundHttpException('ADMIN_BRAND_NOT_FOUND');
        }

        if ($this->adminBrandRepository->hasLinkedEntities($brand)) {
            $brand->setActive(false);
            $this->adminBrandRepository->save($brand);
        } else {
            $this->adminBrandRepository->remove($brand);
        }

        return null;
    }
}
