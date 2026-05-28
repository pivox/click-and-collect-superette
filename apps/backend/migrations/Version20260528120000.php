<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ProductReferenceProposal: nullable category, category_name_proposed, local_product_id (issue #195)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_reference_proposals ADD COLUMN category_name_proposed VARCHAR(160) DEFAULT NULL');
        $this->addSql('ALTER TABLE product_reference_proposals ADD COLUMN local_product_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE product_reference_proposals ALTER COLUMN category_id DROP NOT NULL');
        $this->addSql('ALTER TABLE product_reference_proposals ADD CONSTRAINT FK_PRODUCT_PROPOSALS_LOCAL_PRODUCT FOREIGN KEY (local_product_id) REFERENCES merchant_local_products (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_PRODUCT_PROPOSALS_LOCAL_PRODUCT ON product_reference_proposals (local_product_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_PRODUCT_PROPOSALS_LOCAL_PRODUCT');
        $this->addSql('ALTER TABLE product_reference_proposals DROP CONSTRAINT FK_PRODUCT_PROPOSALS_LOCAL_PRODUCT');
        $this->addSql('ALTER TABLE product_reference_proposals ALTER COLUMN category_id SET NOT NULL');
        $this->addSql('ALTER TABLE product_reference_proposals DROP COLUMN local_product_id');
        $this->addSql('ALTER TABLE product_reference_proposals DROP COLUMN category_name_proposed');
    }
}
