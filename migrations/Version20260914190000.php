<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A mailbox of notifications the application shows the user itself';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE in_app_notifications (
            id VARCHAR(36) NOT NULL,
            user_id VARCHAR(36) NOT NULL,
            message TEXT NOT NULL,
            subject VARCHAR(255) DEFAULT NULL,
            parameters JSON NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql('CREATE INDEX idx_in_app_notifications_user_read ON in_app_notifications (user_id, read_at)');
        $this->addSql('CREATE INDEX idx_in_app_notifications_user_created ON in_app_notifications (user_id, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE in_app_notifications');
    }
}
