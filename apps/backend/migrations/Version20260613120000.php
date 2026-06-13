<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Kadhia members and share links for shared Kadhias';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pgcrypto');

        $this->addSql('CREATE TABLE kadhia_members (id UUID NOT NULL, kadhia_id UUID NOT NULL, user_id UUID NOT NULL, role VARCHAR(16) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_KADHIA_MEMBERS_KADHIA_USER ON kadhia_members (kadhia_id, user_id)');
        $this->addSql('CREATE INDEX IDX_KADHIA_MEMBERS_KADHIA ON kadhia_members (kadhia_id)');
        $this->addSql('CREATE INDEX IDX_KADHIA_MEMBERS_USER ON kadhia_members (user_id)');
        $this->addSql('COMMENT ON COLUMN kadhia_members.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN kadhia_members.kadhia_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN kadhia_members.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN kadhia_members.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE kadhia_members ADD CONSTRAINT FK_KADHIA_MEMBERS_KADHIA FOREIGN KEY (kadhia_id) REFERENCES kadhias (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE kadhia_members ADD CONSTRAINT FK_KADHIA_MEMBERS_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('INSERT INTO kadhia_members (id, kadhia_id, user_id, role, created_at) SELECT gen_random_uuid(), k.id, k.customer_id, \'editor\', NOW() FROM kadhias k ON CONFLICT (kadhia_id, user_id) DO NOTHING');

        $this->addSql('CREATE TABLE kadhia_share_links (id UUID NOT NULL, kadhia_id UUID NOT NULL, created_by_id UUID NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, active BOOLEAN DEFAULT true NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_KADHIA_SHARE_LINKS_TOKEN_HASH ON kadhia_share_links (token_hash)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_KADHIA_SHARE_LINKS_ACTIVE_KADHIA ON kadhia_share_links (kadhia_id) WHERE active = true');
        $this->addSql('CREATE INDEX IDX_KADHIA_SHARE_LINKS_KADHIA ON kadhia_share_links (kadhia_id)');
        $this->addSql('CREATE INDEX IDX_KADHIA_SHARE_LINKS_CREATED_BY ON kadhia_share_links (created_by_id)');
        $this->addSql('CREATE INDEX IDX_KADHIA_SHARE_LINKS_EXPIRES_AT ON kadhia_share_links (expires_at)');
        $this->addSql('COMMENT ON COLUMN kadhia_share_links.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN kadhia_share_links.kadhia_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN kadhia_share_links.created_by_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN kadhia_share_links.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN kadhia_share_links.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE kadhia_share_links ADD CONSTRAINT FK_KADHIA_SHARE_LINKS_KADHIA FOREIGN KEY (kadhia_id) REFERENCES kadhias (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE kadhia_share_links ADD CONSTRAINT FK_KADHIA_SHARE_LINKS_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kadhia_share_links DROP CONSTRAINT FK_KADHIA_SHARE_LINKS_KADHIA');
        $this->addSql('ALTER TABLE kadhia_share_links DROP CONSTRAINT FK_KADHIA_SHARE_LINKS_CREATED_BY');
        $this->addSql('ALTER TABLE kadhia_members DROP CONSTRAINT FK_KADHIA_MEMBERS_KADHIA');
        $this->addSql('ALTER TABLE kadhia_members DROP CONSTRAINT FK_KADHIA_MEMBERS_USER');
        $this->addSql('DROP TABLE kadhia_share_links');
        $this->addSql('DROP TABLE kadhia_members');
    }
}
