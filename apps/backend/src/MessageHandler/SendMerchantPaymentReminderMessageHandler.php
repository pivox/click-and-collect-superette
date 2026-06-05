<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Billing\MerchantPaymentReminderContext;
use App\Billing\MerchantPaymentReminderEmailSenderInterface;
use App\Billing\PaymentReminderStage;
use App\Entity\BillingDocument;
use App\Entity\SubscriptionPaymentReminder;
use App\Enum\BillingDocumentStatus;
use App\Enum\SubscriptionLifecycle;
use App\Enum\SubscriptionPaymentReminderChannel;
use App\Enum\SubscriptionPaymentReminderStatus;
use App\Message\SendMerchantPaymentReminderMessage;
use App\Repository\BillingDocumentRepository;
use App\Repository\SubscriptionPaymentReminderRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class SendMerchantPaymentReminderMessageHandler
{
    public function __construct(
        private MerchantPaymentReminderEmailSenderInterface $sender,
        private BillingDocumentRepository $billingDocumentRepository,
        private SubscriptionPaymentReminderRepository $reminderRepository,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SendMerchantPaymentReminderMessage $message): void
    {
        $stage = PaymentReminderStage::from($message->stage);
        $context = new MerchantPaymentReminderContext(
            merchantEmail: $message->merchantEmail,
            merchantName: $message->merchantName,
            shopName: $message->shopName,
            dueDate: new \DateTimeImmutable($message->dueDate),
            amountTnd: $message->amountTnd,
            stage: $stage,
        );

        if (null === $message->billingDocumentId || !Uuid::isValid($message->billingDocumentId)) {
            $this->sender->send($context);

            return;
        }

        $document = $this->billingDocumentRepository->find($message->billingDocumentId);
        if (null === $document) {
            return;
        }

        $committed = false;
        $this->entityManager->beginTransaction();
        try {
            $this->entityManager->refresh($document, LockMode::PESSIMISTIC_WRITE);
            if (!$this->isRelaunchable($document)) {
                $this->entityManager->commit();
                $committed = true;

                return;
            }

            $now = \DateTimeImmutable::createFromInterface($this->clock->now());
            $trace = $this->reminderRepository->findOneForDocumentStageChannel(
                $document,
                $stage->value,
                SubscriptionPaymentReminderChannel::Email,
            );
            if (SubscriptionPaymentReminderStatus::Sent === $trace?->getStatus()) {
                $this->entityManager->commit();
                $committed = true;

                return;
            }

            try {
                $this->sender->send($context);
                if (null === $trace) {
                    $trace = SubscriptionPaymentReminder::emailSent($document, $stage, $now);
                    $this->entityManager->persist($trace);
                } else {
                    $trace->markEmailSent($now);
                }
                $this->entityManager->flush();
                $this->entityManager->commit();
                $committed = true;
            } catch (\Throwable $exception) {
                if (null === $trace) {
                    $trace = SubscriptionPaymentReminder::emailFailed($document, $stage, $now, $exception->getMessage());
                    $this->entityManager->persist($trace);
                } else {
                    $trace->markEmailFailed($now, $exception->getMessage());
                }
                $this->entityManager->flush();
                $this->entityManager->commit();
                $committed = true;

                throw $exception;
            }
        } catch (\Throwable $exception) {
            if (!$committed) {
                $this->entityManager->rollback();
            }

            throw $exception;
        }
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
}
