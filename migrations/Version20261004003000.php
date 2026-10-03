<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Existing ledgers can only be assigned automatically when their owner has one
 * unambiguous historical household. Never copy a balance into both homes.
 */
final class Version20261004003000 extends AbstractMigration
{
    private const TABLES = ['points_accounts', 'user_wallets', 'allowance_accounts', 'allowance_transactions', 'allowance_payouts', 'allowance_goals'];

    public function getDescription(): string
    {
        return 'Separate point and money ledgers by household, preserving existing single-household balances';
    }

    public function up(Schema $schema): void
    {
        $owners = [];
        foreach (self::TABLES as $table) {
            foreach ($this->connection->fetchFirstColumn('SELECT DISTINCT user_id FROM ' . $table) as $owner) {
                $owners[$owner] = true;
            }
        }
        $assignments = [];
        foreach (array_keys($owners) as $owner) {
            $teams = $this->connection->fetchFirstColumn(<<<'SQL'
                SELECT DISTINCT team_id FROM (
                    SELECT target.party_id AS team_id
                    FROM party_relationships relationship
                    JOIN party_roles source ON source.id = relationship.from_party_role_id
                    JOIN party_roles target ON target.id = relationship.to_party_role_id
                    JOIN teams team ON team.id = target.party_id
                    WHERE source.party_id = ?
                    UNION SELECT team_id FROM allowance_week_closures WHERE user_id = ?
                    UNION SELECT template.team_id FROM task_executions execution
                        JOIN task_templates template ON template.id = execution.task_template_id
                        WHERE execution.assigned_user_id = ? AND template.team_id IS NOT NULL
                    UNION SELECT team_id FROM tasks WHERE assigned_user_id = ? AND team_id IS NOT NULL
                ) households
                SQL, [$owner, $owner, $owner, $owner]);
            $this->abortIf(count($teams) > 1, sprintf(
                'User %s has financial history and multiple households. Allocate the historical ledger explicitly before this migration; no balances have been changed.', $owner
            ));
            $assignments[$owner] = $teams[0] ?? '';
        }
        foreach (self::TABLES as $table) {
            $this->addSql("ALTER TABLE $table ADD team_id VARCHAR(36) DEFAULT '' NOT NULL");
            foreach ($assignments as $owner => $team) {
                $this->addSql("UPDATE $table SET team_id = ? WHERE user_id = ?", [$team, $owner]);
            }
            $this->addSql("CREATE INDEX idx_{$table}_team_user ON $table (team_id, user_id)");
        }
        foreach ([
            'uniq_account_user_kind' => ['points_accounts', 'team_id, user_id, kind'],
            'uniq_user_wallets_user_id' => ['user_wallets', 'team_id, user_id'],
            'uniq_allowance_account' => ['allowance_accounts', 'team_id, user_id, kind, reference'],
            'uniq_allowance_closure_user_week' => ['allowance_week_closures', 'team_id, user_id, week_start'],
        ] as $index => [$table, $columns]) {
            $this->addSql('DROP INDEX ' . $index);
            $this->addSql("CREATE UNIQUE INDEX $index ON $table ($columns)");
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Combining independent household ledgers would lose ownership information. Restore the pre-migration backup instead.');
    }
}
