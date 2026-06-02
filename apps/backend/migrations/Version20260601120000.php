<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260601120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'OpenAI product enrichment jobs and audit fields on product references';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE product_ai_enrichment_jobs (id UUID NOT NULL, product_reference_id UUID NOT NULL, status VARCHAR(32) NOT NULL, openai_batch_id VARCHAR(120) DEFAULT NULL, openai_output_file_id VARCHAR(120) DEFAULT NULL, input_payload JSON DEFAULT NULL, response_payload JSON DEFAULT NULL, attempt_count INT NOT NULL, estimated_cost_usd NUMERIC(10, 6) DEFAULT NULL, error_message VARCHAR(1000) DEFAULT NULL, submitted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, applied_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_PRODUCT_AI_ENRICHMENT_STATUS ON product_ai_enrichment_jobs (status)');
        $this->addSql('CREATE INDEX IDX_PRODUCT_AI_ENRICHMENT_BATCH ON product_ai_enrichment_jobs (openai_batch_id)');
        $this->addSql('CREATE INDEX IDX_PRODUCT_AI_ENRICHMENT_PRODUCT ON product_ai_enrichment_jobs (product_reference_id)');
        $this->addSql('ALTER TABLE product_ai_enrichment_jobs ADD CONSTRAINT FK_PRODUCT_AI_ENRICHMENT_PRODUCT FOREIGN KEY (product_reference_id) REFERENCES product_references (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql("ALTER TABLE product_ai_enrichment_jobs ADD CONSTRAINT CHK_PRODUCT_AI_ENRICHMENT_STATUS CHECK (status IN ('pending', 'submitted', 'succeeded', 'applied', 'failed'))");

        $this->addSql('ALTER TABLE product_references ADD ai_enriched_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE product_references ADD ai_confidence NUMERIC(5, 3) DEFAULT NULL');
        $this->addSql('ALTER TABLE product_references ADD ai_source VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE product_references ADD ai_previous_values JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_ai_enrichment_jobs DROP CONSTRAINT FK_PRODUCT_AI_ENRICHMENT_PRODUCT');
        $this->addSql('DROP TABLE product_ai_enrichment_jobs');
        $this->addSql('ALTER TABLE product_references DROP ai_enriched_at');
        $this->addSql('ALTER TABLE product_references DROP ai_confidence');
        $this->addSql('ALTER TABLE product_references DROP ai_source');
        $this->addSql('ALTER TABLE product_references DROP ai_previous_values');
    }
}
