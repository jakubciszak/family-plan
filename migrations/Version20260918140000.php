<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Team action plans, task type links and execution plan snapshots';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE action_plans ADD team_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_action_plan_team ON action_plans (team_id)');
        $this->addSql('ALTER TABLE task_templates ADD action_plan_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE task_executions ADD action_plan JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_executions DROP action_plan');
        $this->addSql('ALTER TABLE task_templates DROP action_plan_id');
        $this->addSql('DROP INDEX idx_action_plan_team');
        $this->addSql('ALTER TABLE action_plans DROP team_id');
    }
}
