<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260531210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop obsolete default on merchant product price history currency.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE merchant_product_price_history ALTER currency DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE merchant_product_price_history ALTER currency SET DEFAULT 'TND'");
    }
}
