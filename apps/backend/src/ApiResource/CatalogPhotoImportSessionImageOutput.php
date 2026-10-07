<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Nested image summary inside CatalogPhotoImportSessionOutput
 * (CATALOG-AI-001, issue #638). `source_hash` is an internal dedup key, not
 * exposed to the client.
 */
final readonly class CatalogPhotoImportSessionImageOutput
{
    public function __construct(
        #[Groups(['catalog_photo_import_session:read'])]
        public string $id,
        #[Groups(['catalog_photo_import_session:read'])]
        public string $status,
        #[Groups(['catalog_photo_import_session:read'])]
        #[SerializedName('created_at')]
        public string $createdAt,
        #[Groups(['catalog_photo_import_session:read'])]
        #[SerializedName('removed_at')]
        public ?string $removedAt,
    ) {
    }
}
