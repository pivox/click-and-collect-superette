<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * S13-005 / US-041 — product image pipeline.
 *
 * Stores the original upload plus the responsive WebP variants + JPEG fallback
 * metadata. The official picture belongs to a product_reference; an image may
 * instead be attached to a product_reference_proposal for AI / contribution flows.
 */
final class Version20260604120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create product_images table (original + WebP variants + JPEG fallback)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE product_images (id UUID NOT NULL, product_reference_id UUID DEFAULT NULL, product_reference_proposal_id UUID DEFAULT NULL, original_path VARCHAR(1024) NOT NULL, mime_type VARCHAR(64) NOT NULL, width INT NOT NULL, height INT NOT NULL, variants JSON NOT NULL, source VARCHAR(32) NOT NULL, status VARCHAR(32) NOT NULL, alt_text VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_PRODUCT_IMAGES_REFERENCE ON product_images (product_reference_id)');
        $this->addSql('CREATE INDEX IDX_PRODUCT_IMAGES_PROPOSAL ON product_images (product_reference_proposal_id)');
        $this->addSql('CREATE INDEX IDX_PRODUCT_IMAGES_STATUS ON product_images (status)');
        $this->addSql('ALTER TABLE product_images ADD CONSTRAINT FK_PRODUCT_IMAGES_REFERENCE FOREIGN KEY (product_reference_id) REFERENCES product_references (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_images ADD CONSTRAINT FK_PRODUCT_IMAGES_PROPOSAL FOREIGN KEY (product_reference_proposal_id) REFERENCES product_reference_proposals (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql("ALTER TABLE product_images ADD CONSTRAINT product_image_source_allowed CHECK (source IN ('admin_upload', 'ai_enrichment', 'merchant_contribution', 'open_source', 'external_authorized_source'))");
        $this->addSql("ALTER TABLE product_images ADD CONSTRAINT product_image_status_allowed CHECK (status IN ('candidate', 'needs_review', 'verified', 'rejected', 'archived'))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_images DROP CONSTRAINT FK_PRODUCT_IMAGES_REFERENCE');
        $this->addSql('ALTER TABLE product_images DROP CONSTRAINT FK_PRODUCT_IMAGES_PROPOSAL');
        $this->addSql('DROP TABLE product_images');
    }
}
