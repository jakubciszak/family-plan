<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Save the reminder sound chosen for an action plan';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE action_plans ADD reminder_sound VARCHAR(20) DEFAULT 'soft' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE action_plans DROP reminder_sound');
    }
}
