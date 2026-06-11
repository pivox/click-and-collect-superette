<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create product groups referential tables for issue 464';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE product_groups (id UUID NOT NULL, name_fr VARCHAR(160) NOT NULL, name_ar VARCHAR(160) DEFAULT NULL, slug VARCHAR(180) NOT NULL, description_fr TEXT DEFAULT NULL, description_ar TEXT DEFAULT NULL, market_country VARCHAR(2) DEFAULT \'TN\' NOT NULL, status VARCHAR(32) DEFAULT \'draft\' NOT NULL, visibility VARCHAR(32) DEFAULT \'merchant\' NOT NULL, icon VARCHAR(80) DEFAULT NULL, sort_order INT DEFAULT 0 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PRODUCT_GROUPS_SLUG ON product_groups (slug)');
        $this->addSql('CREATE INDEX IDX_PRODUCT_GROUPS_STATUS ON product_groups (status)');
        $this->addSql('CREATE INDEX IDX_PRODUCT_GROUPS_SORT_ORDER ON product_groups (sort_order)');
        $this->addSql('COMMENT ON COLUMN product_groups.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN product_groups.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN product_groups.updated_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE product_group_items (id UUID NOT NULL, product_group_id UUID NOT NULL, product_reference_id UUID NOT NULL, sort_order INT DEFAULT 0 NOT NULL, importance VARCHAR(32) DEFAULT \'recommended\' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_PRODUCT_GROUP_ITEMS_GROUP ON product_group_items (product_group_id)');
        $this->addSql('CREATE INDEX IDX_PRODUCT_GROUP_ITEMS_REFERENCE ON product_group_items (product_reference_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PRODUCT_GROUP_ITEMS_GROUP_REFERENCE ON product_group_items (product_group_id, product_reference_id)');
        $this->addSql('COMMENT ON COLUMN product_group_items.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN product_group_items.product_group_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN product_group_items.product_reference_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN product_group_items.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN product_group_items.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE product_group_items ADD CONSTRAINT FK_PRODUCT_GROUP_ITEMS_GROUP FOREIGN KEY (product_group_id) REFERENCES product_groups (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE product_group_items ADD CONSTRAINT FK_PRODUCT_GROUP_ITEMS_REFERENCE FOREIGN KEY (product_reference_id) REFERENCES product_references (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_group_items DROP CONSTRAINT FK_PRODUCT_GROUP_ITEMS_GROUP');
        $this->addSql('ALTER TABLE product_group_items DROP CONSTRAINT FK_PRODUCT_GROUP_ITEMS_REFERENCE');
        $this->addSql('DROP TABLE product_group_items');
        $this->addSql('DROP TABLE product_groups');
    }
}
