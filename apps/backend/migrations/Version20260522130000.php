<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create product_import_raw staging table for scraped development product observations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE product_import_raw (
            id UUID NOT NULL,
            source_name VARCHAR(100) NOT NULL,
            source_url TEXT DEFAULT NULL,
            raw_title TEXT NOT NULL,
            raw_brand VARCHAR(120) DEFAULT NULL,
            raw_quantity VARCHAR(80) DEFAULT NULL,
            raw_category VARCHAR(120) DEFAULT NULL,
            raw_payload JSON DEFAULT NULL,
            production_usable BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('COMMENT ON COLUMN product_import_raw.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN product_import_raw.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN product_import_raw.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PRODUCT_IMPORT_RAW_SOURCE_URL ON product_import_raw (source_name, source_url)');
        $this->addSql('CREATE INDEX IDX_PRODUCT_IMPORT_RAW_SOURCE ON product_import_raw (source_name)');
        $this->addSql('CREATE INDEX IDX_PRODUCT_IMPORT_RAW_PRODUCTION_USABLE ON product_import_raw (production_usable)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE product_import_raw');
    }
}
