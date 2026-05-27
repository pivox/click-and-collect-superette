<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260527100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create messenger_messages table for persistent async transport (Symfony Messenger Doctrine transport).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS messenger_messages (
            id BIGSERIAL NOT NULL,
            body TEXT NOT NULL,
            headers TEXT NOT NULL,
            queue_name VARCHAR(190) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql("COMMENT ON COLUMN messenger_messages.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN messenger_messages.available_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN messenger_messages.delivered_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_MESSENGER_MESSAGES_QUEUE_NAME ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_MESSENGER_MESSAGES_AVAILABLE_AT ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_MESSENGER_MESSAGES_DELIVERED_AT ON messenger_messages (delivered_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_MESSENGER_MESSAGES_QUEUE_AVAILABLE ON messenger_messages (queue_name, available_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
    }
}
