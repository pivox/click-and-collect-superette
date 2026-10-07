<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\CatalogPhotoImportSession;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCatalogPhotoImportSessionInput
{
    public function __construct(
        #[Assert\Choice(choices: CatalogPhotoImportSession::ALLOWED_MODES)]
        public ?string $mode = null,
        #[Assert\Length(max: 64)]
        #[SerializedName('campaign_id')]
        public ?string $campaignId = null,
    ) {
    }
}
