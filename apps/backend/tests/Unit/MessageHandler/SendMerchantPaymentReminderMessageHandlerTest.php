<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Billing\MerchantPaymentReminderContext;
use App\Billing\MerchantPaymentReminderEmailSenderInterface;
use App\Billing\PaymentReminderStage;
use App\Message\SendMerchantPaymentReminderMessage;
use App\MessageHandler\SendMerchantPaymentReminderMessageHandler;
use App\Repository\BillingDocumentRepository;
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

        $handler = new SendMerchantPaymentReminderMessageHandler(
            $sender,
            $documentRepository,
            $entityManager,
            new MockClock(new \DateTimeImmutable('2026-06-18T10:00:00+01:00')),
        );

        $handler(new SendMerchantPaymentReminderMessage(
            billingDocumentId: 'not-a-uuid',
            documentNumber: 'MS-2026-000401',
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
