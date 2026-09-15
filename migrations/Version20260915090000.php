<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pocket money: rules, closed weeks, payouts, goals and a double entry book of the money itself';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE allowance_accounts (
            id VARCHAR(36) NOT NULL,
            user_id VARCHAR(36) NOT NULL,
            kind VARCHAR(30) NOT NULL,
            reference VARCHAR(36) NOT NULL,
            balance BIGINT NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql('CREATE UNIQUE INDEX uniq_allowance_account ON allowance_accounts (user_id, kind, reference)');
        $this->addSql('CREATE INDEX idx_allowance_account_user ON allowance_accounts (user_id)');

        $this->addSql("CREATE TABLE allowance_transactions (
            id VARCHAR(36) NOT NULL,
            user_id VARCHAR(36) NOT NULL,
            type VARCHAR(30) NOT NULL,
            description VARCHAR(255) NOT NULL,
            booked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            reference VARCHAR(36) DEFAULT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql('CREATE INDEX idx_allowance_transaction_user ON allowance_transactions (user_id, booked_at)');

        $this->addSql("CREATE TABLE allowance_entries (
            id VARCHAR(36) NOT NULL,
            transaction_id VARCHAR(36) NOT NULL,
            account_id VARCHAR(36) NOT NULL,
            amount BIGINT NOT NULL,
            booked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql('CREATE INDEX idx_allowance_entry_account ON allowance_entries (account_id, booked_at)');
        $this->addSql('CREATE INDEX idx_allowance_entry_transaction ON allowance_entries (transaction_id)');

        $this->addSql("CREATE TABLE allowance_rules (
            id VARCHAR(36) NOT NULL,
            team_id VARCHAR(36) NOT NULL,
            points_account VARCHAR(30) NOT NULL,
            minimum_points INT NOT NULL,
            rate_amount BIGINT NOT NULL,
            rate_per_points INT NOT NULL,
            is_active BOOLEAN NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql('CREATE UNIQUE INDEX uniq_allowance_rule_team_account ON allowance_rules (team_id, points_account)');
        $this->addSql('CREATE INDEX idx_allowance_rule_team ON allowance_rules (team_id)');

        $this->addSql("CREATE TABLE allowance_week_closures (
            id VARCHAR(36) NOT NULL,
            team_id VARCHAR(36) NOT NULL,
            user_id VARCHAR(36) NOT NULL,
            week_start DATE NOT NULL,
            breakdown JSON NOT NULL,
            total BIGINT NOT NULL,
            closed_by VARCHAR(36) NOT NULL,
            closed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            transaction_id VARCHAR(36) DEFAULT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql('CREATE UNIQUE INDEX uniq_allowance_closure_user_week ON allowance_week_closures (user_id, week_start)');
        $this->addSql('CREATE INDEX idx_allowance_closure_team_week ON allowance_week_closures (team_id, week_start)');

        $this->addSql("CREATE TABLE allowance_payouts (
            id VARCHAR(36) NOT NULL,
            user_id VARCHAR(36) NOT NULL,
            amount BIGINT NOT NULL,
            status VARCHAR(30) NOT NULL,
            note VARCHAR(255) DEFAULT NULL,
            offered_by VARCHAR(36) NOT NULL,
            offered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            settled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            transaction_id VARCHAR(36) DEFAULT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql('CREATE INDEX idx_allowance_payout_user_status ON allowance_payouts (user_id, status)');

        $this->addSql("CREATE TABLE allowance_goals (
            id VARCHAR(36) NOT NULL,
            user_id VARCHAR(36) NOT NULL,
            name VARCHAR(120) NOT NULL,
            target BIGINT NOT NULL,
            wanted_by DATE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            reached_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql('CREATE INDEX idx_allowance_goal_user ON allowance_goals (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE allowance_goals');
        $this->addSql('DROP TABLE allowance_payouts');
        $this->addSql('DROP TABLE allowance_week_closures');
        $this->addSql('DROP TABLE allowance_rules');
        $this->addSql('DROP TABLE allowance_entries');
        $this->addSql('DROP TABLE allowance_transactions');
        $this->addSql('DROP TABLE allowance_accounts');
    }
}
