<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251215155000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tasks table for one-off task management';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS tasks (
            id UUID NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            points INTEGER NOT NULL,
            frequency VARCHAR(50) NOT NULL,
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
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_TASKS_STATUS ON tasks (status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_TASKS_ASSIGNED_USER ON tasks (assigned_user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tasks');
    }
}
