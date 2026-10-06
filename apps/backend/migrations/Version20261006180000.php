<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add AI budget policy singleton (admin spending guardrails for CATALOG-AI, issue #644 — configuration slice only).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ai_budget_policies (id UUID NOT NULL, singleton_key VARCHAR(32) NOT NULL, enabled BOOLEAN NOT NULL, openai_enabled BOOLEAN NOT NULL, gemini_enabled BOOLEAN NOT NULL, mistral_enabled BOOLEAN NOT NULL, qwen_enabled BOOLEAN NOT NULL, currency VARCHAR(3) NOT NULL, max_cost_per_call_amount NUMERIC(12, 4) DEFAULT NULL, max_cost_per_photo_amount NUMERIC(12, 4) DEFAULT NULL, max_cost_per_shop_amount NUMERIC(12, 4) DEFAULT NULL, global_period_cap_amount NUMERIC(12, 4) DEFAULT NULL, global_period_days INT NOT NULL, max_output_tokens_per_call INT DEFAULT NULL, max_crops_per_photo INT DEFAULT NULL, max_attempts_per_photo INT DEFAULT NULL, max_call_duration_seconds INT DEFAULT NULL, max_concurrent_calls INT DEFAULT NULL, alert_threshold_percent INT NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AI_BUDGET_POLICY_SINGLETON ON ai_budget_policies (singleton_key)');
        $this->addSql('ALTER TABLE ai_budget_policies ADD CONSTRAINT ai_budget_policy_alert_threshold_range CHECK (alert_threshold_percent BETWEEN 1 AND 99)');
        $this->addSql('ALTER TABLE ai_budget_policies ADD CONSTRAINT ai_budget_policy_global_period_days_range CHECK (global_period_days BETWEEN 1 AND 365)');
        $this->addSql("INSERT INTO ai_budget_policies (id, singleton_key, enabled, openai_enabled, gemini_enabled, mistral_enabled, qwen_enabled, currency, global_period_days, alert_threshold_percent, updated_at) VALUES ('00000000-0000-0000-0000-000000000005', 'default', false, false, false, false, false, 'USD', 30, 80, CURRENT_TIMESTAMP)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ai_budget_policies');
    }
}
