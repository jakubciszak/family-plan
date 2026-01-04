<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add team_id to tasks and task_templates tables
 */
final class Version20260104141000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add team_id to tasks and task_templates tables for team association';
    }

    public function up(Schema $schema): void
    {
        // Add team_id column to tasks table
        $this->addSql('ALTER TABLE tasks ADD team_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN tasks.team_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE INDEX IDX_TASKS_TEAM_ID ON tasks (team_id)');

        // Add team_id column to task_templates table
        $this->addSql('ALTER TABLE task_templates ADD team_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN task_templates.team_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE INDEX IDX_TASK_TEMPLATES_TEAM_ID ON task_templates (team_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_TASK_TEMPLATES_TEAM_ID');
        $this->addSql('ALTER TABLE task_templates DROP team_id');
        
        $this->addSql('DROP INDEX IDX_TASKS_TEAM_ID');
        $this->addSql('ALTER TABLE tasks DROP team_id');
    }
}
