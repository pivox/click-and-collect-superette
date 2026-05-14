<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create order_status_logs table (Sprint 3 — US-040 status history).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE order_status_logs (
            id UUID NOT NULL,
            order_id UUID NOT NULL,
            status VARCHAR(32) NOT NULL,
            note VARCHAR(500) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_ORDER_STATUS_LOGS_ORDER_CREATED ON order_status_logs (order_id, created_at)');
        $this->addSql('ALTER TABLE order_status_logs ADD CONSTRAINT FK_ORDER_STATUS_LOGS_ORDER FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_status_logs DROP CONSTRAINT FK_ORDER_STATUS_LOGS_ORDER');
        $this->addSql('DROP TABLE order_status_logs');
    }
}
