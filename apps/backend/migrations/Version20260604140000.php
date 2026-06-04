<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add merchant subscriptions with separate lifecycle and pricing phase.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE subscriptions (
            id UUID NOT NULL,
            merchant_id UUID NOT NULL,
            lifecycle VARCHAR(20) NOT NULL,
            pricing_phase VARCHAR(20) NOT NULL,
            monthly_price_tnd NUMERIC(10, 3) NOT NULL,
            currency VARCHAR(3) NOT NULL,
            started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            current_period_started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            current_period_ends_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            trial_ends_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            promo_ends_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            next_phase_change_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_SUBSCRIPTIONS_MERCHANT ON subscriptions (merchant_id)');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTIONS_LIFECYCLE ON subscriptions (lifecycle)');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTIONS_PRICING_PHASE ON subscriptions (pricing_phase)');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTIONS_CREATED_AT ON subscriptions (created_at)');
        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT FK_SUBSCRIPTIONS_MERCHANT FOREIGN KEY (merchant_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql("COMMENT ON COLUMN subscriptions.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN subscriptions.merchant_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN subscriptions.started_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN subscriptions.current_period_started_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN subscriptions.current_period_ends_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN subscriptions.trial_ends_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN subscriptions.promo_ends_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN subscriptions.next_phase_change_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN subscriptions.cancelled_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN subscriptions.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN subscriptions.updated_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscriptions DROP CONSTRAINT FK_SUBSCRIPTIONS_MERCHANT');
        $this->addSql('DROP TABLE subscriptions');
    }
}
