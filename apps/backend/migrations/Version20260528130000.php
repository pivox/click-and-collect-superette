<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ProductFamily entity, pack_quantity on ProductReference and MerchantLocalProduct (issue #197)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE product_families (
            id UUID NOT NULL,
            brand_id UUID DEFAULT NULL,
            category_id UUID DEFAULT NULL,
            base_name_fr VARCHAR(255) NOT NULL,
            base_name_ar VARCHAR(255) DEFAULT NULL,
            country VARCHAR(2) NOT NULL DEFAULT \'TN\',
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_PRODUCT_FAMILIES_BRAND ON product_families (brand_id)');
        $this->addSql('CREATE INDEX IDX_PRODUCT_FAMILIES_CATEGORY ON product_families (category_id)');
        $this->addSql('ALTER TABLE product_families ADD CONSTRAINT FK_PRODUCT_FAMILIES_BRAND FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_families ADD CONSTRAINT FK_PRODUCT_FAMILIES_CATEGORY FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE product_references ADD COLUMN product_family_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE product_references ADD COLUMN pack_quantity INT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE product_references ADD CONSTRAINT FK_PRODUCT_REFERENCES_FAMILY FOREIGN KEY (product_family_id) REFERENCES product_families (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_PRODUCT_REFERENCES_FAMILY ON product_references (product_family_id)');

        $this->addSql('ALTER TABLE merchant_local_products ADD COLUMN pack_quantity INT NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE merchant_local_products DROP COLUMN pack_quantity');

        $this->addSql('DROP INDEX IDX_PRODUCT_REFERENCES_FAMILY');
        $this->addSql('ALTER TABLE product_references DROP CONSTRAINT FK_PRODUCT_REFERENCES_FAMILY');
        $this->addSql('ALTER TABLE product_references DROP COLUMN pack_quantity');
        $this->addSql('ALTER TABLE product_references DROP COLUMN product_family_id');

        $this->addSql('ALTER TABLE product_families DROP CONSTRAINT FK_PRODUCT_FAMILIES_BRAND');
        $this->addSql('ALTER TABLE product_families DROP CONSTRAINT FK_PRODUCT_FAMILIES_CATEGORY');
        $this->addSql('DROP INDEX IDX_PRODUCT_FAMILIES_BRAND');
        $this->addSql('DROP INDEX IDX_PRODUCT_FAMILIES_CATEGORY');
        $this->addSql('DROP TABLE product_families');
    }
}
