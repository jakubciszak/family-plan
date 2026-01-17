<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create Party archetype tables: parties (STI) and party_relationships
 */
final class Version20260117150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Party archetype tables: parties (Single Table Inheritance) and party_relationships';
    }

    public function up(Schema $schema): void
    {
        // Create parties table using Single Table Inheritance
        $this->addSql('
            CREATE TABLE parties (
                id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
                party_type VARCHAR(50) NOT NULL COMMENT \'Discriminator: PERSON or ORGANIZATION\',
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) DEFAULT NULL COMMENT \'(DC2Type:email)\',
                description TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                PRIMARY KEY(id),
                INDEX idx_party_type (party_type)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        // Create party_relationships table
        $this->addSql('
            CREATE TABLE party_relationships (
                id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
                from_party_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
                to_party_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
                type VARCHAR(50) NOT NULL COMMENT \'(DC2Type:party_relationship_type)\',
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                ended_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                PRIMARY KEY(id),
                INDEX idx_from_party (from_party_id),
                INDEX idx_to_party (to_party_id),
                INDEX idx_relationship_type (type),
                INDEX idx_active_relationships (ended_at),
                CONSTRAINT fk_party_rel_from FOREIGN KEY (from_party_id) REFERENCES parties (id) ON DELETE CASCADE,
                CONSTRAINT fk_party_rel_to FOREIGN KEY (to_party_id) REFERENCES parties (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        // Create index for common queries: finding active relationships
        $this->addSql('
            CREATE INDEX idx_active_rel_from_type
            ON party_relationships (from_party_id, type, ended_at)
        ');
    }

    public function down(Schema $schema): void
    {
        // Drop party_relationships table first (has foreign keys)
        $this->addSql('DROP TABLE IF EXISTS party_relationships');

        // Drop parties table
        $this->addSql('DROP TABLE IF EXISTS parties');
    }
}
