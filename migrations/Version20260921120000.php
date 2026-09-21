<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add daily calendar events, recurrence exceptions, tags and idempotent creation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE day_planning_events (id VARCHAR(36) NOT NULL, owner_id VARCHAR(36) NOT NULL, team_id VARCHAR(36) DEFAULT NULL, definition JSON NOT NULL, participants JSON NOT NULL, exceptions JSON NOT NULL, participation_exceptions JSON NOT NULL, person_ids JSONB NOT NULL, cancelled BOOLEAN NOT NULL, version INT NOT NULL DEFAULT 1, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_day_planning_owner ON day_planning_events (owner_id)');
        $this->addSql('CREATE INDEX idx_day_planning_team ON day_planning_events (team_id)');
        $this->addSql('CREATE INDEX idx_day_planning_people ON day_planning_events USING GIN (person_ids)');
        $this->addSql('CREATE TABLE day_planning_tags (id VARCHAR(36) NOT NULL, owner_id VARCHAR(36) NOT NULL, team_id VARCHAR(36) DEFAULT NULL, name VARCHAR(80) NOT NULL, color VARCHAR(7) NOT NULL, scope VARCHAR(8) NOT NULL, archived BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_day_planning_tag_owner ON day_planning_tags (owner_id)');
        $this->addSql('CREATE INDEX idx_day_planning_tag_team ON day_planning_tags (team_id)');
        $this->addSql('CREATE TABLE day_planning_idempotency (owner_id VARCHAR(36) NOT NULL, request_key VARCHAR(36) NOT NULL, request_hash VARCHAR(64) NOT NULL, response JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(owner_id, request_key))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE day_planning_idempotency');
        $this->addSql('DROP TABLE day_planning_events');
        $this->addSql('DROP TABLE day_planning_tags');
    }
}
