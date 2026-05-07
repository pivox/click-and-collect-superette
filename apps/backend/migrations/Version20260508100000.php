<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add brands, categories and product_references tables (product reference foundation).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE brands (id UUID NOT NULL, canonical_name VARCHAR(160) NOT NULL, slug VARCHAR(180) NOT NULL, aliases JSON NOT NULL, country VARCHAR(2) DEFAULT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BRANDS_SLUG ON brands (slug)');

        $this->addSql('CREATE TABLE categories (id UUID NOT NULL, parent_id UUID DEFAULT NULL, name_fr VARCHAR(160) NOT NULL, name_ar VARCHAR(160) DEFAULT NULL, slug VARCHAR(180) NOT NULL, sort_order INT NOT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CATEGORIES_SLUG ON categories (slug)');
        $this->addSql('CREATE INDEX IDX_CATEGORIES_PARENT ON categories (parent_id)');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT FK_CATEGORIES_PARENT FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE product_references (id UUID NOT NULL, brand_id UUID NOT NULL, category_id UUID NOT NULL, name_fr VARCHAR(255) NOT NULL, name_ar VARCHAR(255) DEFAULT NULL, variant_fr VARCHAR(160) DEFAULT NULL, variant_ar VARCHAR(160) DEFAULT NULL, volume NUMERIC(10, 3) DEFAULT NULL, unit VARCHAR(32) NOT NULL, barcode VARCHAR(64) DEFAULT NULL, aliases JSON NOT NULL, country VARCHAR(2) NOT NULL, status VARCHAR(32) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PRODUCT_REFERENCES_BARCODE ON product_references (barcode)');
        $this->addSql('CREATE INDEX IDX_PRODUCT_REFERENCES_BRAND ON product_references (brand_id)');
        $this->addSql('CREATE INDEX IDX_PRODUCT_REFERENCES_CATEGORY ON product_references (category_id)');
        $this->addSql('ALTER TABLE product_references ADD CONSTRAINT FK_PRODUCT_REFERENCES_BRAND FOREIGN KEY (brand_id) REFERENCES brands (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_references ADD CONSTRAINT FK_PRODUCT_REFERENCES_CATEGORY FOREIGN KEY (category_id) REFERENCES categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql("ALTER TABLE product_references ADD CONSTRAINT product_reference_unit_allowed CHECK (unit IN ('litre', 'millilitre', 'kilogramme', 'gramme', 'piece', 'paquet'))");
        $this->addSql("ALTER TABLE product_references ADD CONSTRAINT product_reference_status_allowed CHECK (status IN ('draft', 'pending_review', 'approved', 'rejected', 'archived'))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_references DROP CONSTRAINT FK_PRODUCT_REFERENCES_BRAND');
        $this->addSql('ALTER TABLE product_references DROP CONSTRAINT FK_PRODUCT_REFERENCES_CATEGORY');
        $this->addSql('ALTER TABLE categories DROP CONSTRAINT FK_CATEGORIES_PARENT');
        $this->addSql('DROP TABLE product_references');
        $this->addSql('DROP TABLE categories');
        $this->addSql('DROP TABLE brands');
    }
}
