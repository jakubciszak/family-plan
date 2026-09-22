<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remember the hour of an imported lesson and the calendar tag imported events carry';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE school_timetable_imported_lessons ADD starts_at VARCHAR(5) NOT NULL DEFAULT '00:00'");
        $this->addSql('ALTER TABLE school_timetable_imported_lessons ALTER COLUMN starts_at DROP DEFAULT');
        $this->addSql('ALTER TABLE school_timetable_accounts ADD tag_id VARCHAR(36) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE school_timetable_imported_lessons DROP starts_at');
        $this->addSql('ALTER TABLE school_timetable_accounts DROP tag_id');
    }
}
