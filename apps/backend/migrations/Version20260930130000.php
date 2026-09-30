<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * MERCHANT-TEAM-005: notification fan-out per account and transition author.
 *
 * - notifications: uniqueness moves from (order, type) to (order, type, user)
 *   so every account of an organization gets its own row (own read state)
 *   while a retried event stays idempotent per account. Existing rows all
 *   satisfy the wider constraint — no data change.
 * - order_status_logs: nullable actor columns (historical rows stay null);
 *   actor_user_id is SET NULL on user removal, actor_type remains as the
 *   durable trace.
 */
final class Version20260930130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Per-recipient merchant notifications and order status transition author';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_notifications_order_type');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_NOTIFICATIONS_ORDER_TYPE_USER ON notifications (order_id, type, user_id)');

        $this->addSql('ALTER TABLE order_status_logs ADD actor_type VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE order_status_logs ADD actor_user_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE order_status_logs ADD CONSTRAINT FK_D33B144E859B83FF FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_D33B144E859B83FF ON order_status_logs (actor_user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_status_logs DROP CONSTRAINT FK_D33B144E859B83FF');
        $this->addSql('DROP INDEX IDX_D33B144E859B83FF');
        $this->addSql('ALTER TABLE order_status_logs DROP actor_user_id');
        $this->addSql('ALTER TABLE order_status_logs DROP actor_type');

        $this->addSql('DROP INDEX UNIQ_NOTIFICATIONS_ORDER_TYPE_USER');
        $this->addSql('CREATE UNIQUE INDEX uniq_notifications_order_type ON notifications (order_id, type)');
    }
}
