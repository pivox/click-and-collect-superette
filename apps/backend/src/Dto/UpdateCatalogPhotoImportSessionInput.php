<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\CatalogPhotoImportSession;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateCatalogPhotoImportSessionInput
{
    public function __construct(
        /** Optimistic-lock guard: must match the session's current `version`. */
        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        public int $version,
        #[Assert\Choice(choices: CatalogPhotoImportSession::ALLOWED_MODES)]
        public ?string $mode = null,
        #[Assert\Length(max: 64)]
        #[SerializedName('campaign_id')]
        public ?string $campaignId = null,
    ) {
    }
}
