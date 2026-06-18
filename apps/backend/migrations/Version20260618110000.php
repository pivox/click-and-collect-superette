<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add password change required flag on users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD password_change_required BOOLEAN NOT NULL DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP password_change_required');
    }
}
