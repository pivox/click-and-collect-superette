<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260509100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add created_product_reference_id to product_reference_proposals for approve/merge tracking.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_reference_proposals ADD COLUMN created_product_reference_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE product_reference_proposals ADD CONSTRAINT FK_prp_created_ref FOREIGN KEY (created_product_reference_id) REFERENCES product_references (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_prp_created_ref ON product_reference_proposals (created_product_reference_id)');
        $this->addSql('ALTER TABLE product_reference_proposals DROP CONSTRAINT CHK_PROPOSALS_STATUS');
        $this->addSql("ALTER TABLE product_reference_proposals ADD CONSTRAINT CHK_PROPOSALS_STATUS CHECK (status IN ('pending', 'approved', 'rejected', 'merged'))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_reference_proposals DROP CONSTRAINT CHK_PROPOSALS_STATUS');
        $this->addSql("ALTER TABLE product_reference_proposals ADD CONSTRAINT CHK_PROPOSALS_STATUS CHECK (status IN ('pending', 'approved', 'rejected'))");
        $this->addSql('DROP INDEX IDX_prp_created_ref');
        $this->addSql('ALTER TABLE product_reference_proposals DROP CONSTRAINT FK_prp_created_ref');
        $this->addSql('ALTER TABLE product_reference_proposals DROP COLUMN created_product_reference_id');
    }
}
