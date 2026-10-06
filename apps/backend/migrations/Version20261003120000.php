<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store provider identities and one-use social OAuth state/PKCE exchanges.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE social_identities (id UUID NOT NULL, user_id UUID NOT NULL, provider VARCHAR(20) NOT NULL, subject VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_SOCIAL_PROVIDER_SUBJECT ON social_identities (provider, subject)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_SOCIAL_USER_PROVIDER ON social_identities (user_id, provider)');
        $this->addSql('CREATE INDEX IDX_SOCIAL_IDENTITY_USER ON social_identities (user_id)');
        $this->addSql('ALTER TABLE social_identities ADD CONSTRAINT FK_SOCIAL_IDENTITY_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE TABLE social_auth_flows (id UUID NOT NULL, link_user_id UUID DEFAULT NULL, provider VARCHAR(20) NOT NULL, state_hash VARCHAR(64) NOT NULL, code_challenge VARCHAR(43) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, callback_consumed BOOLEAN NOT NULL, exchange_consumed BOOLEAN NOT NULL, ticket_hash VARCHAR(64) DEFAULT NULL, subject VARCHAR(255) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, name VARCHAR(100) NOT NULL, credential_hash VARCHAR(64) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_SOCIAL_FLOW_STATE ON social_auth_flows (state_hash)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_SOCIAL_FLOW_TICKET ON social_auth_flows (ticket_hash)');
        $this->addSql('CREATE INDEX IDX_SOCIAL_FLOW_USER ON social_auth_flows (link_user_id)');
        $this->addSql('CREATE INDEX IDX_SOCIAL_FLOW_EXPIRY ON social_auth_flows (expires_at)');
        $this->addSql('ALTER TABLE social_auth_flows ADD CONSTRAINT FK_SOCIAL_FLOW_USER FOREIGN KEY (link_user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE social_auth_flows');
        $this->addSql('DROP TABLE social_identities');
    }
}
