<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track the last handled async Messenger message for ops monitoring.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS messenger_worker_state (
            queue_name VARCHAR(190) NOT NULL,
            last_consumed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(queue_name)
        )');
        $this->addSql("COMMENT ON COLUMN messenger_worker_state.last_consumed_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN messenger_worker_state.updated_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS messenger_worker_state');
    }
}
