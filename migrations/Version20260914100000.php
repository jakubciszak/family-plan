<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Split the points wallet into accounts with entries, rebuilt from approved executions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE points_accounts (
            id VARCHAR(36) NOT NULL,
            user_id VARCHAR(36) NOT NULL,
            kind VARCHAR(30) NOT NULL,
            balance INT NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql('CREATE UNIQUE INDEX uniq_account_user_kind ON points_accounts (user_id, kind)');
        $this->addSql('CREATE INDEX idx_points_accounts_user ON points_accounts (user_id)');

        $this->addSql("CREATE TABLE points_entries (
            id VARCHAR(36) NOT NULL,
            account_id VARCHAR(36) NOT NULL,
            amount INT NOT NULL,
            booked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            source VARCHAR(30) NOT NULL,
            reference VARCHAR(36) DEFAULT NULL,
            period_key VARCHAR(20) DEFAULT NULL,
            description VARCHAR(255) NOT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql('CREATE UNIQUE INDEX uniq_entry_account_reference_period ON points_entries (account_id, reference, period_key)');
        $this->addSql('CREATE INDEX idx_points_entries_account_booked ON points_entries (account_id, booked_at)');

        // Every user with a wallet gets a task account and a bonus account.
        foreach (['tasks', 'bonuses'] as $kind) {
            $this->addSql("INSERT INTO points_accounts (id, user_id, kind, balance, created_at)
                SELECT md5(random()::text || clock_timestamp()::text)::uuid::text, w.user_id, '{$kind}', 0, w.created_at
                FROM user_wallets w");
        }

        // Task points are recoverable from the approved executions themselves.
        $this->addSql("INSERT INTO points_entries (id, account_id, amount, booked_at, source, reference, period_key, description)
            SELECT md5(random()::text || clock_timestamp()::text)::uuid::text,
                   a.id,
                   e.points,
                   COALESCE(e.approved_at, e.completed_at, e.created_at),
                   'task_execution',
                   e.id,
                   'execution',
                   COALESCE(e.name, 'Task execution')
            FROM task_executions e
            JOIN points_accounts a ON a.user_id = e.assigned_user_id AND a.kind = 'tasks'
            WHERE e.status = 'approved' AND e.points IS NOT NULL AND e.points > 0");

        $this->addSql("UPDATE points_accounts a
            SET balance = COALESCE((SELECT SUM(en.amount) FROM points_entries en WHERE en.account_id = a.id), 0)
            WHERE a.kind = 'tasks'");

        // Whatever the wallet held beyond that has no recoverable history - book it as an opening balance.
        $this->addSql("INSERT INTO points_entries (id, account_id, amount, booked_at, source, reference, period_key, description)
            SELECT md5(random()::text || clock_timestamp()::text)::uuid::text,
                   a.id,
                   w.balance - a.balance,
                   w.created_at,
                   'opening_balance',
                   NULL,
                   NULL,
                   'Opening balance carried over from the wallet'
            FROM points_accounts a
            JOIN user_wallets w ON w.user_id = a.user_id
            WHERE a.kind = 'tasks' AND w.balance <> a.balance");

        $this->addSql("UPDATE points_accounts a
            SET balance = COALESCE((SELECT SUM(en.amount) FROM points_entries en WHERE en.account_id = a.id), 0)
            WHERE a.kind = 'tasks'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE points_entries');
        $this->addSql('DROP TABLE points_accounts');
    }
}
