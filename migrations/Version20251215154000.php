<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251215154000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create task_templates and task_executions tables for recurring task management';
    }

    public function up(Schema $schema): void
    {
        // Create task_templates table
        $this->addSql('CREATE TABLE task_templates (
            id UUID NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            points INTEGER NOT NULL,
            frequency VARCHAR(50) NOT NULL,
            schedule_config JSONB NOT NULL,
            is_active BOOLEAN NOT NULL DEFAULT true,
            assigned_user_id UUID DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        
        $this->addSql('CREATE INDEX IDX_task_templates_is_active ON task_templates (is_active)');
        $this->addSql('CREATE INDEX IDX_task_templates_assigned_user_id ON task_templates (assigned_user_id)');
        $this->addSql('ALTER TABLE task_templates ADD CONSTRAINT FK_task_templates_assigned_user_id FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('COMMENT ON COLUMN task_templates.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN task_templates.name IS \'(DC2Type:task_name)\'');
        $this->addSql('COMMENT ON COLUMN task_templates.points IS \'(DC2Type:points)\'');
        $this->addSql('COMMENT ON COLUMN task_templates.frequency IS \'(DC2Type:frequency)\'');
        $this->addSql('COMMENT ON COLUMN task_templates.schedule_config IS \'(DC2Type:schedule_config)\'');
        $this->addSql('COMMENT ON COLUMN task_templates.assigned_user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN task_templates.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN task_templates.updated_at IS \'(DC2Type:datetime_immutable)\'');

        // Create task_executions table
        $this->addSql('CREATE TABLE task_executions (
            id UUID NOT NULL,
            task_template_id UUID DEFAULT NULL,
            name VARCHAR(255) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            points INTEGER DEFAULT NULL,
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
        $this->addSql('CREATE INDEX IDX_task_executions_task_template_id ON task_executions (task_template_id)');
        $this->addSql('CREATE INDEX IDX_task_executions_assigned_user_id ON task_executions (assigned_user_id)');
        $this->addSql('CREATE INDEX IDX_task_executions_scheduled_for ON task_executions (scheduled_for)');
        // Foreign keys use ON DELETE SET NULL to preserve historical data when templates/users are deleted
        $this->addSql('ALTER TABLE task_executions ADD CONSTRAINT FK_task_executions_task_template_id FOREIGN KEY (task_template_id) REFERENCES task_templates (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_executions ADD CONSTRAINT FK_task_executions_assigned_user_id FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_executions ADD CONSTRAINT FK_task_executions_completed_by_user_id FOREIGN KEY (completed_by_user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_executions ADD CONSTRAINT FK_task_executions_approved_by_admin_id FOREIGN KEY (approved_by_admin_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('COMMENT ON COLUMN task_executions.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN task_executions.task_template_id IS \'(DC2Type:uuid)\'');
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
        // Drop foreign key constraints first
        $this->addSql('ALTER TABLE task_executions DROP CONSTRAINT IF EXISTS FK_task_executions_task_template_id');
        $this->addSql('ALTER TABLE task_executions DROP CONSTRAINT IF EXISTS FK_task_executions_assigned_user_id');
        $this->addSql('ALTER TABLE task_executions DROP CONSTRAINT IF EXISTS FK_task_executions_completed_by_user_id');
        $this->addSql('ALTER TABLE task_executions DROP CONSTRAINT IF EXISTS FK_task_executions_approved_by_admin_id');
        $this->addSql('ALTER TABLE task_templates DROP CONSTRAINT IF EXISTS FK_task_templates_assigned_user_id');
        
        // Drop tables in reverse order
        $this->addSql('DROP TABLE task_executions');
        $this->addSql('DROP TABLE task_templates');
    }
}
