<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260531110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add merchant product price history for priceHistory API.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE merchant_product_price_history (
            id UUID NOT NULL,
            merchant_product_id UUID NOT NULL,
            merchant_id UUID DEFAULT NULL,
            old_price NUMERIC(10, 3) DEFAULT NULL,
            new_price NUMERIC(10, 3) NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT \'TND\',
            change_type VARCHAR(32) NOT NULL,
            source VARCHAR(32) NOT NULL,
            reason VARCHAR(500) DEFAULT NULL,
            changed_by_user_id UUID DEFAULT NULL,
            changed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_MERCHANT_PRODUCT_PRICE_HISTORY_PRODUCT ON merchant_product_price_history (merchant_product_id)');
        $this->addSql('CREATE INDEX IDX_MERCHANT_PRODUCT_PRICE_HISTORY_MERCHANT ON merchant_product_price_history (merchant_id)');
        $this->addSql('CREATE INDEX IDX_MERCHANT_PRODUCT_PRICE_HISTORY_CHANGED_BY ON merchant_product_price_history (changed_by_user_id)');
        $this->addSql('CREATE INDEX IDX_MERCHANT_PRODUCT_PRICE_HISTORY_CHANGED_AT ON merchant_product_price_history (changed_at)');
        $this->addSql('ALTER TABLE merchant_product_price_history ADD CONSTRAINT FK_MERCHANT_PRODUCT_PRICE_HISTORY_PRODUCT FOREIGN KEY (merchant_product_id) REFERENCES merchant_products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE merchant_product_price_history ADD CONSTRAINT FK_MERCHANT_PRODUCT_PRICE_HISTORY_MERCHANT FOREIGN KEY (merchant_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE merchant_product_price_history ADD CONSTRAINT FK_MERCHANT_PRODUCT_PRICE_HISTORY_CHANGED_BY FOREIGN KEY (changed_by_user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE merchant_product_price_history DROP CONSTRAINT FK_MERCHANT_PRODUCT_PRICE_HISTORY_CHANGED_BY');
        $this->addSql('ALTER TABLE merchant_product_price_history DROP CONSTRAINT FK_MERCHANT_PRODUCT_PRICE_HISTORY_MERCHANT');
        $this->addSql('ALTER TABLE merchant_product_price_history DROP CONSTRAINT FK_MERCHANT_PRODUCT_PRICE_HISTORY_PRODUCT');
        $this->addSql('DROP INDEX IDX_MERCHANT_PRODUCT_PRICE_HISTORY_CHANGED_AT');
        $this->addSql('DROP INDEX IDX_MERCHANT_PRODUCT_PRICE_HISTORY_CHANGED_BY');
        $this->addSql('DROP INDEX IDX_MERCHANT_PRODUCT_PRICE_HISTORY_MERCHANT');
        $this->addSql('DROP INDEX IDX_MERCHANT_PRODUCT_PRICE_HISTORY_PRODUCT');
        $this->addSql('DROP TABLE merchant_product_price_history');
    }
}
