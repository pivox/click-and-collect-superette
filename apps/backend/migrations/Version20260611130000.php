<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add simple promotion fields to merchant products';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE merchant_products ADD promotion_price_tnd NUMERIC(10, 3) DEFAULT NULL');
        $this->addSql('ALTER TABLE merchant_products ADD promotion_ends_on DATE DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_MERCHANT_PRODUCTS_PROMOTION_ENDS_ON ON merchant_products (promotion_ends_on)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_MERCHANT_PRODUCTS_PROMOTION_ENDS_ON');
        $this->addSql('ALTER TABLE merchant_products DROP promotion_price_tnd');
        $this->addSql('ALTER TABLE merchant_products DROP promotion_ends_on');
    }
}
