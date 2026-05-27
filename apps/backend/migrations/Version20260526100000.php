<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enable PostgreSQL unaccent extension for accent-insensitive store search.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS unaccent');
        $this->addSql('CREATE INDEX IDX_SHOPS_UNACCENT_NAME ON shops (unaccent(lower(name)))');
        $this->addSql('CREATE INDEX IDX_SHOPS_UNACCENT_CITY ON shops (unaccent(lower(city)))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS IDX_SHOPS_UNACCENT_NAME');
        $this->addSql('DROP INDEX IF EXISTS IDX_SHOPS_UNACCENT_CITY');
        $this->addSql('DROP EXTENSION IF EXISTS unaccent');
    }
}
