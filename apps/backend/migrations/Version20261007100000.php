<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add photo-import sessions and shared merchant quota ledger (CATALOG-AI-001, issue #638).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE catalog_photo_import_sessions (id UUID NOT NULL, shop_id UUID NOT NULL, created_by_user_id UUID DEFAULT NULL, status VARCHAR(32) NOT NULL, mode VARCHAR(32) DEFAULT NULL, campaign_id VARCHAR(64) DEFAULT NULL, configuration_version INT NOT NULL, version INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_PHOTO_IMPORT_SESSION_SHOP ON catalog_photo_import_sessions (shop_id)');
        $this->addSql('CREATE INDEX IDX_PHOTO_IMPORT_SESSION_CREATED_BY ON catalog_photo_import_sessions (created_by_user_id)');
        $this->addSql('ALTER TABLE catalog_photo_import_sessions ADD CONSTRAINT FK_PHOTO_IMPORT_SESSION_SHOP FOREIGN KEY (shop_id) REFERENCES shops (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_photo_import_sessions ADD CONSTRAINT FK_PHOTO_IMPORT_SESSION_CREATED_BY FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE catalog_photo_import_images (id UUID NOT NULL, session_id UUID NOT NULL, shop_id UUID NOT NULL, source_hash VARCHAR(64) NOT NULL, status VARCHAR(16) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, removed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_PHOTO_IMPORT_IMAGE_SESSION_HASH ON catalog_photo_import_images (session_id, source_hash)');
        $this->addSql('CREATE INDEX IDX_PHOTO_IMPORT_IMAGE_SHOP ON catalog_photo_import_images (shop_id)');
        $this->addSql('ALTER TABLE catalog_photo_import_images ADD CONSTRAINT FK_PHOTO_IMPORT_IMAGE_SESSION FOREIGN KEY (session_id) REFERENCES catalog_photo_import_sessions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_photo_import_images ADD CONSTRAINT FK_PHOTO_IMPORT_IMAGE_SHOP FOREIGN KEY (shop_id) REFERENCES shops (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE catalog_photo_quota_grants (id UUID NOT NULL, shop_id UUID NOT NULL, allowance INT NOT NULL, source VARCHAR(32) NOT NULL, granted_by_id UUID DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PHOTO_QUOTA_GRANT_SHOP_SOURCE ON catalog_photo_quota_grants (shop_id, source)');
        $this->addSql('ALTER TABLE catalog_photo_quota_grants ADD CONSTRAINT FK_PHOTO_QUOTA_GRANT_SHOP FOREIGN KEY (shop_id) REFERENCES shops (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_photo_quota_grants ADD CONSTRAINT FK_PHOTO_QUOTA_GRANT_GRANTED_BY FOREIGN KEY (granted_by_id) REFERENCES users (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE catalog_photo_quota_entries (id UUID NOT NULL, grant_id UUID NOT NULL, import_image_id UUID DEFAULT NULL, operation VARCHAR(32) NOT NULL, quantity INT NOT NULL, idempotency_key VARCHAR(128) NOT NULL, reason VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PHOTO_QUOTA_ENTRY_IDEMPOTENCY ON catalog_photo_quota_entries (idempotency_key)');
        $this->addSql('CREATE INDEX IDX_PHOTO_QUOTA_ENTRY_GRANT ON catalog_photo_quota_entries (grant_id)');
        $this->addSql('CREATE INDEX IDX_PHOTO_QUOTA_ENTRY_IMAGE ON catalog_photo_quota_entries (import_image_id)');
        $this->addSql('ALTER TABLE catalog_photo_quota_entries ADD CONSTRAINT FK_PHOTO_QUOTA_ENTRY_GRANT FOREIGN KEY (grant_id) REFERENCES catalog_photo_quota_grants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_photo_quota_entries ADD CONSTRAINT FK_PHOTO_QUOTA_ENTRY_IMAGE FOREIGN KEY (import_image_id) REFERENCES catalog_photo_import_images (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE catalog_photo_quota_entries');
        $this->addSql('DROP TABLE catalog_photo_quota_grants');
        $this->addSql('DROP TABLE catalog_photo_import_images');
        $this->addSql('DROP TABLE catalog_photo_import_sessions');
    }
}
