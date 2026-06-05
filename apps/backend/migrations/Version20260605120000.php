<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add non-fiscal monthly billing documents for merchant subscriptions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE billing_document_number_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE billing_documents (
            id UUID NOT NULL,
            subscription_id UUID NOT NULL,
            merchant_id UUID NOT NULL,
            document_number VARCHAR(32) NOT NULL,
            document_type VARCHAR(32) NOT NULL,
            status VARCHAR(20) NOT NULL,
            pricing_phase VARCHAR(20) NOT NULL,
            currency VARCHAR(3) NOT NULL,
            billing_period_start TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            billing_period_end TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            issued_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            due_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            cancellation_reason TEXT DEFAULT NULL,
            amount_tnd NUMERIC(10, 3) NOT NULL,
            amount_paid_tnd NUMERIC(10, 3) NOT NULL,
            amount_due_tnd NUMERIC(10, 3) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BILLING_DOCUMENTS_NUMBER ON billing_documents (document_number)');
        $this->addSql('CREATE INDEX IDX_BILLING_DOCUMENTS_MERCHANT ON billing_documents (merchant_id)');
        $this->addSql('CREATE INDEX IDX_BILLING_DOCUMENTS_SUBSCRIPTION ON billing_documents (subscription_id)');
        $this->addSql('CREATE INDEX IDX_BILLING_DOCUMENTS_STATUS ON billing_documents (status)');
        $this->addSql('CREATE INDEX IDX_BILLING_DOCUMENTS_ISSUED_AT ON billing_documents (issued_at)');
        $this->addSql('ALTER TABLE billing_documents ADD CONSTRAINT FK_BILLING_DOCUMENTS_SUBSCRIPTION FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE billing_documents ADD CONSTRAINT FK_BILLING_DOCUMENTS_MERCHANT FOREIGN KEY (merchant_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql("COMMENT ON COLUMN billing_documents.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN billing_documents.subscription_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN billing_documents.merchant_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN billing_documents.billing_period_start IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN billing_documents.billing_period_end IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN billing_documents.issued_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN billing_documents.due_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN billing_documents.paid_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN billing_documents.cancelled_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN billing_documents.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN billing_documents.updated_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billing_documents DROP CONSTRAINT FK_BILLING_DOCUMENTS_SUBSCRIPTION');
        $this->addSql('ALTER TABLE billing_documents DROP CONSTRAINT FK_BILLING_DOCUMENTS_MERCHANT');
        $this->addSql('DROP TABLE billing_documents');
        $this->addSql('DROP SEQUENCE billing_document_number_seq CASCADE');
    }
}
