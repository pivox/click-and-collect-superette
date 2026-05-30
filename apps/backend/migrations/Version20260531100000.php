<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260531100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Synchronize Doctrine schema drift for product families, proposals, references and messenger messages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_families ALTER country DROP DEFAULT');
        $this->addSql('ALTER INDEX IF EXISTS idx_product_families_brand RENAME TO IDX_52EF555B44F5D008');
        $this->addSql('ALTER INDEX IF EXISTS idx_product_families_category RENAME TO IDX_52EF555B12469DE2');
        $this->addSql('ALTER INDEX IF EXISTS idx_product_proposals_local_product RENAME TO IDX_3C99B7EB27A73C0A');
        $this->addSql('ALTER INDEX IF EXISTS idx_product_references_family RENAME TO IDX_C7972B7ADFEE0E7');
        $this->addSql('DROP INDEX IF EXISTS idx_messenger_messages_queue_name');
        $this->addSql('DROP INDEX IF EXISTS idx_messenger_messages_available_at');
        $this->addSql('DROP INDEX IF EXISTS idx_messenger_messages_delivered_at');
        $this->addSql('DROP INDEX IF EXISTS idx_messenger_messages_queue_available');
        $this->addSql("COMMENT ON COLUMN messenger_messages.created_at IS ''");
        $this->addSql("COMMENT ON COLUMN messenger_messages.available_at IS ''");
        $this->addSql("COMMENT ON COLUMN messenger_messages.delivered_at IS ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("COMMENT ON COLUMN messenger_messages.delivered_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN messenger_messages.available_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN messenger_messages.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_MESSENGER_MESSAGES_QUEUE_AVAILABLE ON messenger_messages (queue_name, available_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_MESSENGER_MESSAGES_DELIVERED_AT ON messenger_messages (delivered_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_MESSENGER_MESSAGES_AVAILABLE_AT ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_MESSENGER_MESSAGES_QUEUE_NAME ON messenger_messages (queue_name)');
        $this->addSql('ALTER INDEX IF EXISTS idx_c7972b7adfee0e7 RENAME TO IDX_PRODUCT_REFERENCES_FAMILY');
        $this->addSql('ALTER INDEX IF EXISTS idx_3c99b7eb27a73c0a RENAME TO IDX_PRODUCT_PROPOSALS_LOCAL_PRODUCT');
        $this->addSql('ALTER INDEX IF EXISTS idx_52ef555b12469de2 RENAME TO IDX_PRODUCT_FAMILIES_CATEGORY');
        $this->addSql('ALTER INDEX IF EXISTS idx_52ef555b44f5d008 RENAME TO IDX_PRODUCT_FAMILIES_BRAND');
        $this->addSql("ALTER TABLE product_families ALTER country SET DEFAULT 'TN'");
    }
}
