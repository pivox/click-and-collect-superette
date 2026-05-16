<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add recurring pickup slot rules.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pickup_slot_rules (
            id UUID NOT NULL,
            shop_id UUID NOT NULL,
            weekday SMALLINT NOT NULL,
            start_time TIME(0) WITHOUT TIME ZONE NOT NULL,
            end_time TIME(0) WITHOUT TIME ZONE NOT NULL,
            capacity INT NOT NULL,
            is_active BOOLEAN NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_PICKUP_SLOT_RULES_SHOP ON pickup_slot_rules (shop_id)');
        $this->addSql('CREATE INDEX IDX_PICKUP_SLOT_RULES_SHOP_WEEKDAY ON pickup_slot_rules (shop_id, weekday)');
        $this->addSql('ALTER TABLE pickup_slot_rules ADD CONSTRAINT FK_PICKUP_SLOT_RULES_SHOP FOREIGN KEY (shop_id) REFERENCES shops (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pickup_slot_rules');
    }
}
