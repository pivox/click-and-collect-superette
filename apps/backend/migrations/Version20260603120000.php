<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260603120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add composite index on customer_shops for paginated list performance';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_CUSTOMER_SHOPS_PAGINATED ON customer_shops (customer_id, status, is_favorite, last_seen_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_CUSTOMER_SHOPS_PAGINATED');
    }
}
