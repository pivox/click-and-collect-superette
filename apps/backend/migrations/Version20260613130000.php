<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create feedback settings and entries tables for Retour module';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE feedback_settings (id UUID NOT NULL, global_enabled BOOLEAN NOT NULL, client_enabled BOOLEAN NOT NULL, merchant_enabled BOOLEAN NOT NULL, admin_enabled BOOLEAN NOT NULL, client_area_enabled BOOLEAN NOT NULL, merchant_area_enabled BOOLEAN NOT NULL, admin_area_enabled BOOLEAN NOT NULL, require_authenticated_user BOOLEAN NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN feedback_settings.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN feedback_settings.updated_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE feedback_entries (id UUID NOT NULL, user_id UUID NOT NULL, shop_id UUID DEFAULT NULL, status VARCHAR(20) NOT NULL, type VARCHAR(20) NOT NULL, message TEXT NOT NULL, app_area VARCHAR(20) NOT NULL, app_sub_area VARCHAR(100) DEFAULT NULL, user_role VARCHAR(20) NOT NULL, page_url VARCHAR(2048) DEFAULT NULL, route_name VARCHAR(150) DEFAULT NULL, page_title VARCHAR(255) DEFAULT NULL, locale VARCHAR(10) DEFAULT NULL, viewport_width INT DEFAULT NULL, viewport_height INT DEFAULT NULL, user_agent VARCHAR(500) DEFAULT NULL, contact_consent BOOLEAN NOT NULL, admin_note TEXT DEFAULT NULL, read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, resolved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_FEEDBACK_ENTRIES_STATUS ON feedback_entries (status)');
        $this->addSql('CREATE INDEX IDX_FEEDBACK_ENTRIES_TYPE ON feedback_entries (type)');
        $this->addSql('CREATE INDEX IDX_FEEDBACK_ENTRIES_AREA ON feedback_entries (app_area)');
        $this->addSql('CREATE INDEX IDX_FEEDBACK_ENTRIES_USER_ROLE ON feedback_entries (user_role)');
        $this->addSql('CREATE INDEX IDX_FEEDBACK_ENTRIES_USER ON feedback_entries (user_id)');
        $this->addSql('CREATE INDEX IDX_FEEDBACK_ENTRIES_SHOP ON feedback_entries (shop_id)');
        $this->addSql('CREATE INDEX IDX_FEEDBACK_ENTRIES_CREATED_AT ON feedback_entries (created_at)');
        $this->addSql('COMMENT ON COLUMN feedback_entries.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN feedback_entries.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN feedback_entries.shop_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN feedback_entries.read_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN feedback_entries.resolved_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN feedback_entries.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN feedback_entries.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE feedback_entries ADD CONSTRAINT FK_FEEDBACK_ENTRIES_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE feedback_entries ADD CONSTRAINT FK_FEEDBACK_ENTRIES_SHOP FOREIGN KEY (shop_id) REFERENCES shops (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE feedback_entries DROP CONSTRAINT FK_FEEDBACK_ENTRIES_USER');
        $this->addSql('ALTER TABLE feedback_entries DROP CONSTRAINT FK_FEEDBACK_ENTRIES_SHOP');
        $this->addSql('DROP TABLE feedback_entries');
        $this->addSql('DROP TABLE feedback_settings');
    }
}
