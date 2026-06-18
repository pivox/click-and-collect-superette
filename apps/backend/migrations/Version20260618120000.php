<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add merchant invitation tokens';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE merchant_invitation_tokens (id UUID NOT NULL, merchant_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, token_hash VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_MERCHANT_INVITATION_TOKEN_HASH ON merchant_invitation_tokens (token_hash)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_MERCHANT_INVITATION_PENDING_MERCHANT ON merchant_invitation_tokens (merchant_id) WHERE used_at IS NULL AND revoked_at IS NULL');
        $this->addSql('CREATE INDEX IDX_MERCHANT_INVITATION_EXPIRES_AT ON merchant_invitation_tokens (expires_at)');
        $this->addSql('CREATE INDEX IDX_MERCHANT_INVITATION_CREATED_BY ON merchant_invitation_tokens (created_by_id)');
        $this->addSql('ALTER TABLE merchant_invitation_tokens ADD CONSTRAINT FK_MERCHANT_INVITATION_MERCHANT FOREIGN KEY (merchant_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE merchant_invitation_tokens ADD CONSTRAINT FK_MERCHANT_INVITATION_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE merchant_invitation_tokens DROP CONSTRAINT FK_MERCHANT_INVITATION_MERCHANT');
        $this->addSql('ALTER TABLE merchant_invitation_tokens DROP CONSTRAINT FK_MERCHANT_INVITATION_CREATED_BY');
        $this->addSql('DROP TABLE merchant_invitation_tokens');
    }
}
