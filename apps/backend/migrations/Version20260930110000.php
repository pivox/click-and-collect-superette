<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * MERCHANT-TEAM-001 expand phase: merchant organizations and memberships.
 *
 * Schema only — no access control change and no data backfill. Historical
 * data is migrated by the idempotent `app:merchant-organizations:backfill`
 * command, and anomalies are reported by `app:merchant-organizations:audit`.
 * `shops.owner_id` is kept untouched during the whole transition.
 */
final class Version20260930110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add merchant organizations, memberships and the nullable shops.merchant_organization_id column';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE merchant_organizations (id UUID NOT NULL, name VARCHAR(160) NOT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, primary_account_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_9643146DA5CFB3DC ON merchant_organizations (primary_account_id)');
        $this->addSql('ALTER TABLE merchant_organizations ADD CONSTRAINT FK_9643146DA5CFB3DC FOREIGN KEY (primary_account_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE merchant_memberships (id UUID NOT NULL, status VARCHAR(16) NOT NULL, invited_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, accepted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, merchant_organization_id UUID NOT NULL, user_id UUID NOT NULL, invited_by_id UUID DEFAULT NULL, revoked_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_EBFA6544E78F00 ON merchant_memberships (merchant_organization_id)');
        $this->addSql('CREATE INDEX IDX_EBFA65A76ED395 ON merchant_memberships (user_id)');
        $this->addSql('CREATE INDEX IDX_EBFA65A7B4A7E3 ON merchant_memberships (invited_by_id)');
        $this->addSql('CREATE INDEX IDX_EBFA65FB8FE773 ON merchant_memberships (revoked_by_id)');
        $this->addSql('CREATE INDEX idx_merchant_membership_user_status ON merchant_memberships (user_id, status)');
        $this->addSql('CREATE INDEX idx_merchant_membership_org_status ON merchant_memberships (merchant_organization_id, status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_merchant_membership_org_user ON merchant_memberships (merchant_organization_id, user_id)');
        $this->addSql("CREATE UNIQUE INDEX uniq_merchant_membership_active_user ON merchant_memberships (user_id) WHERE (status = 'active')");
        $this->addSql('ALTER TABLE merchant_memberships ADD CONSTRAINT FK_EBFA6544E78F00 FOREIGN KEY (merchant_organization_id) REFERENCES merchant_organizations (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE merchant_memberships ADD CONSTRAINT FK_EBFA65A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE merchant_memberships ADD CONSTRAINT FK_EBFA65A7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE merchant_memberships ADD CONSTRAINT FK_EBFA65FB8FE773 FOREIGN KEY (revoked_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE shops ADD merchant_organization_id UUID DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_237A678344E78F00 ON shops (merchant_organization_id)');
        $this->addSql('ALTER TABLE shops ADD CONSTRAINT FK_237A678344E78F00 FOREIGN KEY (merchant_organization_id) REFERENCES merchant_organizations (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shops DROP CONSTRAINT FK_237A678344E78F00');
        $this->addSql('DROP INDEX IDX_237A678344E78F00');
        $this->addSql('ALTER TABLE shops DROP merchant_organization_id');

        $this->addSql('ALTER TABLE merchant_memberships DROP CONSTRAINT FK_EBFA6544E78F00');
        $this->addSql('ALTER TABLE merchant_memberships DROP CONSTRAINT FK_EBFA65A76ED395');
        $this->addSql('ALTER TABLE merchant_memberships DROP CONSTRAINT FK_EBFA65A7B4A7E3');
        $this->addSql('ALTER TABLE merchant_memberships DROP CONSTRAINT FK_EBFA65FB8FE773');
        $this->addSql('DROP TABLE merchant_memberships');

        $this->addSql('ALTER TABLE merchant_organizations DROP CONSTRAINT FK_9643146DA5CFB3DC');
        $this->addSql('DROP TABLE merchant_organizations');
    }
}
