<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\KadhiaStatus;
use App\Repository\KadhiaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: KadhiaRepository::class)]
#[ORM\Table(name: 'kadhias')]
#[ORM\Index(name: 'IDX_KADHIAS_CUSTOMER', columns: ['customer_id'])]
#[ORM\Index(name: 'IDX_KADHIAS_SHOP', columns: ['shop_id'])]
#[ORM\Index(name: 'IDX_KADHIAS_STATUS', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class Kadhia
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $customer;

    #[ORM\ManyToOne(targetEntity: Shop::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Shop $shop;

    #[ORM\Column(type: 'string', length: 16, enumType: KadhiaStatus::class)]
    private KadhiaStatus $status = KadhiaStatus::Draft;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, KadhiaLine> */
    #[ORM\OneToMany(targetEntity: KadhiaLine::class, mappedBy: 'kadhia', cascade: ['persist', 'remove'])]
    private Collection $lines;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->lines = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCustomer(): User
    {
        return $this->customer;
    }

    public function setCustomer(User $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getShop(): Shop
    {
        return $this->shop;
    }

    public function setShop(Shop $shop): static
    {
        $this->shop = $shop;

        return $this;
    }

    public function getStatus(): KadhiaStatus
    {
        return $this->status;
    }

    public function setStatus(KadhiaStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    /** @return Collection<int, KadhiaLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(KadhiaLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setKadhia($this);
        }

        return $this;
    }

    public function removeLine(KadhiaLine $line): static
    {
        $this->lines->removeElement($line);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
