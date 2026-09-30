<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * MERCHANT-TEAM-002 expand phase: attach subscriptions and CRM profiles to
 * merchant organizations.
 *
 * Nullable columns only — historical merchant_id columns are untouched and
 * stay the fallback during the transition. Data is attached by the idempotent
 * `app:merchant-organizations:backfill` command (billing documents, payments
 * and reminders follow transitively through their subscription FK).
 */
final class Version20260930120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Attach subscriptions and merchant CRM profiles to merchant organizations (nullable, unique)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscriptions ADD merchant_organization_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT FK_4778A0144E78F00 FOREIGN KEY (merchant_organization_id) REFERENCES merchant_organizations (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_subscriptions_merchant_organization ON subscriptions (merchant_organization_id)');

        $this->addSql('ALTER TABLE merchant_crm_profiles ADD merchant_organization_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE merchant_crm_profiles ADD CONSTRAINT FK_618F38F844E78F00 FOREIGN KEY (merchant_organization_id) REFERENCES merchant_organizations (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_618F38F844E78F00 ON merchant_crm_profiles (merchant_organization_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE merchant_crm_profiles DROP CONSTRAINT FK_618F38F844E78F00');
        $this->addSql('DROP INDEX UNIQ_618F38F844E78F00');
        $this->addSql('ALTER TABLE merchant_crm_profiles DROP merchant_organization_id');

        $this->addSql('ALTER TABLE subscriptions DROP CONSTRAINT FK_4778A0144E78F00');
        $this->addSql('DROP INDEX uniq_subscriptions_merchant_organization');
        $this->addSql('ALTER TABLE subscriptions DROP merchant_organization_id');
    }
}
