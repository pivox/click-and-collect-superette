<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add exceptional closures for merchant operations.';
    }

    public function up(Schema $schema): void
    {
        // Timestamps are stored WITHOUT TIME ZONE, consistent with pickup_slots and other date columns
        // in this project. Tunisia (Africa/Tunis) does not observe DST — it is permanently UTC+1 —
        // so literal local-time storage is safe for overlap comparisons. All application code that
        // constructs or compares these timestamps must use the Africa/Tunis timezone explicitly.
        $this->addSql('CREATE TABLE exceptional_closures (
            id UUID NOT NULL,
            shop_id UUID NOT NULL,
            starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            ends_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            is_active BOOLEAN NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_EXCEPTIONAL_CLOSURES_SHOP ON exceptional_closures (shop_id)');
        $this->addSql('CREATE INDEX IDX_EXCEPTIONAL_CLOSURES_SHOP_STARTS_AT ON exceptional_closures (shop_id, starts_at)');
        $this->addSql('ALTER TABLE exceptional_closures ADD CONSTRAINT FK_EXCEPTIONAL_CLOSURES_SHOP FOREIGN KEY (shop_id) REFERENCES shops (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE exceptional_closures');
    }
}
