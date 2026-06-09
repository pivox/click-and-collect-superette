<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add rejection_reason field to product_references for rapid treatment (S13-006).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_references ADD rejection_reason TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_references DROP rejection_reason');
    }
}
