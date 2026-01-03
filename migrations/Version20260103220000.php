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
        // Drop user_settings table
        $this->addSql('DROP TABLE user_settings');
    }
}
