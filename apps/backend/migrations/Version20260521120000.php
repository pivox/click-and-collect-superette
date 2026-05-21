<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create admin_audit_logs table for S7-004 audit trail.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE admin_audit_logs (
            id UUID NOT NULL,
            admin_user_id UUID NOT NULL,
            action VARCHAR(100) NOT NULL,
            resource_type VARCHAR(100) NOT NULL,
            resource_id VARCHAR(36) NOT NULL,
            metadata JSON DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('COMMENT ON COLUMN admin_audit_logs.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN admin_audit_logs.admin_user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE admin_audit_logs ADD CONSTRAINT FK_ADMIN_AUDIT_LOGS_USER FOREIGN KEY (admin_user_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_ADMIN_AUDIT_LOGS_ACTION ON admin_audit_logs (action)');
        $this->addSql('CREATE INDEX IDX_ADMIN_AUDIT_LOGS_RESOURCE ON admin_audit_logs (resource_type, resource_id)');
        $this->addSql('CREATE INDEX IDX_ADMIN_AUDIT_LOGS_CREATED_AT ON admin_audit_logs (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_audit_logs DROP CONSTRAINT FK_ADMIN_AUDIT_LOGS_USER');
        $this->addSql('DROP TABLE admin_audit_logs');
    }
}
