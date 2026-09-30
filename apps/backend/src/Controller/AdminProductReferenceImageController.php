<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\AdminProductImageProvenanceInput;
use App\Entity\ProductReference;
use App\Enum\ProductImageLicenseCode;
use App\Exception\InvalidProductImageException;
use App\Repository\AdminProductReferenceRepository;
use App\Repository\ProductImageRepository;
use App\Service\AdminAuditLogger;
use App\Service\ProductImage\ProductImageApplicationService;
use App\Service\ProductImage\ProductImageStoreCommand;
use App\Service\ProductImage\ProductImageUrlBuilder;
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
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Admin endpoints to manage the official picture of a referential product.
 *
 * Routes live under /api/admin, already restricted to ROLE_ADMIN by security.yaml;
 * the IsGranted attribute documents and reinforces that requirement.
 *
 * The controller only handles transport (request parsing, access, HTTP mapping):
 * all image processing happens in the shared ProductImageApplicationService so the
 * exact same pipeline can be reused by the AI enrichment flow.
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminProductReferenceImageController extends AbstractController
{
    public function __construct(
        private readonly AdminProductReferenceRepository $productReferenceRepository,
        private readonly ProductImageRepository $productImageRepository,
        private readonly ProductImageApplicationService $imageApplicationService,
        private readonly ProductImageUrlBuilder $urlBuilder,
        private readonly AdminAuditLogger $auditLogger,
        private readonly ValidatorInterface $validator,
        #[Autowire(service: 'monolog.logger.admin')]
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'app.product_image.max_upload_bytes')]
        private readonly int $maxUploadBytes,
    ) {
    }

    #[Route(
        '/api/admin/product-references/{id}/image',
        name: 'admin_product_reference_image_upload',
        methods: ['POST'],
    )]
    public function upload(string $id, Request $request): JsonResponse
    {
        $productReference = $this->requireProductReference($id);

        $file = $request->files->get('image');
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
        $altText = \is_string($altText) ? $altText : null;

        $provenance = $this->parseProvenance($request);

        try {
            $image = $this->imageApplicationService->store(
                ProductImageStoreCommand::adminUpload(
                    $productReference,
                    $contents,
                    $altText,
                    // No license_code field sent → platform_owned default resolved by the
                    // pipeline (an admin upload is assumed internal/authorized, #584).
                    licenseCode: null !== $provenance->licenseCode ? ProductImageLicenseCode::from($provenance->licenseCode) : null,
                    sourceName: $provenance->sourceName,
                    sourceUrl: $provenance->sourceUrl,
                    attributionText: $provenance->attributionText,
                    permissionReference: $provenance->permissionReference,
                    capturedAt: $this->parseCapturedAt($provenance->capturedAt),
                ),
            );
        } catch (InvalidProductImageException $exception) {
            $this->logger->warning('admin.product_reference.image_rejected', [
                'product_reference_id' => $productReference->getId()->toRfc4122(),
                'error_code' => $exception->errorCode(),
            ]);
            throw new UnprocessableEntityHttpException($exception->errorCode(), $exception);
        }

        $this->auditLogger->log(
            action: 'product_reference.image.upload',
            resourceType: 'product_reference',
            resourceId: $productReference->getId()->toRfc4122(),
            summary: \sprintf('Image officielle mise à jour pour "%s".', $productReference->getNameFr()),
            metadata: [
                'product_image_id' => $image->getId()->toRfc4122(),
                'mime_type' => $image->getMimeType(),
                'license_code' => $image->getLicenseCode()->value,
            ],
        );

        return new JsonResponse(
            ['image' => $this->urlBuilder->buildPayload($image)],
            Response::HTTP_CREATED,
        );
    }

    #[Route(
        '/api/admin/product-references/{id}/image',
        name: 'admin_product_reference_image_delete',
        methods: ['DELETE'],
    )]
    public function delete(string $id): Response
    {
        $productReference = $this->requireProductReference($id);

        $image = $this->productImageRepository->findOfficialForProductReference($productReference);
        if (null === $image) {
            throw new NotFoundHttpException('PRODUCT_IMAGE_NOT_FOUND');
        }

        $imageId = $image->getId()->toRfc4122();
        $this->imageApplicationService->delete($image);

        $this->auditLogger->log(
            action: 'product_reference.image.delete',
            resourceType: 'product_reference',
            resourceId: $productReference->getId()->toRfc4122(),
            summary: \sprintf('Image officielle supprimée pour "%s".', $productReference->getNameFr()),
            metadata: ['product_image_id' => $imageId],
        );

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Optional provenance form fields of the upload (PRODUCT-IMAGE-004), validated
     * through the same DTO/constraints as the PATCH provenance endpoint.
     */
    private function parseProvenance(Request $request): AdminProductImageProvenanceInput
    {
        $input = new AdminProductImageProvenanceInput();
        $input->licenseCode = $this->stringParam($request, 'license_code');
        $input->sourceName = $this->stringParam($request, 'source_name');
        $input->sourceUrl = $this->stringParam($request, 'source_url');
        $input->attributionText = $this->stringParam($request, 'attribution_text');
        $input->permissionReference = $this->stringParam($request, 'permission_reference');
        $input->capturedAt = $this->stringParam($request, 'captured_at');

        $violations = $this->validator->validate($input);
        if (\count($violations) > 0) {
            throw new UnprocessableEntityHttpException('PRODUCT_IMAGE_INVALID_PROVENANCE');
        }

        return $input;
    }

    private function stringParam(Request $request, string $key): ?string
    {
        $value = $request->request->get($key);
        if (!\is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function parseCapturedAt(?string $raw): ?\DateTimeImmutable
    {
        if (null === $raw) {
            return null;
        }

        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception) {
            throw new UnprocessableEntityHttpException('PRODUCT_IMAGE_INVALID_CAPTURED_AT');
        }
    }

    private function requireProductReference(string $id): ProductReference
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_REFERENCE_NOT_FOUND');
        }

        $productReference = $this->productReferenceRepository->findOne($id);
        if (null === $productReference) {
            throw new NotFoundHttpException('ADMIN_PRODUCT_REFERENCE_NOT_FOUND');
        }

        return $productReference;
    }
}
