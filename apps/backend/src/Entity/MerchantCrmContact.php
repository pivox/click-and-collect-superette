<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MerchantCrmContactChannel;
use App\Repository\MerchantCrmContactRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MerchantCrmContactRepository::class)]
#[ORM\Table(name: 'merchant_crm_contacts')]
#[ORM\Index(name: 'IDX_MERCHANT_CRM_CONTACTS_MERCHANT', columns: ['merchant_id'])]
#[ORM\Index(name: 'IDX_MERCHANT_CRM_CONTACTS_MERCHANT_CONTACTED', columns: ['merchant_id', 'contacted_at'])]
#[ORM\Index(name: 'IDX_MERCHANT_CRM_CONTACTS_ADMIN', columns: ['created_by_admin_id'])]
class MerchantCrmContact
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $merchant;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $createdByAdmin;

    #[ORM\Column]
    private \DateTimeImmutable $contactedAt;

    #[ORM\Column(length: 16, enumType: MerchantCrmContactChannel::class)]
    private MerchantCrmContactChannel $channel;

    #[ORM\Column(type: 'text')]
    private string $note;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        User $merchant,
        User $createdByAdmin,
        MerchantCrmContactChannel $channel,
        string $note,
        ?\DateTimeImmutable $contactedAt = null,
    ) {
        $this->id = Uuid::v4();
        $this->merchant = $merchant;
        $this->createdByAdmin = $createdByAdmin;
        $this->channel = $channel;
        $this->note = trim($note);
        $this->contactedAt = $contactedAt ?? new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getMerchant(): User
    {
        return $this->merchant;
    }

    public function getCreatedByAdmin(): User
    {
        return $this->createdByAdmin;
    }

    public function getContactedAt(): \DateTimeImmutable
    {
        return $this->contactedAt;
    }

    public function getChannel(): MerchantCrmContactChannel
    {
        return $this->channel;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
