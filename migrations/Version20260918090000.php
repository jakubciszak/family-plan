<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Saved personal action plans with ordered steps and stages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE action_plans (id VARCHAR(36) NOT NULL, user_id VARCHAR(36) NOT NULL, name VARCHAR(160) NOT NULL, steps JSON NOT NULL, estimated_minutes INT DEFAULT NULL, reminder_minutes INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql("UPDATE personalisations SET navigation = (navigation::jsonb || '[\"action-plans\"]'::jsonb)::json WHERE NOT navigation::jsonb @> '[\"action-plans\"]'::jsonb");
        $this->addSql('CREATE INDEX idx_action_plan_user ON action_plans (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE personalisations SET navigation = (navigation::jsonb - 'action-plans')::json");
        $this->addSql('DROP TABLE action_plans');
    }
}
