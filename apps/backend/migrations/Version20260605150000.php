<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add structured order incidents for support tracking.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE incidents (
            id UUID NOT NULL,
            order_id UUID NOT NULL,
            merchant_id UUID NOT NULL,
            customer_id UUID NOT NULL,
            type VARCHAR(40) NOT NULL,
            status VARCHAR(20) NOT NULL,
            occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_INCIDENTS_ORDER ON incidents (order_id)');
        $this->addSql('CREATE INDEX IDX_INCIDENTS_MERCHANT ON incidents (merchant_id)');
        $this->addSql('CREATE INDEX IDX_INCIDENTS_CUSTOMER ON incidents (customer_id)');
        $this->addSql('CREATE INDEX IDX_INCIDENTS_STATUS ON incidents (status)');
        $this->addSql('CREATE INDEX IDX_INCIDENTS_CREATED_AT ON incidents (created_at)');
        $this->addSql('ALTER TABLE incidents ADD CONSTRAINT FK_INCIDENTS_ORDER FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE incidents ADD CONSTRAINT FK_INCIDENTS_MERCHANT FOREIGN KEY (merchant_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE incidents ADD CONSTRAINT FK_INCIDENTS_CUSTOMER FOREIGN KEY (customer_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql("COMMENT ON COLUMN incidents.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN incidents.order_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN incidents.merchant_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN incidents.customer_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN incidents.occurred_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN incidents.closed_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN incidents.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN incidents.updated_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE incidents DROP CONSTRAINT FK_INCIDENTS_ORDER');
        $this->addSql('ALTER TABLE incidents DROP CONSTRAINT FK_INCIDENTS_MERCHANT');
        $this->addSql('ALTER TABLE incidents DROP CONSTRAINT FK_INCIDENTS_CUSTOMER');
        $this->addSql('DROP TABLE incidents');
    }
}
