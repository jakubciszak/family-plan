<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914191000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'An admin decides which events are communicated and through which channels';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE notification_policies (
            id VARCHAR(36) NOT NULL,
            event_name VARCHAR(50) NOT NULL,
            channels JSON NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql('CREATE UNIQUE INDEX uniq_notification_policy_event ON notification_policies (event_name)');

        foreach (['task_completed', 'task_approved', 'user_welcome'] as $event) {
            $this->addSql("INSERT INTO notification_policies (id, event_name, channels, updated_at)
                VALUES (gen_random_uuid()::text, '{$event}', '[\"email\"]', NOW())");
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification_policies');
    }
}
