<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011134412 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow partial subscription payments and unique optional idempotency keys.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_SUBSCRIPTION_PAYMENTS_DOCUMENT');
        $this->addSql('ALTER TABLE subscription_payments ADD idempotency_key UUID DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTION_PAYMENTS_DOCUMENT ON subscription_payments (billing_document_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_SUBSCRIPTION_PAYMENTS_IDEMPOTENCY ON subscription_payments (idempotency_key)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            false !== $this->connection->fetchOne('SELECT billing_document_id FROM subscription_payments GROUP BY billing_document_id HAVING COUNT(*) > 1 LIMIT 1'),
            'Cannot restore one payment per document while partial payment history exists.',
        );
        $this->addSql('CREATE UNIQUE INDEX UNIQ_SUBSCRIPTION_PAYMENTS_DOCUMENT ON subscription_payments (billing_document_id)');
        $this->addSql('DROP INDEX IDX_SUBSCRIPTION_PAYMENTS_DOCUMENT');
        $this->addSql('DROP INDEX UNIQ_SUBSCRIPTION_PAYMENTS_IDEMPOTENCY');
        $this->addSql('ALTER TABLE subscription_payments DROP idempotency_key');
    }
}
