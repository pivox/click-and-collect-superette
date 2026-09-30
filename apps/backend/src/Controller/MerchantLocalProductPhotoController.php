<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MerchantLocalProduct;
use App\Entity\Shop;
use App\Enum\ProductImageStatus;
use App\Exception\InvalidProductImageException;
use App\Repository\ProductImageRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use App\Service\ProductImage\ProductImageApplicationService;
use App\Service\ProductImage\ProductImageStoreCommand;
use App\Service\ProductImage\ProductImageUrlBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Merchant photo contribution for local/vrac/reconditioned products
 * (PRODUCT-IMAGE-003, issue #583).
 *
 * The controller only handles transport (request parsing, access, HTTP mapping):
 * the image processing goes through the exact same shared pipeline as the admin
 * upload (ProductImageApplicationService), with two merchant-specific twists:
 * - the stored original is re-encoded through GD so EXIF/GPS metadata from the
 *   merchant's phone never reaches the public uploads directory;
 * - the minimum accepted dimension is lower (320x320) than the referential one.
 *
 * Semantics (lead decision): the photo is stored as source merchant_contribution,
 * status candidate, license merchant_authorized — it is the shop's own picture,
 * exposed only inside its shop catalog and never the official referential image.
 */
#[IsGranted('ROLE_MERCHANT')]
final class MerchantLocalProductPhotoController extends AbstractController
{
    public function __construct(
        private readonly ShopRepository $shopRepository,
        private readonly ProductImageRepository $productImageRepository,
        private readonly ProductImageApplicationService $imageApplicationService,
        private readonly ProductImageUrlBuilder $urlBuilder,
        private readonly MerchantShopAccessChecker $merchantShopAccessChecker,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'app.product_image.max_upload_bytes')]
        private readonly int $maxUploadBytes,
        #[Autowire(param: 'app.product_image.merchant_min_dimension')]
        private readonly int $merchantMinDimension,
    ) {
    }

    #[Route(
        '/api/merchant/stores/{storeId}/local-products/{localProductId}/photo',
        name: 'merchant_local_product_photo_upload',
        methods: ['POST'],
    )]
    public function upload(string $storeId, string $localProductId, Request $request): JsonResponse
    {
        $shop = $this->requireOwnedShop($storeId);
        $localProduct = $this->requireLocalProduct($shop, $localProductId);

        $file = $request->files->get('photo') ?? $request->files->get('image');
        if (!$file instanceof UploadedFile) {
            throw new BadRequestHttpException(InvalidProductImageException::ERROR_FILE_REQUIRED);
        }

        if (!$file->isValid()) {
            // Covers PHP upload errors, including INI_SIZE (file larger than php.ini limit).
            if (\UPLOAD_ERR_INI_SIZE === $file->getError() || \UPLOAD_ERR_FORM_SIZE === $file->getError()) {
                throw new UnprocessableEntityHttpException(InvalidProductImageException::ERROR_TOO_LARGE);
            }
            throw new BadRequestHttpException(InvalidProductImageException::ERROR_FILE_REQUIRED);
        }

        $size = $file->getSize();
        if (false !== $size && $size > $this->maxUploadBytes) {
            throw new UnprocessableEntityHttpException(InvalidProductImageException::ERROR_TOO_LARGE);
        }

        $contents = @file_get_contents($file->getPathname());
        if (false === $contents || '' === $contents) {
            throw new UnprocessableEntityHttpException(InvalidProductImageException::ERROR_UNREADABLE);
        }

        $altText = $request->request->get('alt');
        $altText = \is_string($altText) && '' !== trim($altText) ? $altText : $localProduct->getNameFr();

        try {
            $image = $this->imageApplicationService->store(
                ProductImageStoreCommand::merchantPhoto(
                    $localProduct,
                    $contents,
                    altText: $altText,
                    // Acceptance criterion #583: the merchant origin and dates are kept.
                    sourceName: $shop->getName(),
                    capturedAt: new \DateTimeImmutable(),
                    minDimension: $this->merchantMinDimension,
                ),
            );
        } catch (InvalidProductImageException $exception) {
            $errorCode = $exception->errorCode();
            $this->logger->warning('merchant.local_product.photo_rejected', [
                'shop_id' => $shop->getId()->toRfc4122(),
                'merchant_local_product_id' => $localProduct->getId()->toRfc4122(),
                'error_code' => $errorCode,
            ]);
            if (InvalidProductImageException::ERROR_TOO_SMALL === $errorCode) {
                throw new UnprocessableEntityHttpException('MERCHANT_PHOTO_TOO_SMALL', $exception);
            }
            throw new UnprocessableEntityHttpException($errorCode, $exception);
        }

        return new JsonResponse(
            ['image' => $this->urlBuilder->buildPayload($image)],
            Response::HTTP_CREATED,
        );
    }

    #[Route(
        '/api/merchant/stores/{storeId}/local-products/{localProductId}/photo',
        name: 'merchant_local_product_photo_delete',
        methods: ['DELETE'],
    )]
    public function delete(string $storeId, string $localProductId): Response
    {
        $shop = $this->requireOwnedShop($storeId);
        $localProduct = $this->requireLocalProduct($shop, $localProductId);

        $image = $this->productImageRepository->findCurrentForMerchantLocalProduct($localProduct);
        if (null === $image) {
            throw new NotFoundHttpException('PRODUCT_IMAGE_NOT_FOUND');
        }

        // No physical deletion (#584 stance): the photo is archived so the
        // provenance registry keeps its trail; it leaves the catalogs immediately.
        $image->setStatus(ProductImageStatus::Archived);
        $this->entityManager->flush();

        $this->logger->info('merchant.local_product.photo_archived', [
            'shop_id' => $shop->getId()->toRfc4122(),
            'merchant_local_product_id' => $localProduct->getId()->toRfc4122(),
            'product_image_id' => $image->getId()->toRfc4122(),
        ]);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function requireOwnedShop(string $storeId): Shop
    {
        if (!Uuid::isValid($storeId)) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $shop = $this->shopRepository->find($storeId);
        if (null === $shop) {
            throw new NotFoundHttpException('STORE_NOT_FOUND');
        }

        $this->merchantShopAccessChecker->denyUnlessMerchantOwnsShop($shop);

        return $shop;
    }

    private function requireLocalProduct(Shop $shop, string $localProductId): MerchantLocalProduct
    {
        if (!Uuid::isValid($localProductId)) {
            throw new NotFoundHttpException('MERCHANT_LOCAL_PRODUCT_NOT_FOUND');
        }

        // Scoped by shop: a local product of another supérette yields the same 404.
        $localProduct = $this->entityManager->getRepository(MerchantLocalProduct::class)->findOneBy([
            'id' => Uuid::fromString($localProductId),
            'shop' => $shop,
        ]);
        if (!$localProduct instanceof MerchantLocalProduct) {
            throw new NotFoundHttpException('MERCHANT_LOCAL_PRODUCT_NOT_FOUND');
        }

        return $localProduct;
    }
}
