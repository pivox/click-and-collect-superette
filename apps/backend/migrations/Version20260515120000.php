<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260515120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create password reset tokens table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE password_reset_tokens (
            id UUID NOT NULL,
            user_id UUID NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            consumed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PASSWORD_RESET_TOKEN_HASH ON password_reset_tokens (token_hash)');
        $this->addSql('CREATE INDEX IDX_PASSWORD_RESET_USER_CONSUMED ON password_reset_tokens (user_id, consumed_at)');
        $this->addSql('CREATE INDEX IDX_PASSWORD_RESET_EXPIRES_AT ON password_reset_tokens (expires_at)');
        $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT FK_PASSWORD_RESET_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE password_reset_tokens DROP CONSTRAINT FK_PASSWORD_RESET_USER');
        $this->addSql('DROP TABLE password_reset_tokens');
    }
}
