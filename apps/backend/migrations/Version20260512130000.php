<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create orders and order_lines tables (Sprint 2 — SP2-009).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE orders (
            id UUID NOT NULL,
            customer_id UUID NOT NULL,
            shop_id UUID NOT NULL,
            kadhia_id UUID DEFAULT NULL,
            pickup_slot_id UUID DEFAULT NULL,
            status VARCHAR(32) NOT NULL,
            notes VARCHAR(500) DEFAULT NULL,
            total_tnd NUMERIC(10, 3) NOT NULL DEFAULT 0,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX IDX_ORDERS_CUSTOMER ON orders (customer_id)');
        $this->addSql('CREATE INDEX IDX_ORDERS_SHOP ON orders (shop_id)');
        $this->addSql('CREATE INDEX IDX_ORDERS_STATUS ON orders (status)');

        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_ORDERS_CUSTOMER FOREIGN KEY (customer_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_ORDERS_SHOP FOREIGN KEY (shop_id) REFERENCES shops (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_ORDERS_KADHIA FOREIGN KEY (kadhia_id) REFERENCES kadhias (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_ORDERS_PICKUP_SLOT FOREIGN KEY (pickup_slot_id) REFERENCES pickup_slots (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT CHK_ORDERS_TOTAL CHECK (total_tnd >= 0)');

        $this->addSql('CREATE TABLE order_lines (
            id UUID NOT NULL,
            order_id UUID NOT NULL,
            merchant_product_id UUID NOT NULL,
            quantity INT NOT NULL,
            unit_price_tnd NUMERIC(10, 3) NOT NULL,
            line_total_tnd NUMERIC(10, 3) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX IDX_ORDER_LINES_ORDER ON order_lines (order_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ORDER_LINES_ORDER_PRODUCT ON order_lines (order_id, merchant_product_id)');

        $this->addSql('ALTER TABLE order_lines ADD CONSTRAINT FK_ORDER_LINES_ORDER FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE order_lines ADD CONSTRAINT FK_ORDER_LINES_MERCHANT_PRODUCT FOREIGN KEY (merchant_product_id) REFERENCES merchant_products (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE order_lines ADD CONSTRAINT CHK_ORDER_LINES_QUANTITY CHECK (quantity >= 1)');
        $this->addSql('ALTER TABLE order_lines ADD CONSTRAINT CHK_ORDER_LINES_UNIT_PRICE CHECK (unit_price_tnd >= 0)');
        $this->addSql('ALTER TABLE order_lines ADD CONSTRAINT CHK_ORDER_LINES_LINE_TOTAL CHECK (line_total_tnd >= 0)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_lines DROP CONSTRAINT FK_ORDER_LINES_ORDER');
        $this->addSql('DROP TABLE order_lines');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT FK_ORDERS_CUSTOMER');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT FK_ORDERS_SHOP');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT FK_ORDERS_KADHIA');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT FK_ORDERS_PICKUP_SLOT');
        $this->addSql('DROP TABLE orders');
    }
}
