<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CustomerProductFavoriteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CustomerProductFavoriteRepository::class)]
#[ORM\Table(name: 'customer_product_favorites')]
#[ORM\UniqueConstraint(name: 'UNIQ_CUSTOMER_PRODUCT_FAVORITES_CUSTOMER_PRODUCT', columns: ['customer_id', 'merchant_product_id'])]
#[ORM\Index(name: 'IDX_CUSTOMER_PRODUCT_FAVORITES_CUSTOMER', columns: ['customer_id'])]
#[ORM\Index(name: 'IDX_CUSTOMER_PRODUCT_FAVORITES_PRODUCT', columns: ['merchant_product_id'])]
class CustomerProductFavorite
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $customer;

    #[ORM\ManyToOne(targetEntity: MerchantProduct::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MerchantProduct $merchantProduct;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getMerchantProduct(): MerchantProduct
    {
        return $this->merchantProduct;
    }

    public function setMerchantProduct(MerchantProduct $merchantProduct): static
    {
        $this->merchantProduct = $merchantProduct;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
