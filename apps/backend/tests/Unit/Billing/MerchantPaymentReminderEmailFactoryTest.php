<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing;

use App\Billing\MerchantPaymentReminderContext;
use App\Billing\MerchantPaymentReminderEmailFactory;
use App\Billing\PaymentReminderStage;
use PHPUnit\Framework\TestCase;

final class MerchantPaymentReminderEmailFactoryTest extends TestCase
{
    public function testCreatesMerchantOrientedFrenchPaymentReminderEmail(): void
    {
        $email = (new MerchantPaymentReminderEmailFactory())->create(new MerchantPaymentReminderContext(
            merchantEmail: 'marchand@example.test',
            merchantName: 'Sami',
            shopName: 'Supérette El Amal',
            dueDate: new \DateTimeImmutable('2026-06-11 09:00:00'),
            amountTnd: '50.000',
            stage: PaymentReminderStage::BeforeDueDate,
        ));

        self::assertSame(['marchand@example.test'], $email->recipients);
        self::assertStringContainsString('abonnement Kadhia', $email->subject);
        self::assertStringContainsString('Bonjour Sami', $email->text);
        self::assertStringContainsString('Supérette El Amal', $email->text);
        self::assertStringContainsString('50.000 TND', $email->text);
        self::assertStringContainsString('11/06/2026', $email->text);
        self::assertStringContainsString('abonnement plateforme marchand', $email->text);
        self::assertStringNotContainsString('commande client', $email->text);
        self::assertStringNotContainsString('paiement en ligne', $email->text);
    }

    public function testCreatesSuspensionWarningCopyAtJ21(): void
    {
        $email = (new MerchantPaymentReminderEmailFactory())->create(new MerchantPaymentReminderContext(
            merchantEmail: 'marchand@example.test',
            merchantName: 'Sami',
            shopName: 'Supérette El Amal',
            dueDate: new \DateTimeImmutable('2026-06-11 09:00:00'),
            amountTnd: '50.000',
            stage: PaymentReminderStage::SuspensionWarning21,
        ));

        self::assertStringContainsString('Dernière relance', $email->subject);
        self::assertStringContainsString('21 jours après échéance', $email->text);
        self::assertStringContainsString('suspension douce', $email->text);
    }
}
