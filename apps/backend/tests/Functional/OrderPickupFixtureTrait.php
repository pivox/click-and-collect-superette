<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\PickupSlot;
use App\Entity\ProductReference;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\ProductReferenceStatus;
use Symfony\Component\Uid\Uuid;

/**
 * Shared fixture helpers for order/pickup functional tests.
 * Requires the using class to have $this->entityManager (provided by FunctionalApiTestCase).
 */
trait OrderPickupFixtureTrait
{
    protected function createMerchantProduct(Shop $shop): MerchantProduct
    {
        $id = Uuid::v4()->toRfc4122();
        $brand = (new Brand())
            ->setCanonicalName('Brand '.$id)
            ->setSlug('brand-'.$id);
        $category = (new Category())
            ->setNameFr('Cat '.$id)
            ->setSlug('cat-'.$id);
        $ref = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr('Produit test')
            ->setStatus(ProductReferenceStatus::Approved);
        $product = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($ref)
            ->setPriceTnd('2.000');

        $this->entityManager->persist($brand);
        $this->entityManager->persist($category);
        $this->entityManager->persist($ref);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }

    protected function createSubmittedOrder(User $customer, Shop $shop, MerchantProduct $product): Order
    {
        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop);
        $order->submit();

        $line = (new OrderLine())
            ->setMerchantProduct($product)
            ->setQuantity(1)
            ->setUnitPriceTnd($product->getPriceTnd())
            ->setLineTotalTnd($product->getPriceTnd());
        $order->addLine($line);
        $order->recomputeTotal();

        $this->entityManager->persist($order);
        $this->entityManager->persist($line);
        $this->entityManager->flush();

        return $order;
    }

    protected function createPickupSlot(Shop $shop, \DateTimeImmutable $startsAt): PickupSlot
    {
        $slot = (new PickupSlot())
            ->setShop($shop)
            ->setStartsAt($startsAt)
            ->setEndsAt($startsAt->modify('+1 hour'))
            ->setCapacity(5);

        $this->entityManager->persist($slot);
        $this->entityManager->flush();

        return $slot;
    }
}
