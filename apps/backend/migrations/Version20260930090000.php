<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ORDER-LEAD-001: per-shop ordering policy.
 *
 * Historical shops keep no row on purpose: the application resolves a default
 * minimum pickup lead time of 0 when no policy exists, and the merchant PATCH
 * upserts the row. No backfill is required and the migration is replayable.
 */
final class Version20260930090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shop ordering policies (minimum pickup lead time per shop)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shop_ordering_policies (id UUID NOT NULL, shop_id UUID NOT NULL, minimum_pickup_lead_time_minutes INT DEFAULT 0 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2EBDCFA44D16C4DD ON shop_ordering_policies (shop_id)');
        $this->addSql('ALTER TABLE shop_ordering_policies ADD CONSTRAINT FK_2EBDCFA44D16C4DD FOREIGN KEY (shop_id) REFERENCES shops (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shop_ordering_policies ADD CONSTRAINT CHK_SHOP_ORDERING_POLICY_LEAD_TIME CHECK (minimum_pickup_lead_time_minutes >= 0 AND minimum_pickup_lead_time_minutes <= 10080)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_ordering_policies DROP CONSTRAINT FK_2EBDCFA44D16C4DD');
        $this->addSql('DROP TABLE shop_ordering_policies');
    }
}
