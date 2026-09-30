<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * MOBILE-API #616: opaque refresh tokens with single-use rotation.
 *
 * Only the sha256 hash of the token is stored (same model as
 * password_reset_tokens). family_id groups the rotation chain issued from one
 * login: replaying a consumed token revokes the whole family.
 *
 * Hand-written (local DB may be in drift — never regenerate with migrations:diff).
 */
final class Version20260930170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create refresh_tokens table (opaque mobile refresh tokens, rotation families).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE refresh_tokens (
            id UUID NOT NULL,
            user_id UUID NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            family_id UUID NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            device_label VARCHAR(120) DEFAULT NULL,
            created_by_ip VARCHAR(45) DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_REFRESH_TOKENS_TOKEN_HASH ON refresh_tokens (token_hash)');
        $this->addSql('CREATE INDEX IDX_REFRESH_TOKENS_USER ON refresh_tokens (user_id)');
        $this->addSql('CREATE INDEX IDX_REFRESH_TOKENS_FAMILY ON refresh_tokens (family_id)');
        $this->addSql('CREATE INDEX IDX_REFRESH_TOKENS_EXPIRES_AT ON refresh_tokens (expires_at)');
        $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT FK_REFRESH_TOKENS_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_tokens DROP CONSTRAINT FK_REFRESH_TOKENS_USER');
        $this->addSql('DROP TABLE refresh_tokens');
    }
}
