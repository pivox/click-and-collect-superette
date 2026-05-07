<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable shop owner relation to users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shops ADD owner_id UUID DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_6F6A97497E3C61F9 ON shops (owner_id)');
        $this->addSql('ALTER TABLE shops ADD CONSTRAINT FK_6F6A97497E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shops DROP CONSTRAINT FK_6F6A97497E3C61F9');
        $this->addSql('DROP INDEX IDX_6F6A97497E3C61F9');
        $this->addSql('ALTER TABLE shops DROP owner_id');
    }
}
