<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * PRODUCT-IMAGE-004: provenance, license and usage-rights registry on product images.
 *
 * Hand-written (local DB may be in drift — never regenerate with migrations:diff).
 * Backfill: existing admin uploads are internal shots → platform_owned; every other
 * source keeps the column default 'unknown'. collected_at is backfilled with
 * created_at (best known ingestion date for pre-existing rows).
 */
final class Version20260930150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add provenance/license registry columns to product_images (source, license, approval, supersession)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_images ADD source_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE product_images ADD source_url VARCHAR(2048) DEFAULT NULL');
        $this->addSql('ALTER TABLE product_images ADD attribution_text TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE product_images ADD permission_reference VARCHAR(255) DEFAULT NULL');
        $this->addSql("ALTER TABLE product_images ADD license_code VARCHAR(40) DEFAULT 'unknown' NOT NULL");
        $this->addSql('ALTER TABLE product_images ADD captured_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE product_images ADD collected_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE product_images ADD approved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE product_images ADD approved_by_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE product_images ADD superseded_by_id UUID DEFAULT NULL');

        $this->addSql("ALTER TABLE product_images ADD CONSTRAINT product_image_license_allowed CHECK (license_code IN ('platform_owned', 'merchant_authorized', 'manufacturer_authorized', 'distributor_authorized', 'cc_by', 'cc_by_sa', 'public_domain', 'unknown'))");
        $this->addSql('ALTER TABLE product_images ADD CONSTRAINT FK_PRODUCT_IMAGES_APPROVED_BY FOREIGN KEY (approved_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_images ADD CONSTRAINT FK_PRODUCT_IMAGES_SUPERSEDED_BY FOREIGN KEY (superseded_by_id) REFERENCES product_images (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE INDEX IDX_PRODUCT_IMAGES_LICENSE ON product_images (license_code)');
        $this->addSql('CREATE INDEX IDX_PRODUCT_IMAGES_APPROVED_BY ON product_images (approved_by_id)');
        $this->addSql('CREATE INDEX IDX_PRODUCT_IMAGES_SUPERSEDED_BY ON product_images (superseded_by_id)');

        // Backfill: an admin upload is an internal/authorized shot (assumption
        // documented in issue #584); every other source stays 'unknown'.
        $this->addSql("UPDATE product_images SET license_code = 'platform_owned' WHERE source = 'admin_upload'");
        $this->addSql('UPDATE product_images SET collected_at = created_at WHERE collected_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_PRODUCT_IMAGES_SUPERSEDED_BY');
        $this->addSql('DROP INDEX IDX_PRODUCT_IMAGES_APPROVED_BY');
        $this->addSql('DROP INDEX IDX_PRODUCT_IMAGES_LICENSE');
        $this->addSql('ALTER TABLE product_images DROP CONSTRAINT FK_PRODUCT_IMAGES_SUPERSEDED_BY');
        $this->addSql('ALTER TABLE product_images DROP CONSTRAINT FK_PRODUCT_IMAGES_APPROVED_BY');
        $this->addSql('ALTER TABLE product_images DROP CONSTRAINT product_image_license_allowed');
        $this->addSql('ALTER TABLE product_images DROP superseded_by_id');
        $this->addSql('ALTER TABLE product_images DROP approved_by_id');
        $this->addSql('ALTER TABLE product_images DROP approved_at');
        $this->addSql('ALTER TABLE product_images DROP collected_at');
        $this->addSql('ALTER TABLE product_images DROP captured_at');
        $this->addSql('ALTER TABLE product_images DROP license_code');
        $this->addSql('ALTER TABLE product_images DROP permission_reference');
        $this->addSql('ALTER TABLE product_images DROP attribution_text');
        $this->addSql('ALTER TABLE product_images DROP source_url');
        $this->addSql('ALTER TABLE product_images DROP source_name');
    }
}
