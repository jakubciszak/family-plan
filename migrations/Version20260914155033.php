<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914155033 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep the reason a task execution was sent back';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_executions ADD rejection_reason VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_executions DROP rejection_reason');
    }
}
