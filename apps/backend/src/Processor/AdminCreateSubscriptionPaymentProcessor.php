<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\SubscriptionPaymentOutput;
use App\Dto\AdminSubscriptionPaymentCreateInput;
use App\Entity\BillingDocument;
use App\Entity\SubscriptionPayment;
use App\Entity\User;
use App\Enum\BillingDocumentStatus;
use App\Enum\SubscriptionLifecycle;
use App\Enum\SubscriptionPaymentMethod;
use App\Provider\SubscriptionPaymentOutputFactory;
use App\Repository\BillingDocumentRepository;
use App\Service\AdminAuditLogger;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<AdminSubscriptionPaymentCreateInput, SubscriptionPaymentOutput>
 */
final readonly class AdminCreateSubscriptionPaymentProcessor implements ProcessorInterface
{
    public function __construct(
        private BillingDocumentRepository $billingDocumentRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private AdminAuditLogger $auditLogger,
        private SubscriptionPaymentOutputFactory $outputFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SubscriptionPaymentOutput
    {
        if (!$data instanceof AdminSubscriptionPaymentCreateInput) {
            throw new \InvalidArgumentException('AdminSubscriptionPaymentCreateInput expected.');
        }

        $admin = $this->security->getUser();
        if (!$admin instanceof User) {
            throw new AccessDeniedHttpException('ADMIN_ACCESS_REQUIRED');
        }

        $document = $this->resolveDocument((string) $data->billingDocumentId);
        $amountTnd = bcadd((string) $data->amountTnd, '0', 3);
        $paidAt = $this->parsePaidAt((string) $data->paidAt);
        $method = SubscriptionPaymentMethod::from((string) $data->method);
        $subscription = $document->getSubscription();

        if (BillingDocumentStatus::Paid === $document->getStatus()) {
            throw new ConflictHttpException('SUBSCRIPTION_PAYMENT_DOCUMENT_ALREADY_PAID');
        }
        if (BillingDocumentStatus::Cancelled === $document->getStatus()) {
            throw new ConflictHttpException('SUBSCRIPTION_PAYMENT_DOCUMENT_CANCELLED');
        }
        if (SubscriptionLifecycle::Cancelled === $subscription->getLifecycle()) {
            throw new ConflictHttpException('SUBSCRIPTION_PAYMENT_SUBSCRIPTION_CANCELLED');
        }
        if (bccomp($amountTnd, '0.000', 3) <= 0) {
            throw new UnprocessableEntityHttpException('SUBSCRIPTION_PAYMENT_AMOUNT_INVALID');
        }
        if (0 !== bccomp($amountTnd, $document->getAmountDueTnd(), 3)) {
            throw new UnprocessableEntityHttpException('SUBSCRIPTION_PAYMENT_AMOUNT_MISMATCH');
        }

        $payment = SubscriptionPayment::confirm(
            billingDocument: $document,
            amountTnd: $amountTnd,
            method: $method,
            paidAt: $paidAt,
            createdByAdmin: $admin,
            reference: $data->reference,
        );

        $document->markPaid($paidAt);
        $previousLifecycle = $subscription->getLifecycle();
        if (\in_array($subscription->getLifecycle(), [
            SubscriptionLifecycle::PaymentDue,
            SubscriptionLifecycle::GracePeriod,
            SubscriptionLifecycle::Suspended,
        ], true)) {
            $subscription->setLifecycle(SubscriptionLifecycle::Active);
        }

        try {
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
            if (SubscriptionLifecycle::Suspended === $previousLifecycle) {
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
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('SUBSCRIPTION_PAYMENT_DOCUMENT_ALREADY_PAID');
        }

        return $this->outputFactory->fromSubscriptionPayment($payment);
    }

    private function resolveDocument(string $documentId): BillingDocument
    {
        if (!Uuid::isValid($documentId)) {
            throw new NotFoundHttpException('BILLING_DOCUMENT_NOT_FOUND');
        }

        $document = $this->billingDocumentRepository->find($documentId);
        if (null === $document) {
            throw new NotFoundHttpException('BILLING_DOCUMENT_NOT_FOUND');
        }

        return $document;
    }

    private function parsePaidAt(string $paidAt): \DateTimeImmutable
    {
        try {
            $date = new \DateTimeImmutable($paidAt);
        } catch (\Exception) {
            throw new UnprocessableEntityHttpException('SUBSCRIPTION_PAYMENT_PAID_AT_INVALID');
        }

        if ($date > new \DateTimeImmutable()) {
            throw new UnprocessableEntityHttpException('SUBSCRIPTION_PAYMENT_PAID_AT_IN_FUTURE');
        }

        return $date;
    }
}
