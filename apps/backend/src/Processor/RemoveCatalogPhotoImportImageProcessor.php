<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Repository\CatalogPhotoImportImageRepository;
use App\Service\CatalogPhotoImportSessionAccessResolver;
use App\Service\CatalogPhotoQuotaLedger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Removing an image releases its reservation (if any) — but never recredits
 * one that was already consumed (CATALOG-AI-001, issue #638).
 *
 * @implements ProcessorInterface<null, void>
 */
final readonly class RemoveCatalogPhotoImportImageProcessor implements ProcessorInterface
{
    public function __construct(
        private CatalogPhotoImportSessionAccessResolver $accessResolver,
        private CatalogPhotoImportImageRepository $imageRepository,
        private CatalogPhotoQuotaLedger $quotaLedger,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $storeId = (string) ($uriVariables['storeId'] ?? '');
        $sessionId = (string) ($uriVariables['sessionId'] ?? '');
        $session = $this->accessResolver->resolveSession($storeId, $sessionId);

        if (!$session->isDraft()) {
            throw new ConflictHttpException('CATALOG_PHOTO_IMPORT_SESSION_NOT_DRAFT');
        }

        $imageId = (string) ($uriVariables['imageId'] ?? '');
        if (!Uuid::isValid($imageId)) {
            throw new NotFoundHttpException('CATALOG_PHOTO_IMPORT_IMAGE_NOT_FOUND');
        }

        $image = $this->imageRepository->find($imageId);
        if (null === $image || !$image->getSession()->getId()->equals($session->getId()) || !$image->isActive()) {
            throw new NotFoundHttpException('CATALOG_PHOTO_IMPORT_IMAGE_NOT_FOUND');
        }

        $this->quotaLedger->releaseForImage($image);
        $image->remove();
        $this->entityManager->flush();
    }
}
