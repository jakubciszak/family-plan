<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store a mobidziennik sign in per team and remember the lessons imported from it';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE school_timetable_accounts (id VARCHAR(36) NOT NULL, team_id VARCHAR(36) NOT NULL, school_id VARCHAR(64) NOT NULL, login VARCHAR(190) NOT NULL, secret TEXT NOT NULL, students JSON NOT NULL, imported_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_school_timetable_team ON school_timetable_accounts (team_id)');
        $this->addSql('CREATE TABLE school_timetable_imported_lessons (id VARCHAR(36) NOT NULL, account_id VARCHAR(36) NOT NULL, event_id VARCHAR(36) NOT NULL, student_id VARCHAR(64) NOT NULL, occurs_on VARCHAR(10) NOT NULL, signature VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_school_timetable_window ON school_timetable_imported_lessons (account_id, occurs_on)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE school_timetable_imported_lessons');
        $this->addSql('DROP TABLE school_timetable_accounts');
    }
}
