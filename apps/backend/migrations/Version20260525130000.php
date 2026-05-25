<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add merchant catalogue categories scoped by shop.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE merchant_categories (
            id UUID NOT NULL,
            shop_id UUID NOT NULL,
            parent_id UUID DEFAULT NULL,
            name_fr VARCHAR(160) NOT NULL,
            slug VARCHAR(180) NOT NULL,
            name_ar VARCHAR(160) DEFAULT NULL,
            sort_order INT NOT NULL,
            active BOOLEAN NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('COMMENT ON COLUMN merchant_categories.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN merchant_categories.shop_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN merchant_categories.parent_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN merchant_categories.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN merchant_categories.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE merchant_categories ADD CONSTRAINT FK_MERCHANT_CATEGORIES_SHOP FOREIGN KEY (shop_id) REFERENCES shops (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE merchant_categories ADD CONSTRAINT FK_MERCHANT_CATEGORIES_PARENT FOREIGN KEY (parent_id) REFERENCES merchant_categories (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_MERCHANT_CATEGORIES_SHOP ON merchant_categories (shop_id)');
        $this->addSql('CREATE INDEX IDX_MERCHANT_CATEGORIES_PARENT ON merchant_categories (parent_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_MERCHANT_CATEGORIES_SHOP_NAME_FR ON merchant_categories (shop_id, name_fr)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_MERCHANT_CATEGORIES_SHOP_SLUG ON merchant_categories (shop_id, slug)');

        $this->addSql('ALTER TABLE merchant_products ADD merchant_category_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN merchant_products.merchant_category_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE merchant_products ADD CONSTRAINT FK_MERCHANT_PRODUCTS_MERCHANT_CATEGORY FOREIGN KEY (merchant_category_id) REFERENCES merchant_categories (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_MERCHANT_PRODUCTS_MERCHANT_CATEGORY ON merchant_products (merchant_category_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE merchant_products DROP CONSTRAINT FK_MERCHANT_PRODUCTS_MERCHANT_CATEGORY');
        $this->addSql('DROP INDEX IDX_MERCHANT_PRODUCTS_MERCHANT_CATEGORY');
        $this->addSql('ALTER TABLE merchant_products DROP merchant_category_id');

        $this->addSql('ALTER TABLE merchant_categories DROP CONSTRAINT FK_MERCHANT_CATEGORIES_PARENT');
        $this->addSql('ALTER TABLE merchant_categories DROP CONSTRAINT FK_MERCHANT_CATEGORIES_SHOP');
        $this->addSql('DROP TABLE merchant_categories');
    }
}
