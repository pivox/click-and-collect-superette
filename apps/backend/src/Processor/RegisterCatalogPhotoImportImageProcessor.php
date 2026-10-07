<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\CatalogPhotoImportSessionOutput;
use App\Entity\CatalogPhotoImportImage;
use App\Mapper\CatalogPhotoImportSessionMapper;
use App\Repository\CatalogPhotoImportImageRepository;
use App\Service\CatalogPhotoImportSessionAccessResolver;
use App\Service\CatalogPhotoQuotaExhaustedException;
use App\Service\CatalogPhotoQuotaLedger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Registers one source photo inside a draft session, dedups it by content
 * hash, then atomically reserves a quota credit (CATALOG-AI-001, #638).
 *
 * File validation mirrors MerchantCatalogPhotoImportPreviewProcessor
 * (historical socle, #370) — the bytes themselves are discarded once hashed;
 * real private storage is CATALOG-AI-002 (#639).
 *
 * @implements ProcessorInterface<null, CatalogPhotoImportSessionOutput>
 */
final readonly class RegisterCatalogPhotoImportImageProcessor implements ProcessorInterface
{
    private const array ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const int MAX_PHOTO_BYTES = 5_242_880;

    public function __construct(
        private CatalogPhotoImportSessionAccessResolver $accessResolver,
        private CatalogPhotoImportImageRepository $imageRepository,
        private CatalogPhotoQuotaLedger $quotaLedger,
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        private CatalogPhotoImportSessionMapper $mapper,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CatalogPhotoImportSessionOutput
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        $sessionId = (string) ($uriVariables['sessionId'] ?? '');
        $session = $this->accessResolver->resolveSession($storeId, $sessionId);

        if (!$session->isDraft()) {
            throw new ConflictHttpException('CATALOG_PHOTO_IMPORT_SESSION_NOT_DRAFT');
        }

        $photo = $this->requestStack->getCurrentRequest()?->files->get('photo');
        if (!$photo instanceof UploadedFile) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'PHOTO_IMPORT_PHOTO_REQUIRED');
        }
        if (!\in_array((string) $photo->getClientMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'PHOTO_IMPORT_MIME_INVALID');
        }
        if ($photo->getSize() > self::MAX_PHOTO_BYTES) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'PHOTO_IMPORT_TOO_LARGE');
        }

        $contents = $photo->getContent();
        if ('' === $contents) {
            throw new UnprocessableEntityHttpException('PHOTO_IMPORT_PHOTO_REQUIRED');
        }
        $sourceHash = hash('sha256', $contents);

        $existing = $this->imageRepository->findOneActiveBySessionAndHash($session, $sourceHash);
        if (null !== $existing) {
            // Same bytes already registered and active: retry/double-tap, no new debit.
            return $this->mapper->toOutput($session);
        }

        $image = new CatalogPhotoImportImage($session, $session->getShop(), $sourceHash);
        $this->entityManager->persist($image);
        $this->entityManager->flush();

        try {
            $this->quotaLedger->reserveForImage($image);
        } catch (CatalogPhotoQuotaExhaustedException $exception) {
            $image->remove();
            $this->entityManager->flush();
            throw new UnprocessableEntityHttpException('CATALOG_PHOTO_QUOTA_EXHAUSTED', $exception);
        }

        return $this->mapper->toOutput($session);
    }
}
