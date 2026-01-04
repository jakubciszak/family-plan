<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add user registration fields: phone_number, is_active, activation_token
 */
final class Version20260104120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user registration fields: phone_number, is_active, activation_token';
    }

    public function up(Schema $schema): void
    {
        // Add phone_number column
        $this->addSql('ALTER TABLE users ADD phone_number VARCHAR(20) DEFAULT NULL');
        
        // Add is_active column (default true for existing users)
        $this->addSql('ALTER TABLE users ADD is_active BOOLEAN NOT NULL DEFAULT TRUE');
        
        // Add activation_token column
        $this->addSql('ALTER TABLE users ADD activation_token VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove added columns
        $this->addSql('ALTER TABLE users DROP phone_number');
        $this->addSql('ALTER TABLE users DROP is_active');
        $this->addSql('ALTER TABLE users DROP activation_token');
    }
}
