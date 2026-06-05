<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add internal notes for order incidents.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE incidents ADD processing_started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE TABLE incident_notes (
            id UUID NOT NULL,
            incident_id UUID NOT NULL,
            created_by_admin_id UUID NOT NULL,
            note TEXT NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_INCIDENT_NOTES_INCIDENT ON incident_notes (incident_id)');
        $this->addSql('CREATE INDEX IDX_INCIDENT_NOTES_ADMIN ON incident_notes (created_by_admin_id)');
        $this->addSql('CREATE INDEX IDX_INCIDENT_NOTES_CREATED_AT ON incident_notes (created_at)');
        $this->addSql('ALTER TABLE incident_notes ADD CONSTRAINT FK_INCIDENT_NOTES_INCIDENT FOREIGN KEY (incident_id) REFERENCES incidents (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE incident_notes ADD CONSTRAINT FK_INCIDENT_NOTES_ADMIN FOREIGN KEY (created_by_admin_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql("COMMENT ON COLUMN incident_notes.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN incident_notes.incident_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN incident_notes.created_by_admin_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN incident_notes.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN incidents.processing_started_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE incident_notes DROP CONSTRAINT FK_INCIDENT_NOTES_INCIDENT');
        $this->addSql('ALTER TABLE incident_notes DROP CONSTRAINT FK_INCIDENT_NOTES_ADMIN');
        $this->addSql('DROP TABLE incident_notes');
        $this->addSql('ALTER TABLE incidents DROP processing_started_at');
    }
}
