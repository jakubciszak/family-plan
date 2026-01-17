<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260117220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add team_id column to bonus_points_rules and status_change_rules tables for team-based access control';
    }

    public function up(Schema $schema): void
    {
        // Add team_id column to bonus_points_rules table
        $this->addSql('ALTER TABLE bonus_points_rules ADD COLUMN team_id UUID NOT NULL');
        $this->addSql('CREATE INDEX IDX_bonus_points_rules_team_id ON bonus_points_rules (team_id)');
        $this->addSql('COMMENT ON COLUMN bonus_points_rules.team_id IS \'(DC2Type:uuid)\'');

        // Add team_id column to status_change_rules table
        $this->addSql('ALTER TABLE status_change_rules ADD COLUMN team_id UUID NOT NULL');
        $this->addSql('CREATE INDEX IDX_status_change_rules_team_id ON status_change_rules (team_id)');
        $this->addSql('COMMENT ON COLUMN status_change_rules.team_id IS \'(DC2Type:uuid)\'');
    }

    public function down(Schema $schema): void
    {
        // Remove team_id column from bonus_points_rules table
        $this->addSql('DROP INDEX IF EXISTS IDX_bonus_points_rules_team_id');
        $this->addSql('ALTER TABLE bonus_points_rules DROP COLUMN team_id');

        // Remove team_id column from status_change_rules table
        $this->addSql('DROP INDEX IF EXISTS IDX_status_change_rules_team_id');
        $this->addSql('ALTER TABLE status_change_rules DROP COLUMN team_id');
    }
}
