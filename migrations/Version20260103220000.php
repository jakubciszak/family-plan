<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260103220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_settings table for notification preferences';
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
        // Create user_settings table
        $this->addSql('CREATE TABLE user_settings (
            id SERIAL PRIMARY KEY,
            user_id UUID NOT NULL,
            preferences JSON NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            CONSTRAINT UNIQ_user_settings_user_id UNIQUE (user_id)
        )');
        
        $this->addSql('CREATE INDEX IDX_user_settings_user_id ON user_settings (user_id)');
        $this->addSql('COMMENT ON COLUMN user_settings.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN user_settings.preferences IS \'(DC2Type:user_preferences)\'');
        $this->addSql('COMMENT ON COLUMN user_settings.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_settings.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE status_change_rules');
        // Drop user_settings table
        $this->addSql('DROP TABLE user_settings');
    }
}
