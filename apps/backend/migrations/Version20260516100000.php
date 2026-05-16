<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification type for pickup reminder idempotence.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notifications ADD type VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_NOTIFICATIONS_ORDER_TYPE ON notifications (order_id, type)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_NOTIFICATIONS_ORDER_TYPE');
        $this->addSql('ALTER TABLE notifications DROP type');
    }
}
