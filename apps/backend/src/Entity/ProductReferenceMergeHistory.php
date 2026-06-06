<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductReferenceMergeHistoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProductReferenceMergeHistoryRepository::class)]
#[ORM\Table(name: 'product_reference_merge_history')]
#[ORM\Index(name: 'IDX_PRODUCT_REFERENCE_MERGE_HISTORY_KEPT', columns: ['kept_product_reference_id'])]
#[ORM\Index(name: 'IDX_PRODUCT_REFERENCE_MERGE_HISTORY_ABSORBED', columns: ['absorbed_product_reference_id'])]
#[ORM\Index(name: 'IDX_PRODUCT_REFERENCE_MERGE_HISTORY_ADMIN', columns: ['merged_by_admin_id'])]
#[ORM\Index(name: 'IDX_PRODUCT_REFERENCE_MERGE_HISTORY_MERGED_AT', columns: ['merged_at'])]
class ProductReferenceMergeHistory
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: ProductReference::class)]
    #[ORM\JoinColumn(name: 'kept_product_reference_id', nullable: false, onDelete: 'RESTRICT')]
    private ProductReference $keptProductReference;

    #[ORM\ManyToOne(targetEntity: ProductReference::class)]
    #[ORM\JoinColumn(name: 'absorbed_product_reference_id', nullable: false, onDelete: 'RESTRICT')]
    private ProductReference $absorbedProductReference;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'merged_by_admin_id', nullable: false, onDelete: 'RESTRICT')]
    private User $mergedByAdmin;

    #[ORM\Column(length: 32)]
    private string $reason;

    #[ORM\Column(type: 'integer')]
    private int $movedOfferCount;

    #[ORM\Column(type: 'integer')]
    private int $removedConflictingOfferCount;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $snapshot;

    #[ORM\Column]
    private \DateTimeImmutable $mergedAt;

    /**
     * @param array<string, mixed> $snapshot
     */
    public function __construct(
        ProductReference $keptProductReference,
        ProductReference $absorbedProductReference,
        User $mergedByAdmin,
        string $reason,
        int $movedOfferCount,
        int $removedConflictingOfferCount,
        array $snapshot,
    ) {
        $this->id = Uuid::v4();
        $this->keptProductReference = $keptProductReference;
        $this->absorbedProductReference = $absorbedProductReference;
        $this->mergedByAdmin = $mergedByAdmin;
        $this->reason = $reason;
        $this->movedOfferCount = $movedOfferCount;
        $this->removedConflictingOfferCount = $removedConflictingOfferCount;
        $this->snapshot = $snapshot;
        $this->mergedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getKeptProductReference(): ProductReference
    {
        return $this->keptProductReference;
    }

    public function getAbsorbedProductReference(): ProductReference
    {
        return $this->absorbedProductReference;
    }

    public function getMergedByAdmin(): User
    {
        return $this->mergedByAdmin;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getMovedOfferCount(): int
    {
        return $this->movedOfferCount;
    }

    public function getRemovedConflictingOfferCount(): int
    {
        return $this->removedConflictingOfferCount;
    }

    /** @return array<string, mixed> */
    public function getSnapshot(): array
    {
        return $this->snapshot;
    }

    public function getMergedAt(): \DateTimeImmutable
    {
        return $this->mergedAt;
    }
}
