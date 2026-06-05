<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantCatalogPhotoImportPreviewItemOutput;
use App\ApiResource\MerchantCatalogPhotoImportPreviewOutput;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\MerchantCatalogPhotoImportPreviewer;
use App\Service\MerchantCatalogPhotoImportUnavailableException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<null, MerchantCatalogPhotoImportPreviewOutput>
 */
final readonly class MerchantCatalogPhotoImportPreviewProcessor implements ProcessorInterface
{
    private const array ALLOWED_SOURCE_TYPES = ['receipt', 'shelf', 'cash_register_export', 'paper_list'];
    private const array ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const int MAX_PHOTO_BYTES = 5_242_880;

    public function __construct(
        private ShopRepository $shopRepository,
        private MerchantShopAccessChecker $merchantShopAccessChecker,
        private MerchantCatalogPhotoImportPreviewer $previewer,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantCatalogPhotoImportPreviewOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        $request = $this->requestStack->getCurrentRequest();
        $sourceType = trim((string) ($request?->request->get('source_type') ?? 'receipt'));
        if (!\in_array($sourceType, self::ALLOWED_SOURCE_TYPES, true)) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'PHOTO_IMPORT_SOURCE_TYPE_INVALID');
        }

        $photo = $request?->files->get('photo');
        if (!$photo instanceof UploadedFile) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'PHOTO_IMPORT_PHOTO_REQUIRED');
        }
        if (!\in_array((string) $photo->getClientMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'PHOTO_IMPORT_MIME_INVALID');
        }
        if ($photo->getSize() > self::MAX_PHOTO_BYTES) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'PHOTO_IMPORT_TOO_LARGE');
        }

        try {
            $result = $this->previewer->preview($shop, $photo, $sourceType);
        } catch (MerchantCatalogPhotoImportUnavailableException $exception) {
            throw new HttpException(Response::HTTP_SERVICE_UNAVAILABLE, $exception->getMessage(), $exception);
        }

        return new MerchantCatalogPhotoImportPreviewOutput(
            id: $result->shopId,
            sourceType: $result->sourceType,
            detectedCount: $result->detectedCount,
            matchedReferenceCount: $result->matchedReferenceCount,
            localCandidateCount: $result->localCandidateCount,
            items: array_map(
                static fn ($item): MerchantCatalogPhotoImportPreviewItemOutput => new MerchantCatalogPhotoImportPreviewItemOutput(
                    line: $item->line,
                    status: $item->status,
                    productReferenceId: $item->productReferenceId,
                    nameFr: $item->nameFr,
                    brand: $item->brand,
                    volume: $item->volume,
                    unit: $item->unit,
                    barcode: $item->barcode,
                    suggestedPriceTnd: $item->suggestedPriceTnd,
                    confidence: $item->confidence,
                    alreadyInCatalog: $item->alreadyInCatalog,
                ),
                $result->items,
            ),
        );
    }
}
