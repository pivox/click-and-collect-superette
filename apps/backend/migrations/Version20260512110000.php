<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create kadhias and kadhia_lines tables (Sprint 2 — SP2-005).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE kadhias (
            id UUID NOT NULL,
            customer_id UUID NOT NULL,
            shop_id UUID NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT \'draft\',
            notes VARCHAR(500) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX IDX_KADHIAS_CUSTOMER ON kadhias (customer_id)');
        $this->addSql('CREATE INDEX IDX_KADHIAS_SHOP ON kadhias (shop_id)');
        $this->addSql('CREATE INDEX IDX_KADHIAS_STATUS ON kadhias (status)');

        $this->addSql('ALTER TABLE kadhias ADD CONSTRAINT FK_KADHIAS_CUSTOMER FOREIGN KEY (customer_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE kadhias ADD CONSTRAINT FK_KADHIAS_SHOP FOREIGN KEY (shop_id) REFERENCES shops (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql("ALTER TABLE kadhias ADD CONSTRAINT CHK_KADHIAS_STATUS CHECK (status IN ('draft', 'submitted'))");

        $this->addSql('CREATE TABLE kadhia_lines (
            id UUID NOT NULL,
            kadhia_id UUID NOT NULL,
            merchant_product_id UUID NOT NULL,
            quantity INT NOT NULL,
            unit_price_tnd NUMERIC(10, 3) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_KADHIA_LINES_KADHIA_PRODUCT ON kadhia_lines (kadhia_id, merchant_product_id)');
        $this->addSql('CREATE INDEX IDX_KADHIA_LINES_KADHIA ON kadhia_lines (kadhia_id)');

        $this->addSql('ALTER TABLE kadhia_lines ADD CONSTRAINT FK_KADHIA_LINES_KADHIA FOREIGN KEY (kadhia_id) REFERENCES kadhias (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE kadhia_lines ADD CONSTRAINT FK_KADHIA_LINES_MERCHANT_PRODUCT FOREIGN KEY (merchant_product_id) REFERENCES merchant_products (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE kadhia_lines ADD CONSTRAINT CHK_KADHIA_LINES_QUANTITY CHECK (quantity >= 1)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kadhia_lines DROP CONSTRAINT FK_KADHIA_LINES_KADHIA');
        $this->addSql('ALTER TABLE kadhia_lines DROP CONSTRAINT FK_KADHIA_LINES_MERCHANT_PRODUCT');
        $this->addSql('DROP TABLE kadhia_lines');

        $this->addSql('ALTER TABLE kadhias DROP CONSTRAINT FK_KADHIAS_CUSTOMER');
        $this->addSql('ALTER TABLE kadhias DROP CONSTRAINT FK_KADHIAS_SHOP');
        $this->addSql('DROP TABLE kadhias');
    }
}
