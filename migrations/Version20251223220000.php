<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251223220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add points column to users table and new status to task-related tables';
    }

    public function up(Schema $schema): void
    {
        // Add points column to users table
        $this->addSql('ALTER TABLE users ADD COLUMN points INTEGER DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove points column from users table
        $this->addSql('ALTER TABLE users DROP COLUMN points');
    }
}
