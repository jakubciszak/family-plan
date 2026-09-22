<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remember which admin configured a school account so an unattended sync can act for them';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE school_timetable_accounts ADD owner_id VARCHAR(36) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE school_timetable_accounts DROP owner_id');
    }
}
