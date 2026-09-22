<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Read the hour of lessons imported before the column existed back from their calendar events';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE school_timetable_imported_lessons lesson SET starts_at = substring(event.definition::jsonb->'schedule'->>'localStart' from 12 for 5) FROM day_planning_events event WHERE event.id = lesson.event_id AND lesson.starts_at = '00:00' AND event.definition::jsonb->'schedule'->>'localStart' IS NOT NULL");
    }

    public function down(Schema $schema): void
    {
    }
}
