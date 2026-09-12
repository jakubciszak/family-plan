<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260117150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Party archetype tables: parties (Single Table Inheritance) and party_relationships';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS parties (
            id VARCHAR(36) NOT NULL,
            party_type VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL,
            name VARCHAR(255) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_party_type ON parties (party_type)');

        $this->addSql('CREATE TABLE IF NOT EXISTS party_relationships (
            id VARCHAR(36) NOT NULL,
            from_party_id VARCHAR(36) NOT NULL,
            to_party_id VARCHAR(36) NOT NULL,
            type VARCHAR(50) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            ended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_from_party ON party_relationships (from_party_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_to_party ON party_relationships (to_party_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_relationship_type ON party_relationships (type)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_active_relationships ON party_relationships (ended_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_active_rel_from_type ON party_relationships (from_party_id, type, ended_at)');

        $this->addSql('ALTER TABLE party_relationships
            ADD CONSTRAINT fk_party_rel_from FOREIGN KEY (from_party_id) REFERENCES parties (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE party_relationships
            ADD CONSTRAINT fk_party_rel_to FOREIGN KEY (to_party_id) REFERENCES parties (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS party_relationships');
        $this->addSql('DROP TABLE IF EXISTS parties');
    }
}
