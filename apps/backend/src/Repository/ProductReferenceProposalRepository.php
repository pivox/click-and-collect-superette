<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductReferenceProposal;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductReferenceProposal>
 */
class ProductReferenceProposalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductReferenceProposal::class);
    }

    /** @return list<ProductReferenceProposal> */
    public function findForShop(Shop $shop): array
    {
        return $this->findBy(['shop' => $shop], ['createdAt' => 'DESC']);
    }
}
