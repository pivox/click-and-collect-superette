<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add merchant local products and allow merchant_products to target either a product reference or a local product.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE merchant_local_products (
            id UUID NOT NULL,
            shop_id UUID NOT NULL,
            name_fr VARCHAR(255) NOT NULL,
            name_ar VARCHAR(255) DEFAULT NULL,
            brand_name VARCHAR(160) DEFAULT NULL,
            volume NUMERIC(10, 3) DEFAULT NULL,
            unit VARCHAR(32) NOT NULL,
            barcode VARCHAR(64) DEFAULT NULL,
            default_category_name VARCHAR(160) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('COMMENT ON COLUMN merchant_local_products.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN merchant_local_products.shop_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN merchant_local_products.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN merchant_local_products.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE merchant_local_products ADD CONSTRAINT FK_MERCHANT_LOCAL_PRODUCTS_SHOP FOREIGN KEY (shop_id) REFERENCES shops (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_MERCHANT_LOCAL_PRODUCTS_SHOP ON merchant_local_products (shop_id)');

        $this->addSql('ALTER TABLE merchant_products ADD local_product_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE merchant_products ALTER product_reference_id DROP NOT NULL');
        $this->addSql('COMMENT ON COLUMN merchant_products.local_product_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE merchant_products ADD CONSTRAINT FK_MERCHANT_PRODUCTS_LOCAL_PRODUCT FOREIGN KEY (local_product_id) REFERENCES merchant_local_products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_MERCHANT_PRODUCTS_LOCAL_PRODUCT ON merchant_products (local_product_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_MERCHANT_PRODUCTS_SHOP_LOCAL ON merchant_products (shop_id, local_product_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM merchant_products WHERE product_reference_id IS NULL');
        $this->addSql('DROP INDEX UNIQ_MERCHANT_PRODUCTS_SHOP_LOCAL');
        $this->addSql('DROP INDEX IDX_MERCHANT_PRODUCTS_LOCAL_PRODUCT');
        $this->addSql('ALTER TABLE merchant_products DROP CONSTRAINT FK_MERCHANT_PRODUCTS_LOCAL_PRODUCT');
        $this->addSql('ALTER TABLE merchant_products DROP local_product_id');
        $this->addSql('ALTER TABLE merchant_products ALTER product_reference_id SET NOT NULL');
        $this->addSql('ALTER TABLE merchant_local_products DROP CONSTRAINT FK_MERCHANT_LOCAL_PRODUCTS_SHOP');
        $this->addSql('DROP TABLE merchant_local_products');
    }
}
