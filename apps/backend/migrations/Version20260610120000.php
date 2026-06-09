<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add push_subscriptions table (S14-003)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE push_subscriptions (
            id UUID NOT NULL,
            user_id UUID NOT NULL,
            endpoint TEXT NOT NULL,
            p256dh_key TEXT NOT NULL,
            auth_key TEXT NOT NULL,
            endpoint_hash VARCHAR(64) NOT NULL,
            scope VARCHAR(20) DEFAULT NULL,
            user_agent VARCHAR(512) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            last_success_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            last_failure_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            failure_count INT NOT NULL DEFAULT 0,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PUSH_ENDPOINT ON push_subscriptions (endpoint_hash)');
        $this->addSql('CREATE INDEX IDX_PUSH_USER ON push_subscriptions (user_id)');
        $this->addSql('CREATE INDEX IDX_PUSH_SCOPE ON push_subscriptions (scope)');
        $this->addSql('ALTER TABLE push_subscriptions ADD CONSTRAINT FK_PUSH_USER FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE push_subscriptions');
    }
}
