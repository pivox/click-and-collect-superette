<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BillingDocumentOutput;
use App\Entity\Subscription;
use App\Enum\SubscriptionLifecycle;
use App\Provider\BillingDocumentOutputFactory;
use App\Repository\BillingDocumentRepository;
use App\Service\AdminAuditLogger;
use App\Service\BillingDocumentIssuer;
use App\Service\SubscriptionPricingSchedule;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** @implements ProcessorInterface<mixed, BillingDocumentOutput> */
final readonly class AdminIssueSubscriptionBillingDocumentProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BillingDocumentRepository $documents,
        private BillingDocumentIssuer $issuer,
        private SubscriptionPricingSchedule $schedule,
        private BillingDocumentOutputFactory $output,
        private AdminAuditLogger $audit,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BillingDocumentOutput
    {
        return $this->entityManager->wrapInTransaction(function () use ($uriVariables): BillingDocumentOutput {
            $subscription = $this->entityManager->find(Subscription::class, $uriVariables['subscriptionId'], LockMode::PESSIMISTIC_WRITE);
            if (null === $subscription) {
                throw new NotFoundHttpException('SUBSCRIPTION_NOT_FOUND');
            }
            if (SubscriptionLifecycle::Cancelled === $subscription->getLifecycle()) {
                throw new ConflictHttpException('SUBSCRIPTION_CANCELLED');
            }
            $now = new \DateTimeImmutable();
            $pricing = $this->schedule->forSubscription($subscription, $now);
            $existing = $this->documents->findOneBy(['subscription' => $subscription, 'billingPeriodStart' => $pricing->currentPeriodStartedAt, 'billingPeriodEnd' => $pricing->currentPeriodEndsAt]);
            if (null !== $existing) {
                return $this->output->fromBillingDocument($existing);
            }
            $document = $this->issuer->issueCurrentPeriod($subscription, $now);
            if (null === $document) {
                throw new UnprocessableEntityHttpException('SUBSCRIPTION_PERIOD_FREE');
            }
            $this->entityManager->persist($document);
            $this->audit->log(action: 'billing_document.issued', resourceType: 'billing_document', resourceId: (string) $document->getId(), summary: 'Document interne non fiscal émis pour la période courante.', metadata: ['subscription_id' => (string) $subscription->getId(), 'amount_tnd' => $document->getAmountTnd(), 'billing_interval' => $pricing->billingInterval]);
            $this->entityManager->flush();

            return $this->output->fromBillingDocument($document);
        });
    }
}
