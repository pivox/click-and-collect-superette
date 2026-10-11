<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Versioned subscription pricing, preserving legacy subscriptions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billing_documents ADD pricing_snapshot JSON DEFAULT NULL');
        $this->addSql("ALTER TABLE subscriptions ADD pricing_versions JSON DEFAULT '[]' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billing_documents DROP pricing_snapshot');
        $this->addSql('ALTER TABLE subscriptions DROP pricing_versions');
    }
}
