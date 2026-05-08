<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add open_data_products table — dev seed data from Open Food Facts / Open Beauty Facts / Open Products Facts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE open_data_products (
            id UUID NOT NULL,
            barcode VARCHAR(30) NOT NULL,
            name VARCHAR(255) DEFAULT NULL,
            name_fr VARCHAR(255) DEFAULT NULL,
            name_ar VARCHAR(255) DEFAULT NULL,
            brand VARCHAR(255) DEFAULT NULL,
            category VARCHAR(255) DEFAULT NULL,
            category_fr VARCHAR(255) DEFAULT NULL,
            quantity VARCHAR(100) DEFAULT NULL,
            image_url VARCHAR(500) DEFAULT NULL,
            image_thumb_url VARCHAR(500) DEFAULT NULL,
            ingredients TEXT DEFAULT NULL,
            allergens VARCHAR(500) DEFAULT NULL,
            nutriscore VARCHAR(1) DEFAULT NULL,
            ecoscore VARCHAR(1) DEFAULT NULL,
            nutrition JSON DEFAULT NULL,
            description TEXT DEFAULT NULL,
            attributes JSON DEFAULT NULL,
            source VARCHAR(20) NOT NULL,
            type VARCHAR(20) NOT NULL,
            price_tnd NUMERIC(8, 3) DEFAULT NULL,
            stock INT NOT NULL DEFAULT 0,
            active BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ODP_BARCODE ON open_data_products (barcode)');
        $this->addSql('CREATE INDEX IDX_ODP_SOURCE ON open_data_products (source)');
        $this->addSql('CREATE INDEX IDX_ODP_ACTIVE ON open_data_products (active)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE open_data_products');
    }
}
