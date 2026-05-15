<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260515100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create pickup_sessions and notifications tables for Sprint 4 foundation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pickup_sessions (
            id UUID NOT NULL,
            order_id UUID NOT NULL,
            token UUID NOT NULL,
            generated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            scanned_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            merchant_confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            customer_confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            used BOOLEAN DEFAULT false NOT NULL,
            force_completed_by_merchant BOOLEAN DEFAULT false NOT NULL,
            force_note VARCHAR(500) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PICKUP_SESSIONS_ORDER ON pickup_sessions (order_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PICKUP_SESSIONS_TOKEN ON pickup_sessions (token)');
        $this->addSql('CREATE INDEX IDX_PICKUP_SESSIONS_EXPIRES_AT ON pickup_sessions (expires_at)');
        $this->addSql('ALTER TABLE pickup_sessions ADD CONSTRAINT FK_PICKUP_SESSIONS_ORDER FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE notifications (
            id UUID NOT NULL,
            user_id UUID NOT NULL,
            order_id UUID DEFAULT NULL,
            title_fr VARCHAR(120) NOT NULL,
            title_ar VARCHAR(120) NOT NULL,
            body_fr VARCHAR(500) NOT NULL,
            body_ar VARCHAR(500) NOT NULL,
            is_read BOOLEAN DEFAULT false NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_NOTIFICATIONS_USER_READ_CREATED ON notifications (user_id, is_read, created_at)');
        $this->addSql('CREATE INDEX IDX_NOTIFICATIONS_ORDER ON notifications (order_id)');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_NOTIFICATIONS_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_NOTIFICATIONS_ORDER FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notifications DROP CONSTRAINT FK_NOTIFICATIONS_ORDER');
        $this->addSql('ALTER TABLE notifications DROP CONSTRAINT FK_NOTIFICATIONS_USER');
        $this->addSql('DROP TABLE notifications');

        $this->addSql('ALTER TABLE pickup_sessions DROP CONSTRAINT FK_PICKUP_SESSIONS_ORDER');
        $this->addSql('DROP TABLE pickup_sessions');
    }
}
