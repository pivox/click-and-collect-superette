<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BillingDocument;
use App\Entity\SubscriptionPaymentReminder;
use App\Enum\SubscriptionPaymentReminderChannel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubscriptionPaymentReminder>
 */
class SubscriptionPaymentReminderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubscriptionPaymentReminder::class);
    }

    /**
     * @return list<SubscriptionPaymentReminder>
     */
    public function findForDocument(BillingDocument $document): array
    {
        /* @var list<SubscriptionPaymentReminder> */
        return $this->findBy(
            ['billingDocument' => $document],
            ['createdAt' => 'DESC'],
        );
    }

    public function findOneForDocumentStageChannel(
        BillingDocument $document,
        string $stage,
        SubscriptionPaymentReminderChannel $channel,
    ): ?SubscriptionPaymentReminder {
        return $this->findOneBy([
            'billingDocument' => $document,
            'stage' => $stage,
            'channel' => $channel,
        ]);
    }
}
