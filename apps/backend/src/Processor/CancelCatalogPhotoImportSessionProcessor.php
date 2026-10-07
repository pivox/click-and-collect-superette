<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\CatalogPhotoImportSessionOutput;
use App\Entity\CatalogPhotoImportImage;
use App\Mapper\CatalogPhotoImportSessionMapper;
use App\Service\CatalogPhotoImportSessionAccessResolver;
use App\Service\CatalogPhotoQuotaLedger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @implements ProcessorInterface<null, CatalogPhotoImportSessionOutput>
 */
final readonly class CancelCatalogPhotoImportSessionProcessor implements ProcessorInterface
{
    public function __construct(
        private CatalogPhotoImportSessionAccessResolver $accessResolver,
        private CatalogPhotoQuotaLedger $quotaLedger,
        private EntityManagerInterface $entityManager,
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

        // No external AI call exists yet in this issue's scope, so every
        // still-active image's reservation is simply released — never a
        // "conserve intermediate state and reconcile" situation (#641).
        foreach ($session->getImages() as $image) {
            if ($image instanceof CatalogPhotoImportImage && $image->isActive()) {
                $this->quotaLedger->releaseForImage($image);
                $image->remove();
            }
        }

        $session->cancel();
        $this->entityManager->flush();

        return $this->mapper->toOutput($session);
    }
}
