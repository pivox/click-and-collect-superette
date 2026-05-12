<?php

declare(strict_types=1);

namespace App\Tests\Functional\Doctrine;

use App\Entity\CustomerShop;
use App\Enum\CustomerShopSource;
use App\Enum\CustomerShopStatus;
use App\Repository\CustomerShopRepository;
use App\Tests\Functional\Api\FunctionalApiTestCase;

final class CustomerShopDoctrineTest extends FunctionalApiTestCase
{
    public function testCustomerShopCanBePersistedAndRetrieved(): void
    {
        $customer = $this->createUser('customer-cs@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $relation = (new CustomerShop())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setSource(CustomerShopSource::QrCode);

        $this->entityManager->persist($relation);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->entityManager->getRepository(CustomerShop::class)->find($relation->getId());

        self::assertInstanceOf(CustomerShop::class, $found);
        self::assertSame(CustomerShopSource::QrCode, $found->getSource());
        self::assertSame(CustomerShopStatus::Active, $found->getStatus());
        self::assertFalse($found->isFavorite());
    }

    public function testDefaultValues(): void
    {
        $customer = $this->createUser('customer-defaults@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $relation = (new CustomerShop())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setSource(CustomerShopSource::Search);

        $this->entityManager->persist($relation);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->entityManager->getRepository(CustomerShop::class)->find($relation->getId());

        self::assertInstanceOf(CustomerShop::class, $found);
        self::assertSame(CustomerShopStatus::Active, $found->getStatus());
        self::assertFalse($found->isFavorite());
        self::assertNotNull($found->getFirstSeenAt());
        self::assertNotNull($found->getLastSeenAt());
    }

    public function testUniqueConstraintPreventsDoubleRelation(): void
    {
        $customer = $this->createUser('customer-unique@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $rel1 = (new CustomerShop())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setSource(CustomerShopSource::QrCode);

        $rel2 = (new CustomerShop())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setSource(CustomerShopSource::Search);

        $this->entityManager->persist($rel1);
        $this->entityManager->flush();

        $this->entityManager->persist($rel2);

        $this->expectException(\Exception::class);
        $this->entityManager->flush();
    }

    public function testFirstSeenAtIsImmutableAfterCreation(): void
    {
        $customer = $this->createUser('customer-firstseen@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $relation = (new CustomerShop())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setSource(CustomerShopSource::QrCode);

        $this->entityManager->persist($relation);
        $this->entityManager->flush();

        $firstSeenAt = $relation->getFirstSeenAt();

        $relation->touchLastSeenAt();
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->entityManager->getRepository(CustomerShop::class)->find($relation->getId());

        self::assertInstanceOf(CustomerShop::class, $found);
        self::assertSame($firstSeenAt->getTimestamp(), $found->getFirstSeenAt()->getTimestamp());
    }

    public function testTouchLastSeenAtUpdatesTimestamp(): void
    {
        $customer = $this->createUser('customer-lastseen@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $relation = (new CustomerShop())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setSource(CustomerShopSource::QrCode);

        $this->entityManager->persist($relation);
        $this->entityManager->flush();

        $originalLastSeen = $relation->getLastSeenAt();

        // Simulate time passing.
        sleep(1);
        $relation->touchLastSeenAt();
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->entityManager->getRepository(CustomerShop::class)->find($relation->getId());

        self::assertInstanceOf(CustomerShop::class, $found);
        self::assertGreaterThan($originalLastSeen->getTimestamp(), $found->getLastSeenAt()->getTimestamp());
    }

    public function testFindOneByCustomerAndShop(): void
    {
        $customer = $this->createUser('customer-find@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        $relation = (new CustomerShop())
            ->setCustomer($customer)
            ->setShop($shop)
            ->setSource(CustomerShopSource::Search);

        $this->entityManager->persist($relation);
        $this->entityManager->flush();
        $this->entityManager->clear();

        /** @var CustomerShopRepository $repo */
        $repo = $this->entityManager->getRepository(CustomerShop::class);
        $found = $repo->findOneByCustomerAndShop($customer, $shop);

        self::assertInstanceOf(CustomerShop::class, $found);
        self::assertSame($relation->getId()->toRfc4122(), $found->getId()->toRfc4122());
    }

    public function testFindOneByCustomerAndShopReturnsNullWhenNotFound(): void
    {
        $customer = $this->createUser('customer-notfound@example.test', ['ROLE_CUSTOMER']);
        $shop = $this->createShop();

        /** @var CustomerShopRepository $repo */
        $repo = $this->entityManager->getRepository(CustomerShop::class);
        $found = $repo->findOneByCustomerAndShop($customer, $shop);

        self::assertNull($found);
    }

    public function testFindActiveByCustomerOrdersFavoriteFirst(): void
    {
        $customer = $this->createUser('customer-order@example.test', ['ROLE_CUSTOMER']);
        $shop1 = $this->createShop();
        $shop2 = $this->createShop();
        $shop3 = $this->createShop();

        $rel1 = (new CustomerShop())->setCustomer($customer)->setShop($shop1)->setSource(CustomerShopSource::QrCode);
        $rel2 = (new CustomerShop())->setCustomer($customer)->setShop($shop2)->setSource(CustomerShopSource::Search)->setFavorite(true);
        $rel3 = (new CustomerShop())->setCustomer($customer)->setShop($shop3)->setSource(CustomerShopSource::QrCode);

        $this->entityManager->persist($rel1);
        $this->entityManager->persist($rel2);
        $this->entityManager->persist($rel3);
        $this->entityManager->flush();
        $this->entityManager->clear();

        /** @var CustomerShopRepository $repo */
        $repo = $this->entityManager->getRepository(CustomerShop::class);
        $results = $repo->findActiveByCustomer($customer);

        self::assertCount(3, $results);
        self::assertTrue($results[0]->isFavorite());
    }

    public function testFindActiveByCustomerExcludesHiddenRelations(): void
    {
        $customer = $this->createUser('customer-hidden@example.test', ['ROLE_CUSTOMER']);
        $shop1 = $this->createShop();
        $shop2 = $this->createShop();

        $active = (new CustomerShop())->setCustomer($customer)->setShop($shop1)->setSource(CustomerShopSource::QrCode);
        $hidden = (new CustomerShop())->setCustomer($customer)->setShop($shop2)->setSource(CustomerShopSource::QrCode)->setStatus(CustomerShopStatus::Hidden);

        $this->entityManager->persist($active);
        $this->entityManager->persist($hidden);
        $this->entityManager->flush();
        $this->entityManager->clear();

        /** @var CustomerShopRepository $repo */
        $repo = $this->entityManager->getRepository(CustomerShop::class);
        $results = $repo->findActiveByCustomer($customer);

        self::assertCount(1, $results);
        self::assertSame(CustomerShopStatus::Active, $results[0]->getStatus());
    }
}
