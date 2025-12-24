<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251223230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_wallets table and migrate existing points data';
    }

    public function up(Schema $schema): void
    {
        // Create user_wallets table
        $this->addSql('CREATE TABLE user_wallets (
            id UUID NOT NULL,
            user_id UUID NOT NULL,
            balance INTEGER DEFAULT 0 NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        
        $this->addSql('CREATE UNIQUE INDEX UNIQ_user_wallets_user_id ON user_wallets (user_id)');
        $this->addSql('COMMENT ON COLUMN user_wallets.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN user_wallets.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN user_wallets.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_wallets.updated_at IS \'(DC2Type:datetime_immutable)\'');
        
        // Migrate existing points from users table to user_wallets
        $this->addSql('
            INSERT INTO user_wallets (id, user_id, balance, created_at)
            SELECT 
                gen_random_uuid(),
                id,
                COALESCE(points, 0),
                created_at
            FROM users
        ');
        
        // Remove points column from users table (cleanup from previous implementation)
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS points');
    }

    public function down(Schema $schema): void
    {
        // Restore points column to users table
        $this->addSql('ALTER TABLE users ADD COLUMN points INTEGER DEFAULT 0 NOT NULL');
        
        // Migrate wallet balances back to users
        $this->addSql('
            UPDATE users u
            SET points = (
                SELECT balance 
                FROM user_wallets w 
                WHERE w.user_id = u.id
            )
        ');
        
        // Drop user_wallets table
        $this->addSql('DROP TABLE user_wallets');
    }
}
