<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Merge RoutineTask functionality into Task entity
 */
final class Version20251215150200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Merge RoutineTask functionality into Task entity and update TaskExecution to reference template tasks';
    }

    public function up(Schema $schema): void
    {
        // Add template-related columns to tasks table
        $this->addSql('ALTER TABLE tasks ADD schedule_config VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE tasks ADD is_template BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE tasks ADD is_active BOOLEAN NOT NULL DEFAULT TRUE');
        $this->addSql('ALTER TABLE tasks ADD parent_task_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE tasks ADD scheduled_for TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        
        // Add indexes for new columns
        $this->addSql('CREATE INDEX IDX_tasks_parent_task_id ON tasks (parent_task_id)');
        $this->addSql('CREATE INDEX IDX_tasks_is_template ON tasks (is_template)');
        $this->addSql('CREATE INDEX IDX_tasks_scheduled_for ON tasks (scheduled_for)');
        
        // Add comments for new columns
        $this->addSql('COMMENT ON COLUMN tasks.schedule_config IS \'(DC2Type:schedule_config)\'');
        $this->addSql('COMMENT ON COLUMN tasks.parent_task_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tasks.scheduled_for IS \'(DC2Type:datetime_immutable)\'');

        // Rename column in task_executions table
        $this->addSql('ALTER TABLE task_executions RENAME COLUMN routine_task_id TO template_task_id');
        $this->addSql('DROP INDEX IF EXISTS IDX_task_executions_routine_task_id');
        $this->addSql('CREATE INDEX IDX_task_executions_template_task_id ON task_executions (template_task_id)');

        // Drop routine_tasks table
        $this->addSql('DROP TABLE IF EXISTS routine_tasks');
    }

    public function down(Schema $schema): void
    {
        // Recreate routine_tasks table
        $this->addSql('CREATE TABLE routine_tasks (
            id UUID NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            points INT NOT NULL,
            frequency VARCHAR(50) NOT NULL,
            schedule_config VARCHAR(500) NOT NULL,
            is_active BOOLEAN NOT NULL,
            assigned_user_id UUID DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_routine_tasks_is_active ON routine_tasks (is_active)');
        $this->addSql('CREATE INDEX IDX_routine_tasks_assigned_user_id ON routine_tasks (assigned_user_id)');

        // Rename column back in task_executions
        $this->addSql('ALTER TABLE task_executions RENAME COLUMN template_task_id TO routine_task_id');
        $this->addSql('DROP INDEX IF EXISTS IDX_task_executions_template_task_id');
        $this->addSql('CREATE INDEX IDX_task_executions_routine_task_id ON task_executions (routine_task_id)');

        // Remove columns from tasks table
        $this->addSql('DROP INDEX IF EXISTS IDX_tasks_parent_task_id');
        $this->addSql('DROP INDEX IF EXISTS IDX_tasks_is_template');
        $this->addSql('DROP INDEX IF EXISTS IDX_tasks_scheduled_for');
        $this->addSql('ALTER TABLE tasks DROP schedule_config');
        $this->addSql('ALTER TABLE tasks DROP is_template');
        $this->addSql('ALTER TABLE tasks DROP is_active');
        $this->addSql('ALTER TABLE tasks DROP parent_task_id');
        $this->addSql('ALTER TABLE tasks DROP scheduled_for');
    }
}
