<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912164826 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store how many times a task type may be taken in its window';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_templates ADD execution_limit VARCHAR(100) DEFAULT NULL');
        $this->addSql('UPDATE task_templates SET execution_limit = \'{"type":"unlimited"}\' WHERE execution_limit IS NULL');
        $this->addSql('ALTER TABLE task_templates ALTER COLUMN execution_limit SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_templates DROP execution_limit');
    }
}
