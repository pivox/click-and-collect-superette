<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add merchant temporary password expiration window';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD temporary_password_generated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD temporary_password_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_USERS_TEMPORARY_PASSWORD_EXPIRES_AT ON users (temporary_password_expires_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_USERS_TEMPORARY_PASSWORD_EXPIRES_AT');
        $this->addSql('ALTER TABLE users DROP temporary_password_generated_at');
        $this->addSql('ALTER TABLE users DROP temporary_password_expires_at');
    }
}
