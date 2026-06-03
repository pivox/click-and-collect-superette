<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260528072120 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Drop FK and column before dropping the referenced table (order matters in PostgreSQL)
        $this->addSql('ALTER TABLE product_references DROP CONSTRAINT IF EXISTS fk_product_references_source_import_raw');
        $this->addSql('DROP INDEX IF EXISTS uniq_product_references_source_import_raw');
        $this->addSql('ALTER TABLE product_references DROP COLUMN IF EXISTS source_import_raw_id');
        $this->addSql('DROP TABLE IF EXISTS product_import_raw');
        $this->addSql('COMMENT ON COLUMN admin_audit_logs.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN admin_audit_logs.admin_user_id IS \'\'');
        $this->addSql('COMMENT ON COLUMN admin_audit_logs.created_at IS \'\'');
        $this->addSql('ALTER INDEX uniq_brands_slug RENAME TO UNIQ_7EA24434989D9B62');
        $this->addSql('ALTER INDEX uniq_categories_slug RENAME TO UNIQ_3AF34668989D9B62');
        $this->addSql('ALTER INDEX idx_categories_parent RENAME TO IDX_3AF34668727ACA70');
        $this->addSql('ALTER TABLE customer_shops ALTER is_favorite DROP DEFAULT');
        $this->addSql('ALTER TABLE customer_shops ALTER status DROP DEFAULT');
        $this->addSql('ALTER TABLE kadhias ALTER status DROP DEFAULT');
        $this->addSql('COMMENT ON COLUMN merchant_categories.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN merchant_categories.shop_id IS \'\'');
        $this->addSql('COMMENT ON COLUMN merchant_categories.parent_id IS \'\'');
        $this->addSql('COMMENT ON COLUMN merchant_categories.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN merchant_categories.updated_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN merchant_local_products.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN merchant_local_products.shop_id IS \'\'');
        $this->addSql('COMMENT ON COLUMN merchant_local_products.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN merchant_local_products.updated_at IS \'\'');
        $this->addSql('DROP INDEX idx_merchant_products_local_product');
        $this->addSql('COMMENT ON COLUMN merchant_products.local_product_id IS \'\'');
        $this->addSql('COMMENT ON COLUMN merchant_products.merchant_category_id IS \'\'');
        $this->addSql('ALTER INDEX idx_merchant_products_shop RENAME TO IDX_D4BE2EE34D16C4DD');
        $this->addSql('ALTER INDEX idx_merchant_products_ref RENAME TO IDX_D4BE2EE39BE1FCC2');
        $this->addSql('ALTER INDEX idx_merchant_products_merchant_category RENAME TO IDX_D4BE2EE394F720F1');
        $this->addSql('ALTER TABLE notifications ALTER is_read DROP DEFAULT');
        $this->addSql('DROP INDEX idx_odp_source');
        $this->addSql('DROP INDEX idx_odp_active');
        $this->addSql('ALTER TABLE open_data_products ALTER stock DROP DEFAULT');
        $this->addSql('ALTER TABLE open_data_products ALTER active DROP DEFAULT');
        $this->addSql('ALTER INDEX uniq_odp_barcode RENAME TO UNIQ_F9C1CA6E97AE0266');
        $this->addSql('ALTER TABLE orders ALTER total_tnd DROP DEFAULT');
        $this->addSql('ALTER INDEX uniq_password_reset_token_hash RENAME TO UNIQ_3967A216B3BC57DA');
        $this->addSql('ALTER TABLE pickup_sessions ALTER used DROP DEFAULT');
        $this->addSql('ALTER TABLE pickup_sessions ALTER force_completed_by_merchant DROP DEFAULT');
        $this->addSql('ALTER TABLE pickup_slots ALTER booked_count DROP DEFAULT');
        $this->addSql('ALTER TABLE pickup_slots ALTER is_active DROP DEFAULT');
        $this->addSql('DROP INDEX idx_proposals_status');
        $this->addSql('ALTER TABLE product_reference_proposals ALTER status DROP DEFAULT');
        $this->addSql('ALTER INDEX idx_proposals_proposed_by RENAME TO IDX_3C99B7EBDAB5A938');
        $this->addSql('ALTER INDEX idx_proposals_shop RENAME TO IDX_3C99B7EB4D16C4DD');
        $this->addSql('ALTER INDEX idx_prp_created_ref RENAME TO IDX_3C99B7EB4775788C');
        $this->addSql('ALTER INDEX uniq_product_references_barcode RENAME TO UNIQ_C7972B797AE0266');
        $this->addSql('ALTER INDEX idx_product_references_brand RENAME TO IDX_C7972B744F5D008');
        $this->addSql('ALTER INDEX idx_product_references_category RENAME TO IDX_C7972B712469DE2');
        $this->addSql('ALTER INDEX uniq_5e1f8d5b4d16c4dd RENAME TO UNIQ_90E0145C4D16C4DD');
        $this->addSql('ALTER INDEX uniq_6f6a974989d9b62 RENAME TO UNIQ_237A6783989D9B62');
        $this->addSql('ALTER INDEX uniq_6f6a9749b03a8386 RENAME TO UNIQ_237A67831BC9050B');
        $this->addSql('ALTER INDEX idx_6f6a97497e3c61f9 RENAME TO IDX_237A67837E3C61F9');
        $this->addSql('ALTER INDEX uniq_users_email RENAME TO UNIQ_1483A5E9E7927C74');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE product_import_raw (id UUID NOT NULL, source_name VARCHAR(100) NOT NULL, source_url TEXT DEFAULT NULL, raw_title TEXT NOT NULL, raw_brand VARCHAR(120) DEFAULT NULL, raw_quantity VARCHAR(80) DEFAULT NULL, raw_category VARCHAR(120) DEFAULT NULL, raw_payload JSON DEFAULT NULL, production_usable BOOLEAN DEFAULT false NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_product_import_raw_source ON product_import_raw (source_name)');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_import_raw_source_url ON product_import_raw (source_name, source_url)');
        $this->addSql('CREATE INDEX idx_product_import_raw_production_usable ON product_import_raw (production_usable)');
        $this->addSql('COMMENT ON COLUMN product_import_raw.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN product_import_raw.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN product_import_raw.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN admin_audit_logs.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN admin_audit_logs.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN admin_audit_logs.admin_user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER INDEX uniq_7ea24434989d9b62 RENAME TO uniq_brands_slug');
        $this->addSql('ALTER INDEX idx_3af34668727aca70 RENAME TO idx_categories_parent');
        $this->addSql('ALTER INDEX uniq_3af34668989d9b62 RENAME TO uniq_categories_slug');
        $this->addSql('ALTER TABLE customer_shops ALTER is_favorite SET DEFAULT false');
        $this->addSql('ALTER TABLE customer_shops ALTER status SET DEFAULT \'active\'');
        $this->addSql('ALTER TABLE kadhias ALTER status SET DEFAULT \'draft\'');
        $this->addSql('COMMENT ON COLUMN merchant_categories.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN merchant_categories.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN merchant_categories.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN merchant_categories.shop_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN merchant_categories.parent_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN merchant_local_products.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN merchant_local_products.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN merchant_local_products.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN merchant_local_products.shop_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN merchant_products.local_product_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN merchant_products.merchant_category_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE INDEX idx_merchant_products_local_product ON merchant_products (local_product_id)');
        $this->addSql('ALTER INDEX idx_d4be2ee394f720f1 RENAME TO idx_merchant_products_merchant_category');
        $this->addSql('ALTER INDEX idx_d4be2ee34d16c4dd RENAME TO idx_merchant_products_shop');
        $this->addSql('ALTER INDEX idx_d4be2ee39be1fcc2 RENAME TO idx_merchant_products_ref');
        $this->addSql('ALTER TABLE notifications ALTER is_read SET DEFAULT false');
        $this->addSql('ALTER TABLE open_data_products ALTER stock SET DEFAULT 0');
        $this->addSql('ALTER TABLE open_data_products ALTER active SET DEFAULT false');
        $this->addSql('CREATE INDEX idx_odp_source ON open_data_products (source)');
        $this->addSql('CREATE INDEX idx_odp_active ON open_data_products (active)');
        $this->addSql('ALTER INDEX uniq_f9c1ca6e97ae0266 RENAME TO uniq_odp_barcode');
        $this->addSql('ALTER TABLE orders ALTER total_tnd SET DEFAULT \'0\'');
        $this->addSql('ALTER INDEX uniq_3967a216b3bc57da RENAME TO uniq_password_reset_token_hash');
        $this->addSql('ALTER TABLE pickup_sessions ALTER used SET DEFAULT false');
        $this->addSql('ALTER TABLE pickup_sessions ALTER force_completed_by_merchant SET DEFAULT false');
        $this->addSql('ALTER TABLE pickup_slots ALTER booked_count SET DEFAULT 0');
        $this->addSql('ALTER TABLE pickup_slots ALTER is_active SET DEFAULT true');
        $this->addSql('ALTER TABLE product_reference_proposals ALTER status SET DEFAULT \'pending\'');
        $this->addSql('CREATE INDEX idx_proposals_status ON product_reference_proposals (status)');
        $this->addSql('ALTER INDEX idx_3c99b7eb4d16c4dd RENAME TO idx_proposals_shop');
        $this->addSql('ALTER INDEX idx_3c99b7eb4775788c RENAME TO idx_prp_created_ref');
        $this->addSql('ALTER INDEX idx_3c99b7ebdab5a938 RENAME TO idx_proposals_proposed_by');
        $this->addSql('ALTER TABLE product_references ADD source_import_raw_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN product_references.source_import_raw_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE product_references ADD CONSTRAINT fk_product_references_source_import_raw FOREIGN KEY (source_import_raw_id) REFERENCES product_import_raw (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_references_source_import_raw ON product_references (source_import_raw_id)');
        $this->addSql('ALTER INDEX uniq_c7972b797ae0266 RENAME TO uniq_product_references_barcode');
        $this->addSql('ALTER INDEX idx_c7972b744f5d008 RENAME TO idx_product_references_brand');
        $this->addSql('ALTER INDEX idx_c7972b712469de2 RENAME TO idx_product_references_category');
        $this->addSql('ALTER INDEX uniq_90e0145c4d16c4dd RENAME TO uniq_5e1f8d5b4d16c4dd');
        $this->addSql('ALTER INDEX idx_237a67837e3c61f9 RENAME TO idx_6f6a97497e3c61f9');
        $this->addSql('ALTER INDEX uniq_237a67831bc9050b RENAME TO uniq_6f6a9749b03a8386');
        $this->addSql('ALTER INDEX uniq_237a6783989d9b62 RENAME TO uniq_6f6a974989d9b62');
        $this->addSql('ALTER INDEX uniq_1483a5e9e7927c74 RENAME TO uniq_users_email');
    }
}
