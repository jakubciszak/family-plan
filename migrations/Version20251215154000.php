<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251215154000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create task_templates table and update task_executions to reference task templates';
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
        
        // Update task_executions table to use task_template_id
        $this->addSql('ALTER TABLE task_executions RENAME COLUMN template_task_id TO task_template_id');
    }

    public function down(Schema $schema): void
    {
        // Revert task_executions column name
        $this->addSql('ALTER TABLE task_executions RENAME COLUMN task_template_id TO template_task_id');
        
        // Drop task_templates table
        $this->addSql('DROP TABLE task_templates');
    }
}
