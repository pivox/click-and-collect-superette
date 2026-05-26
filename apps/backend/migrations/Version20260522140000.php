<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link product_references to their raw import source row.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_references ADD source_import_raw_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN product_references.source_import_raw_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE product_references ADD CONSTRAINT FK_PRODUCT_REFERENCES_SOURCE_IMPORT_RAW FOREIGN KEY (source_import_raw_id) REFERENCES product_import_raw (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PRODUCT_REFERENCES_SOURCE_IMPORT_RAW ON product_references (source_import_raw_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_references DROP CONSTRAINT FK_PRODUCT_REFERENCES_SOURCE_IMPORT_RAW');
        $this->addSql('DROP INDEX UNIQ_PRODUCT_REFERENCES_SOURCE_IMPORT_RAW');
        $this->addSql('ALTER TABLE product_references DROP source_import_raw_id');
    }
}
