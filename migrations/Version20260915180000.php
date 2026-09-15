<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remember which devices a user wants push notifications on';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS push_subscriptions (
            id VARCHAR(36) NOT NULL,
            user_id VARCHAR(36) NOT NULL,
            endpoint VARCHAR(500) NOT NULL,
            public_key VARCHAR(255) NOT NULL,
            auth_token VARCHAR(255) NOT NULL,
            device_label VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_push_subscriptions_user ON push_subscriptions (user_id)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_push_subscriptions_endpoint ON push_subscriptions (endpoint)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS push_subscriptions');
    }
}
