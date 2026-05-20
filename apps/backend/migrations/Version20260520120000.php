<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add logo_url and cover_url to shops.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shops ADD logo_url VARCHAR(2048) DEFAULT NULL');
        $this->addSql('ALTER TABLE shops ADD cover_url VARCHAR(2048) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shops DROP logo_url');
        $this->addSql('ALTER TABLE shops DROP cover_url');
    }
}
