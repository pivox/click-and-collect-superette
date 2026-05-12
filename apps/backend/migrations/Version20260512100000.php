<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create customer_shops pivot table (Sprint 2 — US-032).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE customer_shops (
            id UUID NOT NULL,
            customer_id UUID NOT NULL,
            shop_id UUID NOT NULL,
            source VARCHAR(32) NOT NULL,
            first_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            is_favorite BOOLEAN NOT NULL DEFAULT FALSE,
            status VARCHAR(16) NOT NULL DEFAULT \'active\',
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_CUSTOMER_SHOPS_CUSTOMER_SHOP ON customer_shops (customer_id, shop_id)');
        $this->addSql('CREATE INDEX IDX_CUSTOMER_SHOPS_CUSTOMER ON customer_shops (customer_id)');
        $this->addSql('CREATE INDEX IDX_CUSTOMER_SHOPS_SHOP ON customer_shops (shop_id)');

        $this->addSql('ALTER TABLE customer_shops ADD CONSTRAINT FK_CUSTOMER_SHOPS_CUSTOMER FOREIGN KEY (customer_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE customer_shops ADD CONSTRAINT FK_CUSTOMER_SHOPS_SHOP FOREIGN KEY (shop_id) REFERENCES shops (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("ALTER TABLE customer_shops ADD CONSTRAINT CHK_CUSTOMER_SHOPS_SOURCE CHECK (source IN ('qr_code', 'search', 'manual', 'order'))");
        $this->addSql("ALTER TABLE customer_shops ADD CONSTRAINT CHK_CUSTOMER_SHOPS_STATUS CHECK (status IN ('active', 'hidden'))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_shops DROP CONSTRAINT FK_CUSTOMER_SHOPS_CUSTOMER');
        $this->addSql('ALTER TABLE customer_shops DROP CONSTRAINT FK_CUSTOMER_SHOPS_SHOP');
        $this->addSql('DROP TABLE customer_shops');
    }
}
