<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notifications know their event, topic, expiry and resolution; phones get their own push devices; the old backlog is tidied up';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE in_app_notifications ADD event VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE in_app_notifications ADD topic VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE in_app_notifications ADD expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE in_app_notifications ADD resolved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_in_app_notifications_topic ON in_app_notifications (topic)');

        // What older notifications were about, read back from their parameters.
        $this->addSql("UPDATE in_app_notifications SET event = LEFT(parameters->>'event', 50) WHERE event IS NULL AND parameters->>'event' IS NOT NULL");
        $this->addSql("UPDATE in_app_notifications SET event = 'task_completed' WHERE event IS NULL AND subject = 'Zadanie do akceptacji' AND parameters->>'task_id' IS NOT NULL");
        $this->addSql("UPDATE in_app_notifications SET event = 'payout_offered' WHERE event IS NULL AND parameters->>'payout_id' IS NOT NULL");
        $this->addSql("UPDATE in_app_notifications SET event = 'streak_at_risk' WHERE event IS NULL AND parameters->>'tag' = 'streak-at-risk'");
        $this->addSql("UPDATE in_app_notifications SET topic = LEFT(parameters->>'tag', 120) WHERE topic IS NULL AND parameters->>'tag' IS NOT NULL");

        // A streak warning is only true until the midnight after it was sent.
        $this->addSql("UPDATE in_app_notifications SET expires_at = date_trunc('day', created_at) + INTERVAL '1 day' WHERE event = 'streak_at_risk' AND expires_at IS NULL");

        // Approval requests for tasks nobody has to approve any more.
        $this->addSql(<<<'SQL'
            UPDATE in_app_notifications n
            SET resolved_at = NOW(), read_at = COALESCE(n.read_at, NOW())
            WHERE n.event = 'task_completed'
              AND n.resolved_at IS NULL
              AND NOT EXISTS (
                  SELECT 1 FROM task_executions e
                  WHERE e.id::text = n.parameters->>'task_id' AND e.status = 'completed'
              )
            SQL);

        // Payouts that were already confirmed or cancelled.
        $this->addSql(<<<'SQL'
            UPDATE in_app_notifications n
            SET resolved_at = NOW(), read_at = COALESCE(n.read_at, NOW())
            WHERE n.event = 'payout_offered'
              AND n.resolved_at IS NULL
              AND NOT EXISTS (
                  SELECT 1 FROM allowance_payouts p
                  WHERE p.id::text = n.parameters->>'payout_id' AND p.status = 'awaiting_confirmation'
              )
            SQL);

        // Only the newest word on a topic stays open for each recipient.
        $this->addSql(<<<'SQL'
            UPDATE in_app_notifications n
            SET resolved_at = NOW(), read_at = COALESCE(n.read_at, NOW())
            WHERE n.resolved_at IS NULL
              AND n.topic IS NOT NULL
              AND EXISTS (
                  SELECT 1 FROM in_app_notifications m
                  WHERE m.user_id = n.user_id AND m.topic = n.topic AND m.created_at > n.created_at
              )
            SQL);

        $this->addSql('CREATE TABLE native_push_devices (
            id VARCHAR(36) NOT NULL,
            user_id VARCHAR(36) NOT NULL,
            platform VARCHAR(20) NOT NULL,
            token VARCHAR(512) NOT NULL,
            device_label VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_native_push_devices_token ON native_push_devices (token)');
        $this->addSql('CREATE INDEX idx_native_push_devices_user ON native_push_devices (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE native_push_devices');
        $this->addSql('DROP INDEX idx_in_app_notifications_topic');
        $this->addSql('ALTER TABLE in_app_notifications DROP resolved_at');
        $this->addSql('ALTER TABLE in_app_notifications DROP expires_at');
        $this->addSql('ALTER TABLE in_app_notifications DROP topic');
        $this->addSql('ALTER TABLE in_app_notifications DROP event');
    }
}
