<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user, shop, platform theme and shop theme persistence.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (id UUID NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USERS_EMAIL ON users (email)');

        $this->addSql('CREATE TABLE shops (id UUID NOT NULL, name VARCHAR(160) NOT NULL, slug VARCHAR(180) NOT NULL, address VARCHAR(255) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, country VARCHAR(2) NOT NULL, phone VARCHAR(20) DEFAULT NULL, active BOOLEAN NOT NULL, qr_code_token VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6F6A974989D9B62 ON shops (slug)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6F6A9749B03A8386 ON shops (qr_code_token)');

        $this->addSql('CREATE TABLE platform_themes (id UUID NOT NULL, singleton_key VARCHAR(32) NOT NULL, primary_color VARCHAR(7) NOT NULL, secondary_color VARCHAR(7) NOT NULL, accent_color VARCHAR(7) NOT NULL, text_color VARCHAR(7) NOT NULL, background_color VARCHAR(7) NOT NULL, font_family VARCHAR(32) NOT NULL, base_font_size INT NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PLATFORM_THEME_SINGLETON ON platform_themes (singleton_key)');
        $this->addSql('ALTER TABLE platform_themes ADD CONSTRAINT platform_theme_base_font_size_range CHECK (base_font_size BETWEEN 14 AND 20)');
        $this->addSql("ALTER TABLE platform_themes ADD CONSTRAINT platform_theme_font_family_allowed CHECK (font_family IN ('inter', 'cairo', 'roboto', 'noto_sans_arabic', 'system'))");
        $this->addSql("ALTER TABLE platform_themes ADD CONSTRAINT platform_theme_primary_color_hex CHECK (primary_color ~ '^#[0-9A-Fa-f]{6}$')");
        $this->addSql("ALTER TABLE platform_themes ADD CONSTRAINT platform_theme_secondary_color_hex CHECK (secondary_color ~ '^#[0-9A-Fa-f]{6}$')");
        $this->addSql("ALTER TABLE platform_themes ADD CONSTRAINT platform_theme_accent_color_hex CHECK (accent_color ~ '^#[0-9A-Fa-f]{6}$')");
        $this->addSql("ALTER TABLE platform_themes ADD CONSTRAINT platform_theme_text_color_hex CHECK (text_color ~ '^#[0-9A-Fa-f]{6}$')");
        $this->addSql("ALTER TABLE platform_themes ADD CONSTRAINT platform_theme_background_color_hex CHECK (background_color ~ '^#[0-9A-Fa-f]{6}$')");
        $this->addSql("INSERT INTO platform_themes (id, singleton_key, primary_color, secondary_color, accent_color, text_color, background_color, font_family, base_font_size, updated_at) VALUES ('00000000-0000-0000-0000-000000000004', 'default', '#1B6CA8', '#F0A500', '#E63946', '#1A1A1A', '#FFFFFF', 'inter', 16, CURRENT_TIMESTAMP)");

        $this->addSql('CREATE TABLE shop_themes (id UUID NOT NULL, shop_id UUID NOT NULL, primary_color VARCHAR(7) NOT NULL, secondary_color VARCHAR(7) NOT NULL, accent_color VARCHAR(7) NOT NULL, text_color VARCHAR(7) NOT NULL, background_color VARCHAR(7) NOT NULL, font_family VARCHAR(32) NOT NULL, base_font_size INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5E1F8D5B4D16C4DD ON shop_themes (shop_id)');
        $this->addSql('ALTER TABLE shop_themes ADD CONSTRAINT FK_5E1F8D5B4D16C4DD FOREIGN KEY (shop_id) REFERENCES shops (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shop_themes ADD CONSTRAINT shop_theme_base_font_size_range CHECK (base_font_size BETWEEN 14 AND 20)');
        $this->addSql("ALTER TABLE shop_themes ADD CONSTRAINT shop_theme_font_family_allowed CHECK (font_family IN ('inter', 'cairo', 'roboto', 'noto_sans_arabic', 'system'))");
        $this->addSql("ALTER TABLE shop_themes ADD CONSTRAINT shop_theme_primary_color_hex CHECK (primary_color ~ '^#[0-9A-Fa-f]{6}$')");
        $this->addSql("ALTER TABLE shop_themes ADD CONSTRAINT shop_theme_secondary_color_hex CHECK (secondary_color ~ '^#[0-9A-Fa-f]{6}$')");
        $this->addSql("ALTER TABLE shop_themes ADD CONSTRAINT shop_theme_accent_color_hex CHECK (accent_color ~ '^#[0-9A-Fa-f]{6}$')");
        $this->addSql("ALTER TABLE shop_themes ADD CONSTRAINT shop_theme_text_color_hex CHECK (text_color ~ '^#[0-9A-Fa-f]{6}$')");
        $this->addSql("ALTER TABLE shop_themes ADD CONSTRAINT shop_theme_background_color_hex CHECK (background_color ~ '^#[0-9A-Fa-f]{6}$')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_themes DROP CONSTRAINT FK_5E1F8D5B4D16C4DD');
        $this->addSql('DROP TABLE shop_themes');
        $this->addSql('DROP TABLE platform_themes');
        $this->addSql('DROP TABLE shops');
        $this->addSql('DROP TABLE users');
    }
}
