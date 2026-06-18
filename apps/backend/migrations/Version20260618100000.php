<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist partial acceptance rejected merchant product ids on orders';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD partial_acceptance_rejected_merchant_product_ids JSON NOT NULL DEFAULT \'[]\'');
        $this->addSql('ALTER TABLE orders ALTER partial_acceptance_rejected_merchant_product_ids DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders DROP partial_acceptance_rejected_merchant_product_ids');
    }
}
