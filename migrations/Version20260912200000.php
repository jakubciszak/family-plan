<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Carry team membership over to the party graph and retire the team_members table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO parties (id, type, party_type, name, email, created_at)
            SELECT DISTINCT u.id, 'PERSON', 'PERSON', u.name, u.email, u.created_at
            FROM users u
            JOIN team_members tm ON tm.user_id = u.id
            WHERE NOT EXISTS (SELECT 1 FROM parties p WHERE p.id = u.id)");

        $this->addSql("INSERT INTO parties (id, type, party_type, name, description, created_at)
            SELECT DISTINCT t.id, 'ORGANIZATION', 'ORGANIZATION', t.name, t.description, t.created_at
            FROM teams t
            JOIN team_members tm ON tm.team_id = t.id
            WHERE NOT EXISTS (SELECT 1 FROM parties p WHERE p.id = t.id)");

        $this->addSql("INSERT INTO party_roles (id, party_id, type, started_at)
            SELECT DISTINCT ON (tm.user_id, tm.role)
                md5(tm.user_id || CASE WHEN tm.role = 'admin' THEN 'TEAM_ADMIN' ELSE 'TEAM_MEMBER' END)::uuid::text,
                tm.user_id,
                CASE WHEN tm.role = 'admin' THEN 'TEAM_ADMIN' ELSE 'TEAM_MEMBER' END,
                tm.joined_at
            FROM team_members tm
            JOIN users u ON u.id = tm.user_id
            JOIN teams t ON t.id = tm.team_id
            ON CONFLICT DO NOTHING");

        $this->addSql("INSERT INTO party_roles (id, party_id, type, started_at)
            SELECT DISTINCT ON (tm.team_id)
                md5(tm.team_id || 'TEAM')::uuid::text, tm.team_id, 'TEAM', tm.joined_at
            FROM team_members tm
            JOIN users u ON u.id = tm.user_id
            JOIN teams t ON t.id = tm.team_id
            ON CONFLICT DO NOTHING");

        $this->addSql("INSERT INTO party_responsibilities (id, party_role_id, type, started_at)
            SELECT md5(r.id || t.type)::uuid::text, r.id, t.type, r.started_at
            FROM party_roles r
            JOIN (VALUES
                ('TEAM_ADMIN', 'DEFINE_TASK_TYPE'),
                ('TEAM_ADMIN', 'TAKE_TASK'),
                ('TEAM_ADMIN', 'ASSIGN_TASK'),
                ('TEAM_ADMIN', 'COMPLETE_TASK'),
                ('TEAM_ADMIN', 'APPROVE_TASK'),
                ('TEAM_MEMBER', 'TAKE_TASK'),
                ('TEAM_MEMBER', 'COMPLETE_TASK')
            ) AS t(role_type, type) ON t.role_type = r.type
            ON CONFLICT DO NOTHING");

        $this->addSql("INSERT INTO party_relationships (id, from_party_role_id, to_party_role_id, type, created_at)
            SELECT
                tm.id,
                md5(tm.user_id || CASE WHEN tm.role = 'admin' THEN 'TEAM_ADMIN' ELSE 'TEAM_MEMBER' END)::uuid::text,
                md5(tm.team_id || 'TEAM')::uuid::text,
                CASE WHEN tm.role = 'admin' THEN 'ADMIN_OF' ELSE 'MEMBER_OF' END,
                tm.joined_at
            FROM team_members tm
            JOIN users u ON u.id = tm.user_id
            JOIN teams t ON t.id = tm.team_id
            WHERE NOT EXISTS (
                SELECT 1 FROM party_relationships existing
                JOIN party_roles fr ON fr.id = existing.from_party_role_id
                JOIN party_roles tr ON tr.id = existing.to_party_role_id
                WHERE fr.party_id = tm.user_id
                  AND tr.party_id = tm.team_id
                  AND existing.ended_at IS NULL
            )
            ON CONFLICT DO NOTHING");

        $this->addSql('DROP TABLE IF EXISTS team_members');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS team_members (
            id VARCHAR(36) NOT NULL,
            team_id VARCHAR(36) NOT NULL,
            user_id VARCHAR(36) NOT NULL,
            role VARCHAR(50) NOT NULL,
            joined_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql("INSERT INTO team_members (id, team_id, user_id, role, joined_at)
            SELECT rel.id, tr.party_id, fr.party_id,
                CASE WHEN rel.type = 'ADMIN_OF' THEN 'admin' ELSE 'member' END,
                rel.created_at
            FROM party_relationships rel
            JOIN party_roles fr ON fr.id = rel.from_party_role_id
            JOIN party_roles tr ON tr.id = rel.to_party_role_id
            WHERE rel.ended_at IS NULL");
    }
}
