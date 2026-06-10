<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductPackItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductPackItemRepository::class)]
#[ORM\Table(name: 'product_pack_items')]
#[ORM\UniqueConstraint(name: 'UNIQ_PRODUCT_PACK_ITEMS_PACK_PRODUCT', columns: ['pack_id', 'merchant_product_id'])]
#[ORM\Index(name: 'IDX_PRODUCT_PACK_ITEMS_PACK', columns: ['pack_id'])]
class ProductPackItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: ProductPack::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ProductPack $pack;

    #[ORM\ManyToOne(targetEntity: MerchantProduct::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private MerchantProduct $merchantProduct;

    #[ORM\Column]
    #[Assert\GreaterThanOrEqual(1)]
    private int $quantity = 1;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getPack(): ProductPack
    {
        return $this->pack;
    }

    public function setPack(ProductPack $pack): static
    {
        $this->pack = $pack;

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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }
}
