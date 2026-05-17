<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add opening hours to shops.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shops ADD opening_hours JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shops DROP opening_hours');
    }
}
