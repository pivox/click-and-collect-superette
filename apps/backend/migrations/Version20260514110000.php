<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add prepared flag to order lines for merchant line-by-line preparation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_lines ADD prepared BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_lines DROP prepared');
    }
}
