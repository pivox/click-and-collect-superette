<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product reference merge history and allow barcode duplicate cleanup.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS UNIQ_C7972B797AE0266');
        $this->addSql('DROP INDEX IF EXISTS uniq_product_references_barcode');
        $this->addSql('CREATE TABLE product_reference_merge_history (
            id UUID NOT NULL,
            kept_product_reference_id UUID NOT NULL,
            absorbed_product_reference_id UUID NOT NULL,
            merged_by_admin_id UUID NOT NULL,
            reason VARCHAR(32) NOT NULL,
            moved_offer_count INT NOT NULL,
            removed_conflicting_offer_count INT NOT NULL,
            snapshot JSON NOT NULL,
            merged_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_PRODUCT_REFERENCE_MERGE_HISTORY_KEPT ON product_reference_merge_history (kept_product_reference_id)');
        $this->addSql('CREATE INDEX IDX_PRODUCT_REFERENCE_MERGE_HISTORY_ABSORBED ON product_reference_merge_history (absorbed_product_reference_id)');
        $this->addSql('CREATE INDEX IDX_PRODUCT_REFERENCE_MERGE_HISTORY_ADMIN ON product_reference_merge_history (merged_by_admin_id)');
        $this->addSql('CREATE INDEX IDX_PRODUCT_REFERENCE_MERGE_HISTORY_MERGED_AT ON product_reference_merge_history (merged_at)');
        $this->addSql('ALTER TABLE product_reference_merge_history ADD CONSTRAINT FK_PRODUCT_REFERENCE_MERGE_HISTORY_KEPT FOREIGN KEY (kept_product_reference_id) REFERENCES product_references (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_reference_merge_history ADD CONSTRAINT FK_PRODUCT_REFERENCE_MERGE_HISTORY_ABSORBED FOREIGN KEY (absorbed_product_reference_id) REFERENCES product_references (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_reference_merge_history ADD CONSTRAINT FK_PRODUCT_REFERENCE_MERGE_HISTORY_ADMIN FOREIGN KEY (merged_by_admin_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_reference_merge_history DROP CONSTRAINT FK_PRODUCT_REFERENCE_MERGE_HISTORY_KEPT');
        $this->addSql('ALTER TABLE product_reference_merge_history DROP CONSTRAINT FK_PRODUCT_REFERENCE_MERGE_HISTORY_ABSORBED');
        $this->addSql('ALTER TABLE product_reference_merge_history DROP CONSTRAINT FK_PRODUCT_REFERENCE_MERGE_HISTORY_ADMIN');
        $this->addSql('DROP TABLE product_reference_merge_history');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C7972B797AE0266 ON product_references (barcode)');
    }
}
