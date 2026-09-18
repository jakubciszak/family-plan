<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enable in-app and push delivery for existing task notification policies';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->connection->fetchAllAssociative("SELECT id, channels FROM notification_policies WHERE event_name IN ('task_completed', 'task_approved')") as $policy) {
            $channels = json_decode($policy['channels'], true, 512, JSON_THROW_ON_ERROR);
            $channels = array_values(array_unique([...$channels, 'in_app', 'push']));
            $this->addSql('UPDATE notification_policies SET channels = ? WHERE id = ?', [json_encode($channels, JSON_THROW_ON_ERROR), $policy['id']]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Notification channel choices cannot be reconstructed.');
    }
}
