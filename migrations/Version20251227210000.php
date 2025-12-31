<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for bonus_points_rules table
 */
final class Version20251227210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create bonus_points_rules table for composite task rules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE bonus_points_rules (
                id UUID NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                bonus_points VARCHAR(255) NOT NULL,
                type VARCHAR(255) NOT NULL,
                config JSON NOT NULL,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )
        ');
        
        $this->addSql('CREATE INDEX idx_bonus_points_rules_is_active ON bonus_points_rules (is_active)');
        
        $this->addSql('COMMENT ON COLUMN bonus_points_rules.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN bonus_points_rules.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN bonus_points_rules.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN bonus_points_rules.bonus_points IS \'(DC2Type:points)\'');
        $this->addSql('COMMENT ON COLUMN bonus_points_rules.type IS \'(DC2Type:rule_type)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bonus_points_rules');
    }
}
