<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FeedbackEntry;
use App\Entity\Shop;
use App\Enum\FeedbackAppArea;
use App\Enum\FeedbackStatus;
use App\Enum\FeedbackType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FeedbackEntry>
 */
class FeedbackEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeedbackEntry::class);
    }

    /**
     * @return list<FeedbackEntry>
     */
    public function findAdminPaginated(
        int $limit,
        int $offset,
        ?FeedbackStatus $status,
        ?string $userRole,
        ?FeedbackType $type,
        ?FeedbackAppArea $appArea,
        ?Shop $shop,
        ?bool $contactConsent,
        ?\DateTimeImmutable $createdFrom,
        ?\DateTimeImmutable $createdTo,
    ): array {
        $queryBuilder = $this->createQueryBuilder('feedback')
            ->orderBy('feedback.createdAt', 'DESC')
            ->addOrderBy('feedback.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        $this->applyAdminFilters($queryBuilder, $status, $userRole, $type, $appArea, $shop, $contactConsent, $createdFrom, $createdTo);

        /** @var list<FeedbackEntry> $feedbacks */
        $feedbacks = $queryBuilder->getQuery()->getResult();

        return $feedbacks;
    }

    public function countAdmin(
        ?FeedbackStatus $status,
        ?string $userRole,
        ?FeedbackType $type,
        ?FeedbackAppArea $appArea,
        ?Shop $shop,
        ?bool $contactConsent,
        ?\DateTimeImmutable $createdFrom,
        ?\DateTimeImmutable $createdTo,
    ): int {
        $queryBuilder = $this->createQueryBuilder('feedback')
            ->select('COUNT(feedback.id)');
        $this->applyAdminFilters($queryBuilder, $status, $userRole, $type, $appArea, $shop, $contactConsent, $createdFrom, $createdTo);

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    private function applyAdminFilters(
        QueryBuilder $queryBuilder,
        ?FeedbackStatus $status,
        ?string $userRole,
        ?FeedbackType $type,
        ?FeedbackAppArea $appArea,
        ?Shop $shop,
        ?bool $contactConsent,
        ?\DateTimeImmutable $createdFrom,
        ?\DateTimeImmutable $createdTo,
    ): void {
        if (null !== $status) {
            $queryBuilder
                ->andWhere('feedback.status = :status')
                ->setParameter('status', $status->value);
        }
        if (null !== $userRole) {
            $queryBuilder
                ->andWhere('feedback.userRole = :userRole')
                ->setParameter('userRole', $userRole);
        }
        if (null !== $type) {
            $queryBuilder
                ->andWhere('feedback.type = :type')
                ->setParameter('type', $type->value);
        }
        if (null !== $appArea) {
            $queryBuilder
                ->andWhere('feedback.appArea = :appArea')
                ->setParameter('appArea', $appArea->value);
        }
        if (null !== $shop) {
            $queryBuilder
                ->andWhere('IDENTITY(feedback.shop) = :shopId')
                ->setParameter('shopId', $shop->getId(), 'uuid');
        }
        if (null !== $contactConsent) {
            $queryBuilder
                ->andWhere('feedback.contactConsent = :contactConsent')
                ->setParameter('contactConsent', $contactConsent);
        }
        if (null !== $createdFrom) {
            $queryBuilder
                ->andWhere('feedback.createdAt >= :createdFrom')
                ->setParameter('createdFrom', $createdFrom);
        }
        if (null !== $createdTo) {
            $queryBuilder
                ->andWhere('feedback.createdAt <= :createdTo')
                ->setParameter('createdTo', $createdTo);
        }
    }
}
