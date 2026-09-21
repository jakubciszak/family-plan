<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921140100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add day planning to existing navigation layouts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE personalisations SET navigation = (navigation::jsonb || '[\"day-planning\"]'::jsonb)::json WHERE NOT navigation::jsonb @> '[\"day-planning\"]'::jsonb");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE personalisations SET navigation = (navigation::jsonb - 'day-planning')::json");
    }
}
