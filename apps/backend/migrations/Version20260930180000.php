<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * MOBILE-PUSH #620: native mobile push devices (Expo), coexisting with the
 * PWA push_subscriptions table (no data migration).
 *
 * push_token is stored in clear — it is required to call the push provider;
 * push_token_hash (sha256) is the unique upsert key, the same motif as
 * push_subscriptions.endpoint_hash.
 *
 * Hand-written (local DB may be in drift — never regenerate with migrations:diff).
 */
final class Version20260930180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create mobile_devices table (native push device registrations, Expo tokens).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mobile_devices (
            id UUID NOT NULL,
            user_id UUID NOT NULL,
            application VARCHAR(16) NOT NULL,
            platform VARCHAR(16) NOT NULL,
            provider VARCHAR(16) NOT NULL,
            push_token TEXT NOT NULL,
            push_token_hash VARCHAR(64) NOT NULL,
            locale VARCHAR(8) NOT NULL,
            timezone VARCHAR(64) DEFAULT NULL,
            app_version VARCHAR(32) DEFAULT NULL,
            os_major_version VARCHAR(8) DEFAULT NULL,
            enabled BOOLEAN NOT NULL,
            last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            revocation_reason VARCHAR(32) DEFAULT NULL,
            last_success_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            last_failure_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            failure_count INT NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_MOBILE_DEVICES_TOKEN_HASH ON mobile_devices (push_token_hash)');
        $this->addSql('CREATE INDEX IDX_MOBILE_DEVICES_USER ON mobile_devices (user_id)');
        $this->addSql('CREATE INDEX IDX_MOBILE_DEVICES_USER_APP_ENABLED ON mobile_devices (user_id, application, enabled)');
        $this->addSql('ALTER TABLE mobile_devices ADD CONSTRAINT FK_MOBILE_DEVICES_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mobile_devices DROP CONSTRAINT FK_MOBILE_DEVICES_USER');
        $this->addSql('DROP TABLE mobile_devices');
    }
}
