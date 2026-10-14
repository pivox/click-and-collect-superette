<?php

declare(strict_types=1);

namespace App\Tests\Functional\Doctrine;

use App\Entity\Shop;
use App\Tests\Functional\Api\FunctionalApiTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

/**
 * Guards the premise of the slug conflict handling in CreateAdminStoreProcessor.
 *
 * That processor generates the slug with a read-then-write loop, so two
 * concurrent creations can pick the same value. It relies on the database to
 * reject the loser of the race, and turns the resulting violation into a 409
 * instead of the 500 it used to produce.
 *
 * The unique index comes from `#[ORM\Column(unique: true)]` on Shop::$slug and
 * from `UNIQ_6F6A974989D9B62` in migration Version20260507120000. Dropping
 * either would silently reintroduce duplicate slugs rather than a conflict, so
 * this test fails loudly if the constraint ever disappears.
 */
final class ShopSlugUniquenessDoctrineTest extends FunctionalApiTestCase
{
    public function testTwoShopsCannotShareTheSameSlug(): void
    {
        $slug = 'superette-slug-conflit';

        $this->entityManager->persist($this->buildShop($slug));
        $this->entityManager->flush();

        $this->entityManager->persist($this->buildShop($slug));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testDistinctSlugsArePersistedWithoutConflict(): void
    {
        $this->entityManager->persist($this->buildShop('superette-slug-a'));
        $this->entityManager->persist($this->buildShop('superette-slug-b'));
        $this->entityManager->flush();

        $shops = $this->entityManager->getRepository(Shop::class)->findBy(
            ['slug' => ['superette-slug-a', 'superette-slug-b']],
        );

        self::assertCount(2, $shops);
    }

    private function buildShop(string $slug): Shop
    {
        $id = Uuid::v4();

        return (new Shop())
            ->setName('Supérette '.$id)
            ->setSlug($slug)
            ->setQrCodeToken('qr-'.$id)
            ->setActive(true);
    }
}
