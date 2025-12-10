<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251210214033 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE tasks (name VARCHAR(255) NOT NULL, description CLOB NOT NULL, points INTEGER NOT NULL, frequency VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, assigned_user_id VARCHAR(36) DEFAULT NULL, completed_by_user_id VARCHAR(36) DEFAULT NULL, completed_at DATETIME DEFAULT NULL, approved_by_admin_id VARCHAR(36) DEFAULT NULL, approved_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, id VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_tasks_status ON tasks (status)');
        $this->addSql('CREATE INDEX idx_tasks_assigned_user ON tasks (assigned_user_id)');
        $this->addSql('CREATE TABLE users (name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, role VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, id VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('CREATE INDEX idx_users_email ON users (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE tasks');
        $this->addSql('DROP TABLE users');
    }
}
