<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create customer_product_favorites table for Kadhia suggestions (issue 383)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE customer_product_favorites (id UUID NOT NULL, customer_id UUID NOT NULL, merchant_product_id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CUSTOMER_PRODUCT_FAVORITES_CUSTOMER_PRODUCT ON customer_product_favorites (customer_id, merchant_product_id)');
        $this->addSql('CREATE INDEX IDX_CUSTOMER_PRODUCT_FAVORITES_CUSTOMER ON customer_product_favorites (customer_id)');
        $this->addSql('CREATE INDEX IDX_CUSTOMER_PRODUCT_FAVORITES_PRODUCT ON customer_product_favorites (merchant_product_id)');
        $this->addSql('COMMENT ON COLUMN customer_product_favorites.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN customer_product_favorites.customer_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN customer_product_favorites.merchant_product_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN customer_product_favorites.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE customer_product_favorites ADD CONSTRAINT FK_CUSTOMER_PRODUCT_FAVORITES_CUSTOMER FOREIGN KEY (customer_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE customer_product_favorites ADD CONSTRAINT FK_CUSTOMER_PRODUCT_FAVORITES_PRODUCT FOREIGN KEY (merchant_product_id) REFERENCES merchant_products (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_product_favorites DROP CONSTRAINT FK_CUSTOMER_PRODUCT_FAVORITES_CUSTOMER');
        $this->addSql('ALTER TABLE customer_product_favorites DROP CONSTRAINT FK_CUSTOMER_PRODUCT_FAVORITES_PRODUCT');
        $this->addSql('DROP TABLE customer_product_favorites');
    }
}
