<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260531120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add readable sequential order numbers per shop.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD order_number INT DEFAULT NULL');
        $this->addSql("
            WITH numbered_orders AS (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY shop_id ORDER BY created_at ASC, id ASC) AS next_order_number
                FROM orders
                WHERE status <> 'draft'
            )
            UPDATE orders
            SET order_number = numbered_orders.next_order_number
            FROM numbered_orders
            WHERE orders.id = numbered_orders.id
        ");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ORDERS_SHOP_NUMBER ON orders (shop_id, order_number)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_ORDERS_SHOP_NUMBER');
        $this->addSql('ALTER TABLE orders DROP order_number');
    }
}
