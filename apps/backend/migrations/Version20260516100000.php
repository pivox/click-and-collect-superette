<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification type for pickup reminder idempotence and Messenger delayed transport storage.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notifications ADD type VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_NOTIFICATIONS_ORDER_TYPE ON notifications (order_id, type)');

        $this->addSql('CREATE TABLE messenger_messages (
            id BIGSERIAL NOT NULL,
            body TEXT NOT NULL,
            headers TEXT NOT NULL,
            queue_name VARCHAR(190) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_MESSENGER_MESSAGES_QUEUE_AVAILABLE ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('DROP INDEX UNIQ_NOTIFICATIONS_ORDER_TYPE');
        $this->addSql('ALTER TABLE notifications DROP type');
    }
}
