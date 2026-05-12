<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create pickup_slots table (Sprint 2 — SP2-007).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pickup_slots (
            id UUID NOT NULL,
            shop_id UUID NOT NULL,
            starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            ends_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            capacity INT NOT NULL,
            booked_count INT NOT NULL DEFAULT 0,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX IDX_PICKUP_SLOTS_SHOP ON pickup_slots (shop_id)');
        $this->addSql('CREATE INDEX IDX_PICKUP_SLOTS_SHOP_STARTS_AT ON pickup_slots (shop_id, starts_at)');

        $this->addSql('ALTER TABLE pickup_slots ADD CONSTRAINT FK_PICKUP_SLOTS_SHOP FOREIGN KEY (shop_id) REFERENCES shops (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE pickup_slots ADD CONSTRAINT CHK_PICKUP_SLOTS_CAPACITY CHECK (capacity >= 1)');
        $this->addSql('ALTER TABLE pickup_slots ADD CONSTRAINT CHK_PICKUP_SLOTS_BOOKED CHECK (booked_count >= 0)');
        $this->addSql('ALTER TABLE pickup_slots ADD CONSTRAINT CHK_PICKUP_SLOTS_TIMES CHECK (ends_at > starts_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pickup_slots DROP CONSTRAINT FK_PICKUP_SLOTS_SHOP');
        $this->addSql('DROP TABLE pickup_slots');
    }
}
