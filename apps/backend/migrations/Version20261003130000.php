<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bind refresh tokens to issuance credentials and revoke legacy tokens without a trustworthy snapshot.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_tokens ADD credential_hash VARCHAR(64) DEFAULT NULL');
        // The current password cannot prove which credentials issued an old
        // token. Reconnection is safer than backfilling an invented snapshot.
        $this->addSql('UPDATE refresh_tokens SET revoked_at = COALESCE(revoked_at, CURRENT_TIMESTAMP) WHERE credential_hash IS NULL');
    }

    public function down(Schema $schema): void
    {
        // Rolling back the schema must never reactivate revoked sessions.
        $this->addSql('ALTER TABLE refresh_tokens DROP COLUMN credential_hash');
    }
}
