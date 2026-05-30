<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminProductReferenceOutput;
use App\Dto\AdminCreateProductReferenceInput;
use App\Entity\ProductReference;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use App\Provider\AdminProductReferenceItemProvider;
use App\Repository\AdminBrandRepository;
use App\Repository\AdminCategoryRepository;
use App\Repository\AdminProductReferenceRepository;
use App\Service\AdminAuditLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<AdminCreateProductReferenceInput, AdminProductReferenceOutput>
 */
final readonly class AdminCreateProductReferenceProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminProductReferenceRepository $adminProductReferenceRepository,
        private AdminBrandRepository $adminBrandRepository,
        private AdminCategoryRepository $adminCategoryRepository,
        private AdminAuditLogger $auditLogger,
        #[Autowire(service: 'monolog.logger.admin')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminProductReferenceOutput
    {
        if (!$data instanceof AdminCreateProductReferenceInput) {
            throw new \InvalidArgumentException('AdminCreateProductReferenceInput expected.');
        }

        $brand = $this->adminBrandRepository->findOne((string) $data->brandId);
        if (null === $brand) {
            throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_REFERENCE_BRAND_NOT_FOUND');
        }

        $category = $this->adminCategoryRepository->findOne((string) $data->categoryId);
        if (null === $category) {
            throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_REFERENCE_CATEGORY_NOT_FOUND');
        }

        $unit = ProductUnit::from((string) $data->unit);

        $barcode = null !== $data->barcode && '' !== trim($data->barcode) ? trim($data->barcode) : null;
        if (null !== $barcode && null !== $this->adminProductReferenceRepository->findOneByBarcode($barcode)) {
            $this->logger->warning('admin.product_reference.barcode_duplicate', ['barcode_suffix' => substr($barcode, -4)]);
            throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_REFERENCE_BARCODE_DUPLICATE');
        }

        $status = null !== $data->status ? ProductReferenceStatus::from($data->status) : ProductReferenceStatus::Draft;

        $productReference = (new ProductReference())
            ->setNameFr(trim((string) $data->nameFr))
            ->setNameAr(null !== $data->nameAr && '' !== trim($data->nameAr) ? trim($data->nameAr) : null)
            ->setVariantFr(null !== $data->variantFr && '' !== trim($data->variantFr) ? trim($data->variantFr) : null)
            ->setVariantAr(null !== $data->variantAr && '' !== trim($data->variantAr) ? trim($data->variantAr) : null)
            ->setBrand($brand)
            ->setCategory($category)
            ->setUnit($unit)
            ->setVolume($data->volume)
            ->setBarcode($barcode)
            ->setAliases($data->aliases ?? [])
            ->setCountry(null !== $data->country && '' !== trim($data->country) ? trim($data->country) : 'TN')
            ->setStatus($status);

        try {
            $this->auditLogger->log(
                action: 'product_reference.create',
                resourceType: 'product_reference',
                resourceId: $productReference->getId()->toRfc4122(),
                summary: \sprintf('Produit référentiel "%s" créé.', $productReference->getNameFr()),
                metadata: ['name_fr' => $productReference->getNameFr()],
            );
            $this->adminProductReferenceRepository->save($productReference);

            $this->logger->info('admin.product_reference.created', [
                'product_reference_id' => $productReference->getId()->toRfc4122(),
                'status' => $status->value,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('admin.product_reference.create_failed', [
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return AdminProductReferenceItemProvider::toOutput($productReference);
    }
}
