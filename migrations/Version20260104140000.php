<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create team management tables: teams, team_members, team_invitations
 */
final class Version20260104140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create team management tables: teams, team_members, team_invitations';
    }

    public function up(Schema $schema): void
    {
        // Create teams table
        $this->addSql('CREATE TABLE teams (
            id UUID NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            created_by UUID NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('COMMENT ON COLUMN teams.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN teams.name IS \'(DC2Type:team_name)\'');
        $this->addSql('COMMENT ON COLUMN teams.created_by IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN teams.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN teams.updated_at IS \'(DC2Type:datetime_immutable)\'');

        // Create team_members table
        $this->addSql('CREATE TABLE team_members (
            id UUID NOT NULL,
            team_id UUID NOT NULL,
            user_id UUID NOT NULL,
            role VARCHAR(50) NOT NULL,
            joined_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_BAC46E24296CD8AE ON team_members (team_id)');
        $this->addSql('CREATE INDEX IDX_BAC46E24A76ED395 ON team_members (user_id)');
        $this->addSql('COMMENT ON COLUMN team_members.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN team_members.team_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN team_members.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN team_members.role IS \'(DC2Type:team_role)\'');
        $this->addSql('COMMENT ON COLUMN team_members.joined_at IS \'(DC2Type:datetime_immutable)\'');

        // Create team_invitations table
        $this->addSql('CREATE TABLE team_invitations (
            id UUID NOT NULL,
            team_id UUID NOT NULL,
            email VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            invited_by UUID NOT NULL,
            token VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            responded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_TEAM_INV_TEAM_ID ON team_invitations (team_id)');
        $this->addSql('CREATE INDEX IDX_TEAM_INV_EMAIL ON team_invitations (email)');
        $this->addSql('CREATE INDEX IDX_TEAM_INV_STATUS ON team_invitations (status)');
        $this->addSql('COMMENT ON COLUMN team_invitations.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN team_invitations.team_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN team_invitations.email IS \'(DC2Type:email)\'');
        $this->addSql('COMMENT ON COLUMN team_invitations.role IS \'(DC2Type:team_role)\'');
        $this->addSql('COMMENT ON COLUMN team_invitations.status IS \'(DC2Type:invitation_status)\'');
        $this->addSql('COMMENT ON COLUMN team_invitations.invited_by IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN team_invitations.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN team_invitations.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN team_invitations.responded_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE team_invitations');
        $this->addSql('DROP TABLE team_members');
        $this->addSql('DROP TABLE teams');
    }
}
