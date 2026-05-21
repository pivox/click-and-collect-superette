<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminProductReferenceOutput;
use App\Dto\AdminUpdateProductReferenceInput;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use App\Provider\AdminProductReferenceItemProvider;
use App\Repository\AdminBrandRepository;
use App\Repository\AdminCategoryRepository;
use App\Repository\AdminProductReferenceRepository;
use App\Service\AdminAuditLogger;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<AdminUpdateProductReferenceInput, AdminProductReferenceOutput>
 */
final readonly class AdminUpdateProductReferenceProcessor implements ProcessorInterface
{
    public function __construct(
        private AdminProductReferenceRepository $adminProductReferenceRepository,
        private AdminBrandRepository $adminBrandRepository,
        private AdminCategoryRepository $adminCategoryRepository,
        private RequestStack $requestStack,
        private AdminAuditLogger $auditLogger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminProductReferenceOutput
    {
        if (!$data instanceof AdminUpdateProductReferenceInput) {
            throw new \InvalidArgumentException('AdminUpdateProductReferenceInput expected.');
        }

        $productReferenceId = (string) ($uriVariables['productReferenceId'] ?? '');
        if (!Uuid::isValid($productReferenceId)) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_REFERENCE_NOT_FOUND');
        }

        $productReference = $this->adminProductReferenceRepository->findOne($productReferenceId);
        if (null === $productReference) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_REFERENCE_NOT_FOUND');
        }

        $payload = $this->currentPayload();

        if (\array_key_exists('nameFr', $payload)) {
            if (null === $data->nameFr) {
                throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_REFERENCE_NAME_FR_REQUIRED');
            }
            $nameFr = trim($data->nameFr);
            if ('' === $nameFr) {
                throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_REFERENCE_NAME_FR_BLANK');
            }
            $productReference->setNameFr($nameFr);
        }

        if (\array_key_exists('nameAr', $payload)) {
            $nameAr = null !== $data->nameAr ? trim($data->nameAr) : null;
            $productReference->setNameAr('' === $nameAr ? null : $nameAr);
        }

        if (\array_key_exists('variantFr', $payload)) {
            $variantFr = null !== $data->variantFr ? trim($data->variantFr) : null;
            $productReference->setVariantFr('' === $variantFr ? null : $variantFr);
        }

        if (\array_key_exists('variantAr', $payload)) {
            $variantAr = null !== $data->variantAr ? trim($data->variantAr) : null;
            $productReference->setVariantAr('' === $variantAr ? null : $variantAr);
        }

        if (\array_key_exists('brandId', $payload)) {
            if (null === $data->brandId) {
                throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_REFERENCE_BRAND_REQUIRED');
            }
            $brand = $this->adminBrandRepository->findOne($data->brandId);
            if (null === $brand) {
                throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_REFERENCE_BRAND_NOT_FOUND');
            }
            $productReference->setBrand($brand);
        }

        if (\array_key_exists('categoryId', $payload)) {
            if (null === $data->categoryId) {
                throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_REFERENCE_CATEGORY_REQUIRED');
            }
            $category = $this->adminCategoryRepository->findOne($data->categoryId);
            if (null === $category) {
                throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_REFERENCE_CATEGORY_NOT_FOUND');
            }
            $productReference->setCategory($category);
        }

        if (\array_key_exists('unit', $payload)) {
            if (null === $data->unit) {
                throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_REFERENCE_UNIT_REQUIRED');
            }
            $productReference->setUnit(ProductUnit::from($data->unit));
        }

        if (\array_key_exists('volume', $payload)) {
            $productReference->setVolume($data->volume);
        }

        if (\array_key_exists('barcode', $payload)) {
            $barcode = null !== $data->barcode && '' !== trim($data->barcode) ? trim($data->barcode) : null;
            if (null !== $barcode) {
                $existing = $this->adminProductReferenceRepository->findOneByBarcode($barcode);
                if (null !== $existing && !$existing->getId()->equals($productReference->getId())) {
                    throw new UnprocessableEntityHttpException('ADMIN_PRODUCT_REFERENCE_BARCODE_DUPLICATE');
                }
            }
            $productReference->setBarcode($barcode);
        }

        if (\array_key_exists('aliases', $payload)) {
            $productReference->setAliases($data->aliases ?? []);
        }

        if (\array_key_exists('country', $payload)) {
            $country = null !== $data->country ? trim($data->country) : null;
            $productReference->setCountry('' === $country || null === $country ? 'TN' : $country);
        }

        if (\array_key_exists('status', $payload) && null !== $data->status) {
            $productReference->setStatus(ProductReferenceStatus::from($data->status));
        }

        $this->auditLogger->log(
            action: 'product_reference.update',
            resourceType: 'product_reference',
            resourceId: $productReference->getId()->toRfc4122(),
            summary: \sprintf('Produit référentiel "%s" modifié.', $productReference->getNameFr()),
            metadata: ['name_fr' => $productReference->getNameFr()],
        );
        $this->adminProductReferenceRepository->save($productReference);

        return AdminProductReferenceItemProvider::toOutput($productReference);
    }

    /**
     * @return array<string, mixed>
     */
    private function currentPayload(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || '' === $request->getContent()) {
            return [];
        }

        $payload = json_decode($request->getContent(), true);

        return \is_array($payload) ? $payload : [];
    }
}
