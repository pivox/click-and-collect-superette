<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product_reference_proposals table — merchant product proposals pending admin review.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE product_reference_proposals (
            id UUID NOT NULL,
            proposed_by_id UUID NOT NULL,
            shop_id UUID NOT NULL,
            category_id UUID NOT NULL,
            brand_id UUID DEFAULT NULL,
            name_fr VARCHAR(255) NOT NULL,
            name_ar VARCHAR(255) DEFAULT NULL,
            brand_name VARCHAR(160) DEFAULT NULL,
            variant_fr VARCHAR(160) DEFAULT NULL,
            volume NUMERIC(10, 3) DEFAULT NULL,
            unit VARCHAR(32) NOT NULL,
            barcode VARCHAR(64) DEFAULT NULL,
            status VARCHAR(32) NOT NULL DEFAULT \'pending\',
            rejection_reason TEXT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('ALTER TABLE product_reference_proposals ADD CONSTRAINT FK_PROPOSALS_PROPOSED_BY FOREIGN KEY (proposed_by_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_reference_proposals ADD CONSTRAINT FK_PROPOSALS_SHOP FOREIGN KEY (shop_id) REFERENCES shops (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_reference_proposals ADD CONSTRAINT FK_PROPOSALS_CATEGORY FOREIGN KEY (category_id) REFERENCES categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_reference_proposals ADD CONSTRAINT FK_PROPOSALS_BRAND FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_PROPOSALS_PROPOSED_BY ON product_reference_proposals (proposed_by_id)');
        $this->addSql('CREATE INDEX IDX_PROPOSALS_SHOP ON product_reference_proposals (shop_id)');
        $this->addSql('CREATE INDEX IDX_PROPOSALS_STATUS ON product_reference_proposals (status)');
        $this->addSql("ALTER TABLE product_reference_proposals ADD CONSTRAINT CHK_PROPOSALS_UNIT CHECK (unit IN ('litre', 'millilitre', 'kilogramme', 'gramme', 'piece', 'paquet'))");
        $this->addSql("ALTER TABLE product_reference_proposals ADD CONSTRAINT CHK_PROPOSALS_STATUS CHECK (status IN ('pending', 'approved', 'rejected'))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_reference_proposals DROP CONSTRAINT FK_PROPOSALS_PROPOSED_BY');
        $this->addSql('ALTER TABLE product_reference_proposals DROP CONSTRAINT FK_PROPOSALS_SHOP');
        $this->addSql('ALTER TABLE product_reference_proposals DROP CONSTRAINT FK_PROPOSALS_CATEGORY');
        $this->addSql('ALTER TABLE product_reference_proposals DROP CONSTRAINT FK_PROPOSALS_BRAND');
        $this->addSql('ALTER TABLE product_reference_proposals DROP CONSTRAINT CHK_PROPOSALS_UNIT');
        $this->addSql('ALTER TABLE product_reference_proposals DROP CONSTRAINT CHK_PROPOSALS_STATUS');
        $this->addSql('DROP TABLE product_reference_proposals');
    }
}
