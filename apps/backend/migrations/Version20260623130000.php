<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add platform settings for deterministic frontend origin';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE platform_settings (id UUID NOT NULL, singleton_key VARCHAR(32) NOT NULL, frontend_origin VARCHAR(2048) NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PLATFORM_SETTING_SINGLETON ON platform_settings (singleton_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE platform_settings');
    }
}
