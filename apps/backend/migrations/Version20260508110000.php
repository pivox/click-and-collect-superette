<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add merchant_products table — per-shop product catalog (price, availability, visibility).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE merchant_products (id UUID NOT NULL, shop_id UUID NOT NULL, product_reference_id UUID NOT NULL, price_tnd NUMERIC(10, 3) NOT NULL, is_available BOOLEAN NOT NULL, is_visible BOOLEAN NOT NULL, merchant_note VARCHAR(500) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_MERCHANT_PRODUCTS_SHOP_REF ON merchant_products (shop_id, product_reference_id)');
        $this->addSql('CREATE INDEX IDX_MERCHANT_PRODUCTS_SHOP ON merchant_products (shop_id)');
        $this->addSql('CREATE INDEX IDX_MERCHANT_PRODUCTS_REF ON merchant_products (product_reference_id)');
        $this->addSql('ALTER TABLE merchant_products ADD CONSTRAINT FK_MERCHANT_PRODUCTS_SHOP FOREIGN KEY (shop_id) REFERENCES shops (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE merchant_products ADD CONSTRAINT FK_MERCHANT_PRODUCTS_REF FOREIGN KEY (product_reference_id) REFERENCES product_references (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE merchant_products DROP CONSTRAINT FK_MERCHANT_PRODUCTS_SHOP');
        $this->addSql('ALTER TABLE merchant_products DROP CONSTRAINT FK_MERCHANT_PRODUCTS_REF');
        $this->addSql('DROP TABLE merchant_products');
    }
}
