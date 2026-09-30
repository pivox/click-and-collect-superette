<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * PRODUCT-IMAGE-003: merchant photo contribution for local/vrac/reconditioned products.
 *
 * Adds the third (nullable) target of product_images: merchant_local_product_id.
 * An image points to exactly one of product_reference_id /
 * product_reference_proposal_id / merchant_local_product_id — invariant enforced
 * by the single write pipeline (ProductImageApplicationService).
 *
 * Hand-written (local DB may be in drift — never regenerate with migrations:diff).
 */
final class Version20260930160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add merchant_local_product_id target (FK + index) to product_images';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_images ADD merchant_local_product_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE product_images ADD CONSTRAINT FK_PRODUCT_IMAGES_LOCAL_PRODUCT FOREIGN KEY (merchant_local_product_id) REFERENCES merchant_local_products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_PRODUCT_IMAGES_LOCAL_PRODUCT ON product_images (merchant_local_product_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_PRODUCT_IMAGES_LOCAL_PRODUCT');
        $this->addSql('ALTER TABLE product_images DROP CONSTRAINT FK_PRODUCT_IMAGES_LOCAL_PRODUCT');
        $this->addSql('ALTER TABLE product_images DROP merchant_local_product_id');
    }
}
