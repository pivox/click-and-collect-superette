<?php

declare(strict_types=1);

namespace App\Mapper;

use App\ApiResource\CatalogPhotoImportSessionImageOutput;
use App\ApiResource\CatalogPhotoImportSessionOutput;
use App\Entity\CatalogPhotoImportImage;
use App\Entity\CatalogPhotoImportSession;

final readonly class CatalogPhotoImportSessionMapper
{
    public function toOutput(CatalogPhotoImportSession $session): CatalogPhotoImportSessionOutput
    {
        return new CatalogPhotoImportSessionOutput(
            id: $session->getId()->toRfc4122(),
            storeId: $session->getShop()->getId()->toRfc4122(),
            status: $session->getStatus()->value,
            mode: $session->getMode(),
            campaignId: $session->getCampaignId(),
            configurationVersion: $session->getConfigurationVersion(),
            version: $session->getVersion(),
            activeImageCount: $session->getActiveImageCount(),
            images: array_values(array_map(
                fn (CatalogPhotoImportImage $image): CatalogPhotoImportSessionImageOutput => $this->toImageOutput($image),
                $session->getImages()->toArray(),
            )),
            createdAt: $session->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $session->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            cancelledAt: $session->getCancelledAt()?->format(\DateTimeInterface::ATOM),
        );
    }

    private function toImageOutput(CatalogPhotoImportImage $image): CatalogPhotoImportSessionImageOutput
    {
        return new CatalogPhotoImportSessionImageOutput(
            id: $image->getId()->toRfc4122(),
            status: $image->getStatus(),
            createdAt: $image->getCreatedAt()->format(\DateTimeInterface::ATOM),
            removedAt: $image->getRemovedAt()?->format(\DateTimeInterface::ATOM),
        );
    }
}
