<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CatalogPhotoImportImage;
use App\Entity\CatalogPhotoImportSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CatalogPhotoImportImage>
 */
class CatalogPhotoImportImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CatalogPhotoImportImage::class);
    }

    /**
     * Only an active row counts as a duplicate — a removed one never blocks
     * re-adding the same photo later (see entity docblock).
     */
    public function findOneActiveBySessionAndHash(CatalogPhotoImportSession $session, string $sourceHash): ?CatalogPhotoImportImage
    {
        return $this->findOneBy([
            'session' => $session,
            'sourceHash' => $sourceHash,
            'status' => CatalogPhotoImportImage::STATUS_ACTIVE,
        ]);
    }
}
