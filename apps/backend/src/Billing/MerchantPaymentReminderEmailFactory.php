<?php

declare(strict_types=1);

namespace App\Billing;

use App\Email\TransactionalEmail;

final class MerchantPaymentReminderEmailFactory
{
    public function create(MerchantPaymentReminderContext $context): TransactionalEmail
    {
        $dueDate = $context->dueDate->format('d/m/Y');
        $stageCopy = $this->stageCopy($context->stage);
        $subject = $this->subject($context->stage);
        $merchantName = '' !== trim($context->merchantName) ? $context->merchantName : 'cher marchand';

        $text = \sprintf(
            "Bonjour %s,\n\n%s\n\nSupérette : %s\nMontant à régler : %s TND\nÉchéance : %s\n\nCe message concerne votre abonnement plateforme marchand Kadhia.\n\nMerci de contacter l'équipe Kadhia après règlement manuel pour validation.\n\nL'équipe Kadhia",
            $merchantName,
            $stageCopy,
            $context->shopName,
            $context->amountTnd,
            $dueDate,
        );

        return new TransactionalEmail(
            recipients: [$context->merchantEmail],
            subject: $subject,
            text: $text,
        );
    }

    private function subject(PaymentReminderStage $stage): string
    {
        return match ($stage) {
            PaymentReminderStage::BeforeDueDate => '[Kadhia] Rappel abonnement Kadhia',
            PaymentReminderStage::DueDate => "[Kadhia] Abonnement Kadhia à régler aujourd'hui",
            PaymentReminderStage::GracePeriod7 => '[Kadhia] Relance abonnement Kadhia - J+7',
            PaymentReminderStage::GracePeriod14 => '[Kadhia] Relance abonnement Kadhia - J+14',
            PaymentReminderStage::SuspensionWarning21 => '[Kadhia] Dernière relance abonnement Kadhia',
        };
    }

    private function stageCopy(PaymentReminderStage $stage): string
    {
        return match ($stage) {
            PaymentReminderStage::BeforeDueDate => 'Votre prochaine échéance arrive dans 7 jours.',
            PaymentReminderStage::DueDate => "Votre échéance d'abonnement arrive aujourd'hui.",
            PaymentReminderStage::GracePeriod7 => "Votre échéance d'abonnement est dépassée depuis 7 jours.",
            PaymentReminderStage::GracePeriod14 => "Votre échéance d'abonnement est dépassée depuis 14 jours.",
            PaymentReminderStage::SuspensionWarning21 => 'Dernière relance : votre échéance est dépassée depuis 21 jours après échéance. Une suspension douce pourra être appliquée si le règlement reste absent.',
        };
    }
}
