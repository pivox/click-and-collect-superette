<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add archived_at and archive_reason to shops.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shops ADD archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE shops ADD archive_reason VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shops DROP archived_at');
        $this->addSql('ALTER TABLE shops DROP archive_reason');
    }
}
