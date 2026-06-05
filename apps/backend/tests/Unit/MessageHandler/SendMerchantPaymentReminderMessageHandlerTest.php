<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Billing\MerchantPaymentReminderContext;
use App\Billing\MerchantPaymentReminderEmailSenderInterface;
use App\Billing\PaymentReminderStage;
use App\Entity\BillingDocument;
use App\Entity\Subscription;
use App\Entity\SubscriptionPaymentReminder;
use App\Entity\User;
use App\Enum\SubscriptionLifecycle;
use App\Enum\SubscriptionPaymentReminderChannel;
use App\Enum\SubscriptionPaymentReminderStatus;
use App\Enum\SubscriptionPricingPhase;
use App\Message\SendMerchantPaymentReminderMessage;
use App\MessageHandler\SendMerchantPaymentReminderMessageHandler;
use App\Repository\BillingDocumentRepository;
use App\Repository\SubscriptionPaymentReminderRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class SendMerchantPaymentReminderMessageHandlerTest extends TestCase
{
    public function testHandlerBuildsReminderContextAndSendsEmail(): void
    {
        $sender = new CapturingMerchantPaymentReminderEmailSender();
        $documentRepository = $this->createMock(BillingDocumentRepository::class);
        $documentRepository->expects(self::never())->method('find');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');
        $reminderRepository = $this->createMock(SubscriptionPaymentReminderRepository::class);
        $reminderRepository->expects(self::never())->method('findOneForDocumentStageChannel');

        $handler = new SendMerchantPaymentReminderMessageHandler(
            $sender,
            $documentRepository,
            $reminderRepository,
            $entityManager,
            new MockClock(new \DateTimeImmutable('2026-06-18T10:00:00+01:00')),
        );

        $handler(new SendMerchantPaymentReminderMessage(
            merchantEmail: 'marchand@example.test',
            merchantName: 'Sami',
            shopName: 'Supérette El Amal',
            dueDate: '2026-06-11',
            amountTnd: '50.000',
            stage: 'j_plus_7',
        ));

        self::assertCount(1, $sender->sent);
        self::assertSame('marchand@example.test', $sender->sent[0]->merchantEmail);
        self::assertSame('Supérette El Amal', $sender->sent[0]->shopName);
        self::assertSame('50.000', $sender->sent[0]->amountTnd);
        self::assertEquals(new \DateTimeImmutable('2026-06-11'), $sender->sent[0]->dueDate);
        self::assertSame(PaymentReminderStage::GracePeriod7, $sender->sent[0]->stage);
    }

    public function testHandlerUpdatesFailedReminderTraceOnRetrySuccess(): void
    {
        $document = $this->document();
        $failedTrace = SubscriptionPaymentReminder::emailFailed(
            $document,
            PaymentReminderStage::GracePeriod7,
            new \DateTimeImmutable('2026-06-18T09:00:00+01:00'),
            'SMTP temporary failure',
        );
        $sender = new CapturingMerchantPaymentReminderEmailSender();
        $documentRepository = $this->createMock(BillingDocumentRepository::class);
        $documentRepository
            ->expects(self::once())
            ->method('find')
            ->with($document->getId()->toRfc4122())
            ->willReturn($document);
        $reminderRepository = $this->createMock(SubscriptionPaymentReminderRepository::class);
        $reminderRepository
            ->expects(self::once())
            ->method('findOneForDocumentStageChannel')
            ->with($document, 'j_plus_7', SubscriptionPaymentReminderChannel::Email)
            ->willReturn($failedTrace);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $handler = new SendMerchantPaymentReminderMessageHandler(
            $sender,
            $documentRepository,
            $reminderRepository,
            $entityManager,
            new MockClock(new \DateTimeImmutable('2026-06-18T10:00:00+01:00')),
        );

        $handler(new SendMerchantPaymentReminderMessage(
            merchantEmail: 'marchand@example.test',
            merchantName: 'Sami',
            shopName: 'Supérette El Amal',
            dueDate: '2026-06-11',
            amountTnd: '50.000',
            stage: 'j_plus_7',
            billingDocumentId: $document->getId()->toRfc4122(),
            documentNumber: 'MS-2026-000401',
        ));

        self::assertSame(SubscriptionPaymentReminderStatus::Sent, $failedTrace->getStatus());
        self::assertSame('2026-06-18T10:00:00+01:00', $failedTrace->getSentAt()?->format(\DateTimeInterface::ATOM));
        self::assertNull($failedTrace->getFailedAt());
        self::assertNull($failedTrace->getFailureReason());
    }

    public function testHandlerDoesNotResendEmailWhenReminderTraceIsAlreadySent(): void
    {
        $document = $this->document();
        $sentTrace = SubscriptionPaymentReminder::emailSent(
            $document,
            PaymentReminderStage::GracePeriod7,
            new \DateTimeImmutable('2026-06-18T09:00:00+01:00'),
        );
        $sender = new CapturingMerchantPaymentReminderEmailSender();
        $documentRepository = $this->createMock(BillingDocumentRepository::class);
        $documentRepository
            ->expects(self::once())
            ->method('find')
            ->with($document->getId()->toRfc4122())
            ->willReturn($document);
        $reminderRepository = $this->createMock(SubscriptionPaymentReminderRepository::class);
        $reminderRepository
            ->expects(self::once())
            ->method('findOneForDocumentStageChannel')
            ->with($document, 'j_plus_7', SubscriptionPaymentReminderChannel::Email)
            ->willReturn($sentTrace);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $handler = new SendMerchantPaymentReminderMessageHandler(
            $sender,
            $documentRepository,
            $reminderRepository,
            $entityManager,
            new MockClock(new \DateTimeImmutable('2026-06-18T10:00:00+01:00')),
        );

        $handler(new SendMerchantPaymentReminderMessage(
            merchantEmail: 'marchand@example.test',
            merchantName: 'Sami',
            shopName: 'Supérette El Amal',
            dueDate: '2026-06-11',
            amountTnd: '50.000',
            stage: 'j_plus_7',
            billingDocumentId: $document->getId()->toRfc4122(),
            documentNumber: 'MS-2026-000401',
        ));

        self::assertCount(0, $sender->sent);
        self::assertSame(SubscriptionPaymentReminderStatus::Sent, $sentTrace->getStatus());
        self::assertSame('2026-06-18T09:00:00+01:00', $sentTrace->getSentAt()?->format(\DateTimeInterface::ATOM));
    }

    public function testHandlerDoesNotSendWhenSubscriptionIsNoLongerRelaunchable(): void
    {
        $document = $this->document();
        $document->getSubscription()->setLifecycle(SubscriptionLifecycle::Suspended);
        $sender = new CapturingMerchantPaymentReminderEmailSender();
        $documentRepository = $this->createMock(BillingDocumentRepository::class);
        $documentRepository
            ->expects(self::once())
            ->method('find')
            ->with($document->getId()->toRfc4122())
            ->willReturn($document);
        $reminderRepository = $this->createMock(SubscriptionPaymentReminderRepository::class);
        $reminderRepository->expects(self::never())->method('findOneForDocumentStageChannel');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('beginTransaction');
        $entityManager->expects(self::once())->method('lock')->with($document, LockMode::PESSIMISTIC_WRITE);
        $entityManager->expects(self::once())->method('commit');
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $handler = new SendMerchantPaymentReminderMessageHandler(
            $sender,
            $documentRepository,
            $reminderRepository,
            $entityManager,
            new MockClock(new \DateTimeImmutable('2026-06-18T10:00:00+01:00')),
        );

        $handler(new SendMerchantPaymentReminderMessage(
            merchantEmail: 'marchand@example.test',
            merchantName: 'Sami',
            shopName: 'Supérette El Amal',
            dueDate: '2026-06-11',
            amountTnd: '50.000',
            stage: 'j_plus_7',
            billingDocumentId: $document->getId()->toRfc4122(),
            documentNumber: 'MS-2026-000401',
        ));

        self::assertCount(0, $sender->sent);
    }

    public function testHandlerLocksDocumentBeforeReadingTraceAndSendingEmail(): void
    {
        $events = [];
        $document = $this->document();
        $sender = $this->createMock(MerchantPaymentReminderEmailSenderInterface::class);
        $sender
            ->expects(self::once())
            ->method('send')
            ->willReturnCallback(static function () use (&$events): void {
                self::assertContains('trace-read', $events);
                $events[] = 'send';
            });
        $documentRepository = $this->createMock(BillingDocumentRepository::class);
        $documentRepository
            ->expects(self::once())
            ->method('find')
            ->with($document->getId()->toRfc4122())
            ->willReturn($document);
        $reminderRepository = $this->createMock(SubscriptionPaymentReminderRepository::class);
        $reminderRepository
            ->expects(self::once())
            ->method('findOneForDocumentStageChannel')
            ->willReturnCallback(static function () use (&$events): ?SubscriptionPaymentReminder {
                self::assertContains('lock', $events);
                $events[] = 'trace-read';

                return null;
            });
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('beginTransaction')
            ->willReturnCallback(static function () use (&$events): void {
                $events[] = 'begin';
            });
        $entityManager
            ->expects(self::once())
            ->method('lock')
            ->with($document, LockMode::PESSIMISTIC_WRITE)
            ->willReturnCallback(static function () use (&$events): void {
                self::assertContains('begin', $events);
                $events[] = 'lock';
            });
        $entityManager
            ->expects(self::once())
            ->method('flush')
            ->willReturnCallback(static function () use (&$events): void {
                self::assertContains('send', $events);
                $events[] = 'flush';
            });
        $entityManager
            ->expects(self::once())
            ->method('commit')
            ->willReturnCallback(static function () use (&$events): void {
                self::assertContains('flush', $events);
                $events[] = 'commit';
            });

        $handler = new SendMerchantPaymentReminderMessageHandler(
            $sender,
            $documentRepository,
            $reminderRepository,
            $entityManager,
            new MockClock(new \DateTimeImmutable('2026-06-18T10:00:00+01:00')),
        );

        $handler(new SendMerchantPaymentReminderMessage(
            merchantEmail: 'marchand@example.test',
            merchantName: 'Sami',
            shopName: 'Supérette El Amal',
            dueDate: '2026-06-11',
            amountTnd: '50.000',
            stage: 'j_plus_7',
            billingDocumentId: $document->getId()->toRfc4122(),
            documentNumber: 'MS-2026-000401',
        ));

        self::assertSame(['begin', 'lock', 'trace-read', 'send', 'flush', 'commit'], $events);
    }

    private function document(): BillingDocument
    {
        $merchant = (new User())
            ->setEmail('marchand@example.test')
            ->setRoles(['ROLE_MERCHANT'])
            ->setPassword('test-password')
            ->setName('Sami');
        $subscription = Subscription::startTrial($merchant, new \DateTimeImmutable('2026-06-01T00:00:00+01:00'));

        return BillingDocument::issueMonthlyStatement(
            subscription: $subscription,
            documentNumber: 'MS-2026-000401',
            billingPeriodStart: new \DateTimeImmutable('2026-06-01T00:00:00+01:00'),
            billingPeriodEnd: new \DateTimeImmutable('2026-07-01T00:00:00+01:00'),
            issuedAt: new \DateTimeImmutable('2026-06-01T09:00:00+01:00'),
            dueAt: new \DateTimeImmutable('2026-06-11T23:59:59+01:00'),
            pricingPhase: SubscriptionPricingPhase::Promo,
            amountTnd: '50.000',
        );
    }
}

final class CapturingMerchantPaymentReminderEmailSender implements MerchantPaymentReminderEmailSenderInterface
{
    /** @var list<MerchantPaymentReminderContext> */
    public array $sent = [];

    public function send(MerchantPaymentReminderContext $context): void
    {
        $this->sent[] = $context;
    }
}
