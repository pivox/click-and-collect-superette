<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add subscription payment reminder traces for email and manual WhatsApp contacts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE subscription_payment_reminders (
            id UUID NOT NULL,
            billing_document_id UUID NOT NULL,
            subscription_id UUID NOT NULL,
            merchant_id UUID NOT NULL,
            created_by_admin_id UUID DEFAULT NULL,
            stage VARCHAR(30) NOT NULL,
            channel VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL,
            scheduled_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            failed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            failure_reason TEXT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_SUBSCRIPTION_PAYMENT_REMINDER_STAGE_CHANNEL ON subscription_payment_reminders (billing_document_id, stage, channel)');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTION_PAYMENT_REMINDER_DOCUMENT ON subscription_payment_reminders (billing_document_id)');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTION_PAYMENT_REMINDER_MERCHANT ON subscription_payment_reminders (merchant_id)');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTION_PAYMENT_REMINDER_STATUS ON subscription_payment_reminders (status)');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTION_PAYMENT_REMINDERS_SUBSCRIPTION ON subscription_payment_reminders (subscription_id)');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTION_PAYMENT_REMINDERS_ADMIN ON subscription_payment_reminders (created_by_admin_id)');
        $this->addSql('ALTER TABLE subscription_payment_reminders ADD CONSTRAINT FK_SUBSCRIPTION_PAYMENT_REMINDERS_DOCUMENT FOREIGN KEY (billing_document_id) REFERENCES billing_documents (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscription_payment_reminders ADD CONSTRAINT FK_SUBSCRIPTION_PAYMENT_REMINDERS_SUBSCRIPTION FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscription_payment_reminders ADD CONSTRAINT FK_SUBSCRIPTION_PAYMENT_REMINDERS_MERCHANT FOREIGN KEY (merchant_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscription_payment_reminders ADD CONSTRAINT FK_SUBSCRIPTION_PAYMENT_REMINDERS_ADMIN FOREIGN KEY (created_by_admin_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql("COMMENT ON COLUMN subscription_payment_reminders.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN subscription_payment_reminders.billing_document_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN subscription_payment_reminders.subscription_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN subscription_payment_reminders.merchant_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN subscription_payment_reminders.created_by_admin_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN subscription_payment_reminders.scheduled_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN subscription_payment_reminders.sent_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN subscription_payment_reminders.failed_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN subscription_payment_reminders.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN subscription_payment_reminders.updated_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription_payment_reminders DROP CONSTRAINT FK_SUBSCRIPTION_PAYMENT_REMINDERS_DOCUMENT');
        $this->addSql('ALTER TABLE subscription_payment_reminders DROP CONSTRAINT FK_SUBSCRIPTION_PAYMENT_REMINDERS_SUBSCRIPTION');
        $this->addSql('ALTER TABLE subscription_payment_reminders DROP CONSTRAINT FK_SUBSCRIPTION_PAYMENT_REMINDERS_MERCHANT');
        $this->addSql('ALTER TABLE subscription_payment_reminders DROP CONSTRAINT FK_SUBSCRIPTION_PAYMENT_REMINDERS_ADMIN');
        $this->addSql('DROP TABLE subscription_payment_reminders');
    }
}
