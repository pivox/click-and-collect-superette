<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AdminBrandOutput;
use App\Entity\Brand;
use App\Repository\AdminBrandRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<AdminBrandOutput>
 */
final readonly class AdminBrandItemProvider implements ProviderInterface
{
    public function __construct(
        private AdminBrandRepository $adminBrandRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminBrandOutput
    {
        $brandId = (string) ($uriVariables['brandId'] ?? '');
        if (!Uuid::isValid($brandId)) {
            throw new NotFoundHttpException('ADMIN_BRAND_NOT_FOUND');
        }

        $brand = $this->adminBrandRepository->findOne($brandId);
        if (null === $brand) {
            throw new NotFoundHttpException('ADMIN_BRAND_NOT_FOUND');
        }

        return self::toOutput($brand);
    }

    public static function toOutput(Brand $brand): AdminBrandOutput
    {
        return new AdminBrandOutput(
            id: $brand->getId()->toRfc4122(),
            canonicalName: $brand->getCanonicalName(),
            slug: $brand->getSlug(),
            aliases: $brand->getAliases(),
            country: $brand->getCountry(),
            isActive: $brand->isActive(),
            createdAt: $brand->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $brand->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
