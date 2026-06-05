<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Billing\MerchantPaymentReminderContext;
use App\Billing\MerchantPaymentReminderEmailSenderInterface;
use App\Billing\PaymentReminderStage;
use App\Entity\SubscriptionPaymentReminder;
use App\Enum\SubscriptionPaymentReminderChannel;
use App\Message\SendMerchantPaymentReminderMessage;
use App\Repository\BillingDocumentRepository;
use App\Repository\SubscriptionPaymentReminderRepository;
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

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $trace = $this->reminderRepository->findOneForDocumentStageChannel(
            $document,
            $stage->value,
            SubscriptionPaymentReminderChannel::Email,
        );
        try {
            $this->sender->send($context);
            if (null === $trace) {
                $trace = SubscriptionPaymentReminder::emailSent($document, $stage, $now);
                $this->entityManager->persist($trace);
            } else {
                $trace->markEmailSent($now);
            }
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            if (null === $trace) {
                $trace = SubscriptionPaymentReminder::emailFailed($document, $stage, $now, $exception->getMessage());
                $this->entityManager->persist($trace);
            } else {
                $trace->markEmailFailed($now, $exception->getMessage());
            }
            $this->entityManager->flush();

            throw $exception;
        }
    }
}
