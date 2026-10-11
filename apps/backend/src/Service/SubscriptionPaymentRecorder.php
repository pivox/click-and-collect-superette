<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\AdminSubscriptionPaymentCreateInput;
use App\Entity\BillingDocument;
use App\Entity\SubscriptionPayment;
use App\Entity\User;
use App\Enum\BillingDocumentStatus;
use App\Enum\SubscriptionLifecycle;
use App\Enum\SubscriptionPaymentMethod;
use App\Repository\BillingDocumentRepository;
use App\Repository\SubscriptionPaymentRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

final readonly class SubscriptionPaymentRecorder
{
    public function __construct(
        private BillingDocumentRepository $documents,
        private SubscriptionPaymentRepository $payments,
        private EntityManagerInterface $entityManager,
        private AdminAuditLogger $auditLogger,
    ) {
    }

    public function record(AdminSubscriptionPaymentCreateInput $input, User $admin): SubscriptionPayment
    {
        $replay = $this->findReplay($input, $admin);
        if (null !== $replay) {
            return $replay;
        }
        $amountTnd = bcadd((string) $input->amountTnd, '0', 3);
        $paidAt = (new \DateTimeImmutable((string) $input->paidAt))->setTimezone(new \DateTimeZone('UTC'));
        $method = SubscriptionPaymentMethod::from((string) $input->method);
        $key = null !== $input->idempotencyKey ? Uuid::fromString($input->idempotencyKey) : null;
        $reference = null !== $input->reference && '' !== trim($input->reference) ? trim($input->reference) : null;
        $document = $this->resolveDocument((string) $input->billingDocumentId);
        $subscription = $document->getSubscription();
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            // Refresh after acquiring the locks: an earlier request may have changed the balance.
            $this->entityManager->refresh($subscription, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->refresh($document, LockMode::PESSIMISTIC_WRITE);
            $replay = $this->findReplay($input, $admin);
            if (null !== $replay) {
                $connection->commit();

                return $replay;
            }
            $this->validatePayment($document, $amountTnd, $paidAt);
            $payment = SubscriptionPayment::confirm($document, $amountTnd, $method, $paidAt, $admin, $reference, $key);
            $document->recordPayment($amountTnd, $paidAt);
            $previousLifecycle = $subscription->getLifecycle();
            if (BillingDocumentStatus::Paid === $document->getStatus()
                && !$this->documents->hasOtherDueDocuments($document, new \DateTimeImmutable())
                && \in_array($previousLifecycle, [SubscriptionLifecycle::PaymentDue, SubscriptionLifecycle::GracePeriod, SubscriptionLifecycle::Suspended], true)) {
                $subscription->setLifecycle(SubscriptionLifecycle::Active);
            }
            $this->entityManager->persist($payment);
            $this->auditLogger->log(
                action: 'subscription_payment.confirmed',
                resourceType: 'subscription_payment',
                resourceId: $payment->getId()->toRfc4122(),
                summary: \sprintf('Paiement manuel %s TND confirmé pour %s.', $amountTnd, $document->getDocumentNumber()),
                metadata: [
                    'billing_document_id' => $document->getId()->toRfc4122(),
                    'subscription_id' => $subscription->getId()->toRfc4122(),
                    'merchant_id' => $document->getMerchant()->getId()->toRfc4122(),
                    'method' => $method->value,
                ],
            );
            if (SubscriptionLifecycle::Suspended === $previousLifecycle && SubscriptionLifecycle::Active === $subscription->getLifecycle()) {
                $this->auditLogger->log(
                    action: 'subscription.reactivated',
                    resourceType: 'subscription',
                    resourceId: $subscription->getId()->toRfc4122(),
                    summary: \sprintf('Abonnement marchand %s réactivé après paiement manuel.', $document->getMerchant()->getEmail()),
                    metadata: [
                        'billing_document_id' => $document->getId()->toRfc4122(),
                        'subscription_payment_id' => $payment->getId()->toRfc4122(),
                        'merchant_id' => $document->getMerchant()->getId()->toRfc4122(),
                        'previous_lifecycle' => $previousLifecycle->value,
                        'next_lifecycle' => SubscriptionLifecycle::Active->value,
                    ],
                );
            }
            $this->entityManager->flush();
            $connection->commit();

            return $payment;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            if ($exception instanceof UniqueConstraintViolationException) {
                throw new ConflictHttpException('SUBSCRIPTION_PAYMENT_IDEMPOTENCY_CONFLICT', $exception);
            }

            throw $exception;
        }
    }

    private function findReplay(AdminSubscriptionPaymentCreateInput $input, User $admin): ?SubscriptionPayment
    {
        if (null === $input->idempotencyKey) {
            return null;
        }
        $existing = $this->payments->findOneBy(['idempotencyKey' => Uuid::fromString($input->idempotencyKey)]);
        if (null === $existing) {
            return null;
        }
        $reference = null !== $input->reference && '' !== trim($input->reference) ? trim($input->reference) : null;
        if (!$existing->getBillingDocument()->getId()->equals(Uuid::fromString((string) $input->billingDocumentId))
            || !$existing->getCreatedByAdmin()->getId()->equals($admin->getId())
            || 0 !== bccomp($existing->getAmountTnd(), (string) $input->amountTnd, 3)
            || $existing->getPaidAt() != new \DateTimeImmutable((string) $input->paidAt)
            || $existing->getMethod()->value !== $input->method
            || $existing->getReference() !== $reference) {
            throw new ConflictHttpException('SUBSCRIPTION_PAYMENT_IDEMPOTENCY_CONFLICT');
        }

        return $existing;
    }

    private function validatePayment(BillingDocument $document, string $amountTnd, \DateTimeImmutable $paidAt): void
    {
        if (BillingDocumentStatus::Paid === $document->getStatus()) {
            throw new ConflictHttpException('SUBSCRIPTION_PAYMENT_DOCUMENT_ALREADY_PAID');
        }
        if (BillingDocumentStatus::Cancelled === $document->getStatus()) {
            throw new ConflictHttpException('SUBSCRIPTION_PAYMENT_DOCUMENT_CANCELLED');
        }
        if (BillingDocumentStatus::Draft === $document->getStatus()) {
            throw new ConflictHttpException('SUBSCRIPTION_PAYMENT_DOCUMENT_NOT_ISSUED');
        }
        if (SubscriptionLifecycle::Cancelled === $document->getSubscription()->getLifecycle()) {
            throw new ConflictHttpException('SUBSCRIPTION_PAYMENT_SUBSCRIPTION_CANCELLED');
        }
        if ($paidAt > new \DateTimeImmutable()) {
            throw new UnprocessableEntityHttpException('SUBSCRIPTION_PAYMENT_PAID_AT_IN_FUTURE');
        }
        if (bccomp($amountTnd, '0.000', 3) <= 0) {
            throw new UnprocessableEntityHttpException('SUBSCRIPTION_PAYMENT_AMOUNT_INVALID');
        }
        if (bccomp($amountTnd, $document->getAmountDueTnd(), 3) > 0) {
            throw new UnprocessableEntityHttpException('SUBSCRIPTION_PAYMENT_AMOUNT_MISMATCH');
        }
    }

    private function resolveDocument(string $documentId): BillingDocument
    {
        if (!Uuid::isValid($documentId)) {
            throw new NotFoundHttpException('BILLING_DOCUMENT_NOT_FOUND');
        }
        $document = $this->documents->find($documentId);
        if (null === $document) {
            throw new NotFoundHttpException('BILLING_DOCUMENT_NOT_FOUND');
        }

        return $document;
    }
}
