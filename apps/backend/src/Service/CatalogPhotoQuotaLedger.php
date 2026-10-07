<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CatalogPhotoImportImage;
use App\Entity\CatalogPhotoQuotaEntry;
use App\Entity\CatalogPhotoQuotaGrant;
use App\Entity\Shop;
use App\Enum\CatalogPhotoQuotaOperation;
use App\Repository\CatalogPhotoQuotaEntryRepository;
use App\Repository\CatalogPhotoQuotaGrantRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Single choke point for the shared merchant photo-import quota
 * (CATALOG-AI-001, issue #638). Every credit movement goes through here so
 * the balance is always derived from the append-only journal, never from a
 * mutable counter.
 */
final readonly class CatalogPhotoQuotaLedger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CatalogPhotoQuotaGrantRepository $grantRepository,
        private CatalogPhotoQuotaEntryRepository $entryRepository,
    ) {
    }

    /**
     * Lazily creates the one-time welcome grant. Race-safe: a losing
     * concurrent insert hits the unique (shop, source) index and simply
     * re-reads the winner's row instead of failing the request.
     */
    public function ensureWelcomeGrant(Shop $shop): CatalogPhotoQuotaGrant
    {
        $existing = $this->grantRepository->findOneWelcomeGrantByShop($shop);
        if (null !== $existing) {
            return $existing;
        }

        $grant = new CatalogPhotoQuotaGrant($shop, CatalogPhotoQuotaGrant::WELCOME_ALLOWANCE, CatalogPhotoQuotaGrant::SOURCE_WELCOME);
        $this->entityManager->persist($grant);
        try {
            $this->entityManager->flush();

            return $grant;
        } catch (UniqueConstraintViolationException) {
            $this->entityManager->detach($grant);
            $winner = $this->grantRepository->findOneWelcomeGrantByShop($shop);
            \assert(null !== $winner, 'the unique violation guarantees a winning row exists');

            return $winner;
        }
    }

    public function getBalance(Shop $shop): CatalogPhotoQuotaBalance
    {
        $this->ensureWelcomeGrant($shop);

        $granted = $this->grantRepository->sumAllowanceForShop($shop);
        $reserved = $this->entryRepository->countByOperationForShop($shop, CatalogPhotoQuotaOperation::Reservation)
            - $this->entryRepository->countByOperationForShop($shop, CatalogPhotoQuotaOperation::Release)
            - $this->entryRepository->countByOperationForShop($shop, CatalogPhotoQuotaOperation::Consumption);
        $consumed = $this->entryRepository->countByOperationForShop($shop, CatalogPhotoQuotaOperation::Consumption);
        $available = $granted + $this->entryRepository->sumQuantityForShop($shop);

        return new CatalogPhotoQuotaBalance(
            granted: $granted,
            reserved: max(0, $reserved),
            consumed: $consumed,
            available: max(0, $available),
        );
    }

    /**
     * One source photo = at most one reservation. Atomic under concurrency:
     * the shop's grant row is locked for the whole check-then-insert, so two
     * simultaneous requests against the last credit never both succeed.
     *
     * Idempotent: replaying the same image (retry, redelivery) is a no-op
     * once the reservation already exists.
     *
     * @throws CatalogPhotoQuotaExhaustedException
     */
    public function reserveForImage(CatalogPhotoImportImage $image): void
    {
        $idempotencyKey = \sprintf('reservation:%s', $image->getId()->toRfc4122());
        if (null !== $this->entryRepository->findOneByIdempotencyKey($idempotencyKey)) {
            return;
        }

        $shop = $image->getShop();
        $this->entityManager->getConnection()->transactional(function () use ($image, $shop, $idempotencyKey): void {
            $this->ensureWelcomeGrant($shop);
            $grant = $this->grantRepository->findOneWelcomeGrantByShopForUpdate($shop);
            \assert(null !== $grant, 'ensureWelcomeGrant just created or confirmed this row');

            // Re-check inside the lock: another transaction may have reserved
            // concurrently for the same image between the early check above
            // and acquiring the lock here.
            if (null !== $this->entryRepository->findOneByIdempotencyKey($idempotencyKey)) {
                return;
            }

            $balance = $this->getBalance($shop);
            if ($balance->available <= 0) {
                throw new CatalogPhotoQuotaExhaustedException('CATALOG_PHOTO_QUOTA_EXHAUSTED');
            }

            $this->entityManager->persist(new CatalogPhotoQuotaEntry(
                grant: $grant,
                operation: CatalogPhotoQuotaOperation::Reservation,
                quantity: -1,
                idempotencyKey: $idempotencyKey,
                importImage: $image,
            ));
            $this->entityManager->flush();
        });
    }

    /**
     * Frees a reservation (image removed/session cancelled before any usable
     * result exists). Idempotent and safe to call on an image that was never
     * reserved (e.g. quota was exhausted when it was registered).
     */
    public function releaseForImage(CatalogPhotoImportImage $image): void
    {
        $reservationKey = \sprintf('reservation:%s', $image->getId()->toRfc4122());
        if (null === $this->entryRepository->findOneByIdempotencyKey($reservationKey)) {
            return; // nothing was ever reserved for this image
        }

        $releaseKey = \sprintf('release:%s', $image->getId()->toRfc4122());
        if (null !== $this->entryRepository->findOneByIdempotencyKey($releaseKey)) {
            return; // already released
        }

        $consumptionKey = \sprintf('consumption:%s', $image->getId()->toRfc4122());
        if (null !== $this->entryRepository->findOneByIdempotencyKey($consumptionKey)) {
            return; // already consumed — never recredited (business rule)
        }

        $shop = $image->getShop();
        $this->entityManager->getConnection()->transactional(function () use ($shop, $image, $releaseKey): void {
            $grant = $this->grantRepository->findOneWelcomeGrantByShopForUpdate($shop);
            \assert(null !== $grant, 'releasing an image implies its reservation already created the grant');

            $this->entityManager->persist(new CatalogPhotoQuotaEntry(
                grant: $grant,
                operation: CatalogPhotoQuotaOperation::Release,
                quantity: 1,
                idempotencyKey: $releaseKey,
                importImage: $image,
            ));
            $this->entityManager->flush();
        });
    }

    /**
     * Converts a reservation into a final, non-releasable debit once a usable
     * AI result exists (CATALOG-AI-004, #641). Not reachable through any
     * endpoint in this issue; exposed and tested here so the "10 photos × 4
     * providers = 10 credits" guarantee is in place before #641 wires it up.
     */
    public function consumeForImage(CatalogPhotoImportImage $image): void
    {
        $reservationKey = \sprintf('reservation:%s', $image->getId()->toRfc4122());
        if (null === $this->entryRepository->findOneByIdempotencyKey($reservationKey)) {
            return; // nothing to finalize
        }

        $consumptionKey = \sprintf('consumption:%s', $image->getId()->toRfc4122());
        if (null !== $this->entryRepository->findOneByIdempotencyKey($consumptionKey)) {
            return; // already consumed
        }

        $releaseKey = \sprintf('release:%s', $image->getId()->toRfc4122());
        if (null !== $this->entryRepository->findOneByIdempotencyKey($releaseKey)) {
            return; // already released — consumption cannot resurrect a freed credit
        }

        $shop = $image->getShop();
        $this->entityManager->getConnection()->transactional(function () use ($shop, $image, $consumptionKey): void {
            $grant = $this->grantRepository->findOneWelcomeGrantByShopForUpdate($shop);
            \assert(null !== $grant, 'consuming an image implies its reservation already created the grant');

            $this->entityManager->persist(new CatalogPhotoQuotaEntry(
                grant: $grant,
                operation: CatalogPhotoQuotaOperation::Consumption,
                quantity: 0,
                idempotencyKey: $consumptionKey,
                importImage: $image,
            ));
            $this->entityManager->flush();
        });
    }

    /**
     * Manual correction that does not touch the welcome grant row, so the
     * original attribution stays a single auditable fact. `$reason` is the
     * only audit trail in V1 — there is no admin-facing caller yet (reserved
     * for the admin review queue, #647/#649).
     */
    public function adjustBalance(Shop $shop, int $delta, string $idempotencyKey, string $reason): void
    {
        if (null !== $this->entryRepository->findOneByIdempotencyKey($idempotencyKey)) {
            return;
        }

        $this->entityManager->getConnection()->transactional(function () use ($shop, $delta, $idempotencyKey, $reason): void {
            $this->ensureWelcomeGrant($shop);
            $grant = $this->grantRepository->findOneWelcomeGrantByShopForUpdate($shop);
            \assert(null !== $grant, 'ensureWelcomeGrant just created or confirmed this row');

            if (null !== $this->entryRepository->findOneByIdempotencyKey($idempotencyKey)) {
                return;
            }

            $entry = new CatalogPhotoQuotaEntry(
                grant: $grant,
                operation: CatalogPhotoQuotaOperation::AdminAdjustment,
                quantity: $delta,
                idempotencyKey: $idempotencyKey,
                importImage: null,
                reason: $reason,
            );
            $this->entityManager->persist($entry);
            $this->entityManager->flush();
        });
    }
}
