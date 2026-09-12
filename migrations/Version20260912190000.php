<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Relate parties through the roles they play, and record the responsibilities those roles carry';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS party_roles (
            id VARCHAR(36) NOT NULL,
            party_id VARCHAR(36) NOT NULL,
            type VARCHAR(50) NOT NULL,
            started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            ended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_party_roles_party ON party_roles (party_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_party_roles_type ON party_roles (type)');
        $this->addSql('ALTER TABLE party_roles
            ADD CONSTRAINT fk_party_roles_party FOREIGN KEY (party_id) REFERENCES parties (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE IF NOT EXISTS party_responsibilities (
            id VARCHAR(36) NOT NULL,
            party_role_id VARCHAR(36) NOT NULL,
            type VARCHAR(50) NOT NULL,
            started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            ended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_responsibilities_role ON party_responsibilities (party_role_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_responsibilities_type ON party_responsibilities (type)');
        $this->addSql('ALTER TABLE party_responsibilities
            ADD CONSTRAINT fk_responsibilities_role FOREIGN KEY (party_role_id) REFERENCES party_roles (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE IF NOT EXISTS party_signatures (
            id VARCHAR(36) NOT NULL,
            signatory_id VARCHAR(36) NOT NULL,
            act VARCHAR(50) NOT NULL,
            subject_id VARCHAR(36) NOT NULL,
            signed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_signatures_signatory ON party_signatures (signatory_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_signatures_subject ON party_signatures (subject_id)');
        $this->addSql('ALTER TABLE party_signatures
            ADD CONSTRAINT fk_signatures_signatory FOREIGN KEY (signatory_id) REFERENCES party_roles (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE party_relationships ADD from_party_role_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE party_relationships ADD to_party_role_id VARCHAR(36) DEFAULT NULL');

        $this->addSql("INSERT INTO party_roles (id, party_id, type, started_at)
            SELECT DISTINCT ON (rel.from_party_id, rel.type)
                md5(rel.from_party_id || CASE WHEN rel.type = 'ADMIN_OF' THEN 'TEAM_ADMIN' ELSE 'TEAM_MEMBER' END)::uuid::text,
                rel.from_party_id,
                CASE WHEN rel.type = 'ADMIN_OF' THEN 'TEAM_ADMIN' ELSE 'TEAM_MEMBER' END,
                rel.created_at
            FROM party_relationships rel
            ON CONFLICT DO NOTHING");

        $this->addSql("INSERT INTO party_roles (id, party_id, type, started_at)
            SELECT DISTINCT ON (rel.to_party_id)
                md5(rel.to_party_id || 'TEAM')::uuid::text,
                rel.to_party_id,
                'TEAM',
                rel.created_at
            FROM party_relationships rel
            ON CONFLICT DO NOTHING");

        $this->addSql("UPDATE party_relationships SET
            from_party_role_id = md5(from_party_id || CASE WHEN type = 'ADMIN_OF' THEN 'TEAM_ADMIN' ELSE 'TEAM_MEMBER' END)::uuid::text,
            to_party_role_id = md5(to_party_id || 'TEAM')::uuid::text");

        $this->addSql('DELETE FROM party_relationships WHERE from_party_role_id IS NULL OR to_party_role_id IS NULL');

        $this->addSql('ALTER TABLE party_relationships ALTER COLUMN from_party_role_id SET NOT NULL');
        $this->addSql('ALTER TABLE party_relationships ALTER COLUMN to_party_role_id SET NOT NULL');

        $this->addSql('ALTER TABLE party_relationships DROP CONSTRAINT IF EXISTS fk_party_rel_from');
        $this->addSql('ALTER TABLE party_relationships DROP CONSTRAINT IF EXISTS fk_party_rel_to');
        $this->addSql('ALTER TABLE party_relationships DROP COLUMN IF EXISTS from_party_id');
        $this->addSql('ALTER TABLE party_relationships DROP COLUMN IF EXISTS to_party_id');

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_rel_from_role ON party_relationships (from_party_role_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_rel_to_role ON party_relationships (to_party_role_id)');
        $this->addSql('ALTER TABLE party_relationships
            ADD CONSTRAINT fk_party_rel_from_role FOREIGN KEY (from_party_role_id) REFERENCES party_roles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE party_relationships
            ADD CONSTRAINT fk_party_rel_to_role FOREIGN KEY (to_party_role_id) REFERENCES party_roles (id) ON DELETE CASCADE');

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
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS party_signatures');
        $this->addSql('DROP TABLE IF EXISTS party_responsibilities');
        $this->addSql('ALTER TABLE party_relationships ADD from_party_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE party_relationships ADD to_party_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('UPDATE party_relationships SET
            from_party_id = (SELECT party_id FROM party_roles WHERE id = from_party_role_id),
            to_party_id = (SELECT party_id FROM party_roles WHERE id = to_party_role_id)');
        $this->addSql('ALTER TABLE party_relationships DROP COLUMN from_party_role_id');
        $this->addSql('ALTER TABLE party_relationships DROP COLUMN to_party_role_id');
        $this->addSql('DROP TABLE IF EXISTS party_roles');
    }
}
