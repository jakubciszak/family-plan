<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251215144200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add routine_tasks and task_executions tables for recurring task management';
    }

    public function up(Schema $schema): void
    {
        // Create routine_tasks table
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
        $this->addSql('COMMENT ON COLUMN routine_tasks.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN routine_tasks.name IS \'(DC2Type:task_name)\'');
        $this->addSql('COMMENT ON COLUMN routine_tasks.points IS \'(DC2Type:points)\'');
        $this->addSql('COMMENT ON COLUMN routine_tasks.frequency IS \'(DC2Type:frequency)\'');
        $this->addSql('COMMENT ON COLUMN routine_tasks.schedule_config IS \'(DC2Type:schedule_config)\'');
        $this->addSql('COMMENT ON COLUMN routine_tasks.assigned_user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN routine_tasks.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN routine_tasks.updated_at IS \'(DC2Type:datetime_immutable)\'');

        // Create task_executions table
        $this->addSql('CREATE TABLE task_executions (
            id UUID NOT NULL,
            routine_task_id UUID DEFAULT NULL,
            name VARCHAR(255) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            points INT DEFAULT NULL,
            scheduled_for TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            status VARCHAR(50) NOT NULL,
            assigned_user_id UUID DEFAULT NULL,
            completed_by_user_id UUID DEFAULT NULL,
            completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            approved_by_admin_id UUID DEFAULT NULL,
            approved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_task_executions_status ON task_executions (status)');
        $this->addSql('CREATE INDEX IDX_task_executions_routine_task_id ON task_executions (routine_task_id)');
        $this->addSql('CREATE INDEX IDX_task_executions_assigned_user_id ON task_executions (assigned_user_id)');
        $this->addSql('CREATE INDEX IDX_task_executions_scheduled_for ON task_executions (scheduled_for)');
        $this->addSql('COMMENT ON COLUMN task_executions.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN task_executions.routine_task_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN task_executions.name IS \'(DC2Type:task_name)\'');
        $this->addSql('COMMENT ON COLUMN task_executions.points IS \'(DC2Type:points)\'');
        $this->addSql('COMMENT ON COLUMN task_executions.scheduled_for IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN task_executions.status IS \'(DC2Type:execution_status)\'');
        $this->addSql('COMMENT ON COLUMN task_executions.assigned_user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN task_executions.completed_by_user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN task_executions.completed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN task_executions.approved_by_admin_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN task_executions.approved_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN task_executions.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN task_executions.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE task_executions');
        $this->addSql('DROP TABLE routine_tasks');
    }
}
