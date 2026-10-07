<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\CatalogPhotoImportSessionOutput;
use App\Dto\UpdateCatalogPhotoImportSessionInput;
use App\Mapper\CatalogPhotoImportSessionMapper;
use App\Service\CatalogPhotoImportSessionAccessResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @implements ProcessorInterface<UpdateCatalogPhotoImportSessionInput, CatalogPhotoImportSessionOutput>
 */
final readonly class UpdateCatalogPhotoImportSessionProcessor implements ProcessorInterface
{
    public function __construct(
        private CatalogPhotoImportSessionAccessResolver $accessResolver,
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
        if (!$data instanceof UpdateCatalogPhotoImportSessionInput) {
            throw new \InvalidArgumentException('UpdateCatalogPhotoImportSessionInput expected.');
        }

        $storeId = (string) ($uriVariables['storeId'] ?? '');
        $sessionId = (string) ($uriVariables['sessionId'] ?? '');
        $session = $this->accessResolver->resolveSession($storeId, $sessionId);

        if (!$session->isDraft()) {
            throw new ConflictHttpException('CATALOG_PHOTO_IMPORT_SESSION_NOT_DRAFT');
        }

        // Explicit staleness check before even attempting the write: gives a
        // clear conflict for the common "another teammate edited it" case.
        // Doctrine's #[ORM\Version] column still protects the rarer true race
        // (two requests reading the same version simultaneously) below.
        if ($data->version !== $session->getVersion()) {
            throw new ConflictHttpException('CATALOG_PHOTO_IMPORT_SESSION_VERSION_CONFLICT');
        }

        $session->setMode($data->mode);
        $session->setCampaignId($data->campaignId);

        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException $exception) {
            throw new ConflictHttpException('CATALOG_PHOTO_IMPORT_SESSION_VERSION_CONFLICT', $exception);
        }

        return $this->mapper->toOutput($session);
    }
}
