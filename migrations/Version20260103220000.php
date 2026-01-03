<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for status_change_rules table
 */
final class Version20260103220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create status_change_rules table for task assignment rules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE status_change_rules (
                id UUID NOT NULL,
                task_template_id UUID NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                condition_type VARCHAR(50) NOT NULL,
                config JSON NOT NULL,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )
        ');

        $this->addSql('CREATE INDEX IDX_status_change_rules_is_active ON status_change_rules (is_active)');
        $this->addSql('CREATE INDEX IDX_status_change_rules_task_template_id ON status_change_rules (task_template_id)');
        $this->addSql('COMMENT ON COLUMN status_change_rules.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN status_change_rules.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE status_change_rules');
    }
}
