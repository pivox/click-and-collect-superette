<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create product_packs and product_pack_items tables for S15-002';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE product_packs (id UUID NOT NULL, shop_id UUID NOT NULL, name_fr VARCHAR(255) NOT NULL, name_ar VARCHAR(255) DEFAULT NULL, description VARCHAR(1000) DEFAULT NULL, is_active BOOLEAN DEFAULT true NOT NULL, created_at TIMESTAMP(0) NOT NULL, updated_at TIMESTAMP(0) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_PRODUCT_PACKS_SHOP ON product_packs (shop_id)');
        $this->addSql('COMMENT ON COLUMN product_packs.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN product_packs.shop_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN product_packs.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN product_packs.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE product_pack_items (id UUID NOT NULL, pack_id UUID NOT NULL, merchant_product_id UUID NOT NULL, quantity INT DEFAULT 1 NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_PRODUCT_PACK_ITEMS_PACK ON product_pack_items (pack_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PRODUCT_PACK_ITEMS_PACK_PRODUCT ON product_pack_items (pack_id, merchant_product_id)');
        $this->addSql('COMMENT ON COLUMN product_pack_items.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN product_pack_items.pack_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN product_pack_items.merchant_product_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE product_packs ADD CONSTRAINT FK_PRODUCT_PACKS_SHOP FOREIGN KEY (shop_id) REFERENCES shops (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_pack_items ADD CONSTRAINT FK_PRODUCT_PACK_ITEMS_PACK FOREIGN KEY (pack_id) REFERENCES product_packs (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_pack_items ADD CONSTRAINT FK_PRODUCT_PACK_ITEMS_MERCHANT_PRODUCT FOREIGN KEY (merchant_product_id) REFERENCES merchant_products (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_pack_items DROP CONSTRAINT FK_PRODUCT_PACK_ITEMS_PACK');
        $this->addSql('ALTER TABLE product_pack_items DROP CONSTRAINT FK_PRODUCT_PACK_ITEMS_MERCHANT_PRODUCT');
        $this->addSql('ALTER TABLE product_packs DROP CONSTRAINT FK_PRODUCT_PACKS_SHOP');
        $this->addSql('DROP TABLE product_pack_items');
        $this->addSql('DROP TABLE product_packs');
    }
}
