<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add manual subscription payments linked to monthly billing documents.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE subscription_payments (
            id UUID NOT NULL,
            billing_document_id UUID NOT NULL,
            subscription_id UUID NOT NULL,
            merchant_id UUID NOT NULL,
            created_by_admin_id UUID NOT NULL,
            validated_by_admin_id UUID NOT NULL,
            amount_tnd NUMERIC(10, 3) NOT NULL,
            currency VARCHAR(3) NOT NULL,
            period_start TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            period_end TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            method VARCHAR(20) NOT NULL,
            reference VARCHAR(120) DEFAULT NULL,
            paid_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            status VARCHAR(20) NOT NULL,
            validated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTION_PAYMENTS_DOCUMENT ON subscription_payments (billing_document_id)');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTION_PAYMENTS_SUBSCRIPTION ON subscription_payments (subscription_id)');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTION_PAYMENTS_MERCHANT ON subscription_payments (merchant_id)');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTION_PAYMENTS_STATUS ON subscription_payments (status)');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTION_PAYMENTS_CREATED_BY_ADMIN ON subscription_payments (created_by_admin_id)');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTION_PAYMENTS_VALIDATED_BY_ADMIN ON subscription_payments (validated_by_admin_id)');
        $this->addSql('ALTER TABLE subscription_payments ADD CONSTRAINT FK_SUBSCRIPTION_PAYMENTS_DOCUMENT FOREIGN KEY (billing_document_id) REFERENCES billing_documents (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscription_payments ADD CONSTRAINT FK_SUBSCRIPTION_PAYMENTS_SUBSCRIPTION FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscription_payments ADD CONSTRAINT FK_SUBSCRIPTION_PAYMENTS_MERCHANT FOREIGN KEY (merchant_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscription_payments ADD CONSTRAINT FK_SUBSCRIPTION_PAYMENTS_CREATED_BY_ADMIN FOREIGN KEY (created_by_admin_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscription_payments ADD CONSTRAINT FK_SUBSCRIPTION_PAYMENTS_VALIDATED_BY_ADMIN FOREIGN KEY (validated_by_admin_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql("COMMENT ON COLUMN subscription_payments.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN subscription_payments.billing_document_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN subscription_payments.subscription_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN subscription_payments.merchant_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN subscription_payments.created_by_admin_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN subscription_payments.validated_by_admin_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN subscription_payments.period_start IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN subscription_payments.period_end IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN subscription_payments.paid_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN subscription_payments.validated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN subscription_payments.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN subscription_payments.updated_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription_payments DROP CONSTRAINT FK_SUBSCRIPTION_PAYMENTS_DOCUMENT');
        $this->addSql('ALTER TABLE subscription_payments DROP CONSTRAINT FK_SUBSCRIPTION_PAYMENTS_SUBSCRIPTION');
        $this->addSql('ALTER TABLE subscription_payments DROP CONSTRAINT FK_SUBSCRIPTION_PAYMENTS_MERCHANT');
        $this->addSql('ALTER TABLE subscription_payments DROP CONSTRAINT FK_SUBSCRIPTION_PAYMENTS_CREATED_BY_ADMIN');
        $this->addSql('ALTER TABLE subscription_payments DROP CONSTRAINT FK_SUBSCRIPTION_PAYMENTS_VALIDATED_BY_ADMIN');
        $this->addSql('DROP TABLE subscription_payments');
    }
}
