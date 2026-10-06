<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add users.cgu_accepted_at for client terms-of-service consent (issue mobile #35).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD cgu_accepted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP cgu_accepted_at');
    }
}
