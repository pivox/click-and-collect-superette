<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * PRODUCT-IMAGE-001: generic shared product references.
 *
 * Adds the `kind` discriminator (default `industrial` — every existing row
 * keeps its current behaviour) and makes `brand_id` nullable, reserved to
 * generic references by the write processors.
 */
final class Version20260930140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product reference kind discriminator and nullable brand for generic products';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE product_references ADD kind VARCHAR(16) DEFAULT 'industrial' NOT NULL");
        $this->addSql('ALTER TABLE product_references ALTER brand_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Generic rows (brand NULL) must be removed or re-branded manually
        // before rolling back the NOT NULL constraint.
        $this->addSql('ALTER TABLE product_references ALTER brand_id SET NOT NULL');
        $this->addSql('ALTER TABLE product_references DROP kind');
    }
}
