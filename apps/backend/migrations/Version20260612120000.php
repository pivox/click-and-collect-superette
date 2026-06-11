<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create merchant CRM tables (profile + contact history) for issue 385';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE merchant_crm_profiles (id UUID NOT NULL, merchant_id UUID NOT NULL, commercial_owner VARCHAR(120) DEFAULT NULL, status VARCHAR(16) DEFAULT \'prospect\' NOT NULL, next_action_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, next_action_note VARCHAR(255) DEFAULT NULL, commercial_note TEXT DEFAULT NULL, last_contact_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_MERCHANT_CRM_PROFILES_MERCHANT ON merchant_crm_profiles (merchant_id)');
        $this->addSql('CREATE INDEX IDX_MERCHANT_CRM_PROFILES_STATUS ON merchant_crm_profiles (status)');
        $this->addSql('CREATE INDEX IDX_MERCHANT_CRM_PROFILES_OWNER ON merchant_crm_profiles (commercial_owner)');
        $this->addSql('COMMENT ON COLUMN merchant_crm_profiles.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN merchant_crm_profiles.merchant_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN merchant_crm_profiles.next_action_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN merchant_crm_profiles.last_contact_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN merchant_crm_profiles.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN merchant_crm_profiles.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE merchant_crm_profiles ADD CONSTRAINT FK_MERCHANT_CRM_PROFILES_MERCHANT FOREIGN KEY (merchant_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('CREATE TABLE merchant_crm_contacts (id UUID NOT NULL, merchant_id UUID NOT NULL, created_by_admin_id UUID NOT NULL, contacted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, channel VARCHAR(16) NOT NULL, note TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_MERCHANT_CRM_CONTACTS_MERCHANT ON merchant_crm_contacts (merchant_id)');
        $this->addSql('CREATE INDEX IDX_MERCHANT_CRM_CONTACTS_MERCHANT_CONTACTED ON merchant_crm_contacts (merchant_id, contacted_at)');
        $this->addSql('CREATE INDEX IDX_MERCHANT_CRM_CONTACTS_ADMIN ON merchant_crm_contacts (created_by_admin_id)');
        $this->addSql('COMMENT ON COLUMN merchant_crm_contacts.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN merchant_crm_contacts.merchant_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN merchant_crm_contacts.created_by_admin_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN merchant_crm_contacts.contacted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN merchant_crm_contacts.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE merchant_crm_contacts ADD CONSTRAINT FK_MERCHANT_CRM_CONTACTS_MERCHANT FOREIGN KEY (merchant_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE merchant_crm_contacts ADD CONSTRAINT FK_MERCHANT_CRM_CONTACTS_ADMIN FOREIGN KEY (created_by_admin_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE merchant_crm_contacts DROP CONSTRAINT FK_MERCHANT_CRM_CONTACTS_MERCHANT');
        $this->addSql('ALTER TABLE merchant_crm_contacts DROP CONSTRAINT FK_MERCHANT_CRM_CONTACTS_ADMIN');
        $this->addSql('ALTER TABLE merchant_crm_profiles DROP CONSTRAINT FK_MERCHANT_CRM_PROFILES_MERCHANT');
        $this->addSql('DROP TABLE merchant_crm_contacts');
        $this->addSql('DROP TABLE merchant_crm_profiles');
    }
}
