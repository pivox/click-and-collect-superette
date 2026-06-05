<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminBillingDocumentWhatsappContactOutput;
use App\Billing\PaymentReminderSchedule;
use App\Entity\BillingDocument;
use App\Entity\SubscriptionPaymentReminder;
use App\Entity\User;
use App\Enum\BillingDocumentStatus;
use App\Enum\SubscriptionLifecycle;
use App\Repository\BillingDocumentRepository;
use App\Repository\SubscriptionPaymentReminderRepository;
use App\Service\AdminAuditLogger;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<null, AdminBillingDocumentWhatsappContactOutput>
 */
final readonly class AdminBillingDocumentWhatsappContactProcessor implements ProcessorInterface
{
    public function __construct(
        private BillingDocumentRepository $billingDocumentRepository,
        private SubscriptionPaymentReminderRepository $reminderRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private ClockInterface $clock,
        private AdminAuditLogger $auditLogger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminBillingDocumentWhatsappContactOutput
    {
        $admin = $this->security->getUser();
        if (!$admin instanceof User) {
            throw new AccessDeniedHttpException('ADMIN_ACCESS_REQUIRED');
        }

        $document = $this->resolveDocument((string) ($uriVariables['billingDocumentId'] ?? ''));
        if (!$this->isRelaunchable($document)) {
            throw new ConflictHttpException('BILLING_DOCUMENT_NOT_RELAUNCHABLE');
        }

        $committed = false;
        $phone = null;
        $message = null;
        $whatsappUrl = null;
        $this->entityManager->beginTransaction();
        try {
            $this->entityManager->refresh($document, LockMode::PESSIMISTIC_WRITE);
            if (!$this->isRelaunchable($document)) {
                throw new ConflictHttpException('BILLING_DOCUMENT_NOT_RELAUNCHABLE');
            }

            $phone = $this->normalizePhone($document->getMerchant()->getPhone());
            if (null === $phone) {
                throw new ConflictHttpException('BILLING_DOCUMENT_MERCHANT_PHONE_MISSING');
            }

            $now = \DateTimeImmutable::createFromInterface($this->clock->now());
            $stage = $this->stageForDocument($document, $now);
            $message = $this->message($document);
            $whatsappUrl = \sprintf('https://wa.me/%s?text=%s', $phone, rawurlencode($message));
            $reminder = $this->reminderRepository->findOneForDocumentStageChannel(
                $document,
                $stage,
                \App\Enum\SubscriptionPaymentReminderChannel::WhatsappManual,
            ) ?? SubscriptionPaymentReminder::whatsappContacted($document, $stage, $admin, $now);

            $this->entityManager->persist($reminder);
            $this->auditLogger->log(
                action: 'subscription_payment_reminder.whatsapp_contacted',
                resourceType: 'billing_document',
                resourceId: $document->getId()->toRfc4122(),
                summary: \sprintf('Contact WhatsApp préparé pour %s.', $document->getDocumentNumber()),
                metadata: [
                    'merchant_id' => $document->getMerchant()->getId()->toRfc4122(),
                    'phone' => $phone,
                    'stage' => $stage,
                ],
            );
            $this->entityManager->flush();
            $this->entityManager->commit();
            $committed = true;
        } catch (\Throwable $exception) {
            if (!$committed) {
                $this->entityManager->rollback();
            }

            throw $exception;
        }

        return new AdminBillingDocumentWhatsappContactOutput(
            id: $document->getId()->toRfc4122().'-whatsapp',
            billingDocumentId: $document->getId()->toRfc4122(),
            phone: $phone,
            message: $message,
            whatsappUrl: $whatsappUrl,
        );
    }

    private function resolveDocument(string $documentId): BillingDocument
    {
        if (!Uuid::isValid($documentId)) {
            throw new NotFoundHttpException('ADMIN_BILLING_DOCUMENT_NOT_FOUND');
        }

        $document = $this->billingDocumentRepository->find($documentId);
        if (null === $document) {
            throw new NotFoundHttpException('ADMIN_BILLING_DOCUMENT_NOT_FOUND');
        }

        return $document;
    }

    private function isRelaunchable(BillingDocument $document): bool
    {
        return \in_array($document->getStatus(), [BillingDocumentStatus::Issued, BillingDocumentStatus::Overdue], true)
            && bccomp($document->getAmountDueTnd(), '0.000', 3) > 0
            && \in_array($document->getSubscription()->getLifecycle(), [
                SubscriptionLifecycle::Active,
                SubscriptionLifecycle::PaymentDue,
                SubscriptionLifecycle::GracePeriod,
            ], true);
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (null === $phone || '' === trim($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (null === $digits || '' === $digits) {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (8 === \strlen($digits)) {
            return '216'.$digits;
        }

        return $digits;
    }

    private function stageForDocument(BillingDocument $document, \DateTimeImmutable $now): string
    {
        $dueAt = $document->getDueAt();
        if (null === $dueAt) {
            return 'manual';
        }

        $stage = PaymentReminderSchedule::resolveStage($dueAt, $now);
        if (null === $stage) {
            return 'manual';
        }

        return $stage->value;
    }

    private function message(BillingDocument $document): string
    {
        $dueAt = $document->getDueAt()?->format('d/m/Y') ?? 'non planifiée';

        return \sprintf(
            'Bonjour, votre abonnement marchand Kadhia lié au document %s reste à régler. Montant: %s TND. Échéance: %s. Merci de confirmer le règlement manuel auprès de l’équipe Kadhia.',
            $document->getDocumentNumber(),
            $document->getAmountDueTnd(),
            $dueAt,
        );
    }
}
