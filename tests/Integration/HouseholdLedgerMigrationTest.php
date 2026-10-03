<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\AbortMigration;
use DoctrineMigrations\Version20261004003000;
use Psr\Log\NullLogger;

final class HouseholdLedgerMigrationTest extends IntegrationTestCase
{
    public function testMigrationPreservesBalancesAndScopesEveryHistoricalRecord(): void
    {
        $db = $this->legacyDatabase();
        $migration = new Version20261004003000($db, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $db->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
        }
        foreach (['points_accounts', 'user_wallets', 'allowance_accounts', 'allowance_transactions', 'allowance_payouts', 'allowance_goals'] as $table) {
            $this->assertSame('home-a', $db->fetchOne("SELECT team_id FROM $table"));
            $this->assertSame(1234, (int) $db->fetchOne("SELECT balance FROM $table"));
        }
        // The uniqueness guard now permits a second home, but keeps one account per home.
        $db->executeStatement("INSERT INTO allowance_accounts (user_id, kind, reference, balance, team_id) VALUES ('child', 'pending', '', 77, 'home-b')");
        $this->assertSame(2, (int) $db->fetchOne('SELECT COUNT(*) FROM allowance_accounts'));
    }

    public function testMigrationRefusesAmbiguousOwnershipBeforeChangingAnyTable(): void
    {
        $db = $this->legacyDatabase();
        $db->executeStatement("INSERT INTO allowance_week_closures (user_id, team_id, week_start) VALUES ('child', 'home-b', '2026-09-07')");
        $migration = new Version20261004003000($db, new NullLogger());
        try {
            $migration->up(new Schema());
            $this->fail('Ambiguous historical funds must not be assigned automatically');
        } catch (AbortMigration $exception) {
            $this->assertStringContainsString('multiple households', $exception->getMessage());
            $this->assertSame([], $migration->getSql());
            $this->assertSame(1234, (int) $db->fetchOne('SELECT balance FROM allowance_accounts'));
        }
    }

    private function legacyDatabase(): Connection
    {
        require_once dirname(__DIR__, 2) . '/migrations/Version20261004003000.php';
        $db = $this->service(Connection::class);
        // Transactional schema isolates legacy DDL from the application's current tables.
        $schema = 'migration_' . bin2hex(random_bytes(6));
        $db->executeStatement('CREATE SCHEMA ' . $schema);
        $db->executeStatement('SET LOCAL search_path TO ' . $schema);
        foreach (['points_accounts', 'user_wallets', 'allowance_accounts', 'allowance_transactions', 'allowance_payouts', 'allowance_goals'] as $table) {
            $db->executeStatement("CREATE TABLE $table (user_id VARCHAR(36), kind VARCHAR(30), reference VARCHAR(36), balance BIGINT)");
            $db->executeStatement("INSERT INTO $table VALUES ('child', 'pending', '', 1234)");
        }
        $db->executeStatement('CREATE TABLE allowance_week_closures (user_id VARCHAR(36), team_id VARCHAR(36), week_start DATE)');
        $db->executeStatement("INSERT INTO allowance_week_closures VALUES ('child', 'home-a', '2026-09-14')");
        $db->executeStatement('CREATE TABLE party_relationships (from_party_role_id VARCHAR(36), to_party_role_id VARCHAR(36))');
        $db->executeStatement('CREATE TABLE party_roles (id VARCHAR(36), party_id VARCHAR(36))');
        $db->executeStatement('CREATE TABLE teams (id VARCHAR(36))');
        $db->executeStatement('CREATE TABLE task_executions (assigned_user_id VARCHAR(36), task_template_id VARCHAR(36))');
        $db->executeStatement('CREATE TABLE task_templates (id VARCHAR(36), team_id VARCHAR(36))');
        $db->executeStatement('CREATE TABLE tasks (assigned_user_id VARCHAR(36), team_id VARCHAR(36))');
        foreach ([
            'uniq_account_user_kind' => ['points_accounts', 'user_id, kind'],
            'uniq_user_wallets_user_id' => ['user_wallets', 'user_id'],
            'uniq_allowance_account' => ['allowance_accounts', 'user_id, kind, reference'],
            'uniq_allowance_closure_user_week' => ['allowance_week_closures', 'user_id, week_start'],
        ] as $index => [$table, $columns]) {
            $db->executeStatement("CREATE UNIQUE INDEX $index ON $table ($columns)");
        }
        return $db;
    }
}
