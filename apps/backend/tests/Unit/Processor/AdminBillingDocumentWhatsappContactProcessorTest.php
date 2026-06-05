<?php

declare(strict_types=1);

namespace App\Tests\Unit\Processor;

use ApiPlatform\Metadata\Post;
use App\Entity\AdminAuditLog;
use App\Entity\BillingDocument;
use App\Entity\Subscription;
use App\Entity\SubscriptionPaymentReminder;
use App\Entity\User;
use App\Enum\SubscriptionPaymentReminderChannel;
use App\Enum\SubscriptionPricingPhase;
use App\Processor\AdminBillingDocumentWhatsappContactProcessor;
use App\Repository\BillingDocumentRepository;
use App\Repository\SubscriptionPaymentReminderRepository;
use App\Service\AdminAuditLogger;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RequestStack;

final class AdminBillingDocumentWhatsappContactProcessorTest extends TestCase
{
    public function testProcessorRefreshesDocumentBeforeTracingWhatsappContactAndAuditLog(): void
    {
        $events = [];
        $admin = (new User())
            ->setEmail('admin@example.test')
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword('test-password')
            ->setName('Admin');
        $document = $this->document();
        $documentId = $document->getId()->toRfc4122();
        $billingDocumentRepository = $this->createMock(BillingDocumentRepository::class);
        $billingDocumentRepository
            ->expects(self::once())
            ->method('find')
            ->with($documentId)
            ->willReturn($document);
        $reminderRepository = $this->createMock(SubscriptionPaymentReminderRepository::class);
        $reminderRepository
            ->expects(self::once())
            ->method('findOneForDocumentStageChannel')
            ->with($document, 'j_plus_7', SubscriptionPaymentReminderChannel::WhatsappManual)
            ->willReturnCallback(static function () use (&$events): ?SubscriptionPaymentReminder {
                self::assertContains('subscription-refresh', $events);
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
            ->expects(self::exactly(2))
            ->method('refresh')
            ->willReturnCallback(static function (object $entity, LockMode $lockMode) use ($document, &$events): void {
                self::assertSame(LockMode::PESSIMISTIC_WRITE, $lockMode);
                if ($entity === $document) {
                    self::assertContains('begin', $events);
                    $events[] = 'document-refresh';

                    return;
                }

                self::assertSame($document->getSubscription(), $entity);
                self::assertContains('document-refresh', $events);
                $events[] = 'subscription-refresh';
            });
        $entityManager
            ->expects(self::exactly(2))
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$events): void {
                $events[] = $entity instanceof AdminAuditLog ? 'audit-persist' : 'reminder-persist';
            });
        $entityManager
            ->expects(self::once())
            ->method('flush')
            ->willReturnCallback(static function () use (&$events): void {
                self::assertContains('audit-persist', $events);
                $events[] = 'flush';
            });
        $entityManager
            ->expects(self::once())
            ->method('commit')
            ->willReturnCallback(static function () use (&$events): void {
                self::assertContains('flush', $events);
                $events[] = 'commit';
            });
        $security = $this->createMock(Security::class);
        $security
            ->expects(self::exactly(2))
            ->method('getUser')
            ->willReturn($admin);
        $processor = new AdminBillingDocumentWhatsappContactProcessor(
            $billingDocumentRepository,
            $reminderRepository,
            $entityManager,
            $security,
            new MockClock(new \DateTimeImmutable('2026-06-18T10:00:00+01:00')),
            new AdminAuditLogger($entityManager, $security, new RequestStack()),
        );

        $output = $processor->process(
            null,
            new Post(),
            ['billingDocumentId' => $documentId],
        );

        self::assertSame($documentId, $output->billingDocumentId);
        self::assertSame('21620123456', $output->phone);
        self::assertSame(['begin', 'document-refresh', 'subscription-refresh', 'trace-read', 'reminder-persist', 'audit-persist', 'flush', 'commit'], $events);
    }

    private function document(): BillingDocument
    {
        $merchant = (new User())
            ->setEmail('marchand@example.test')
            ->setRoles(['ROLE_MERCHANT'])
            ->setPassword('test-password')
            ->setName('Sami')
            ->setPhone('+216 20 123 456');
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
