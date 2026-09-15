<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A booking the system made carries what it was about, not a sentence in one language';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE allowance_transactions ADD context JSON DEFAULT NULL');

        $this->addSql("UPDATE allowance_transactions
            SET context = json_build_object('week', substring(description from 'week of ([0-9-]+)')), description = ''
            WHERE type = 'week_closed' AND description LIKE 'Allowance for the week of %'");

        $this->addSql("UPDATE allowance_transactions
            SET context = json_build_object('week', substring(description from 'week of ([0-9-]+)')), description = ''
            WHERE type = 'week_reopened' AND description LIKE 'The week of % was opened again'");

        $this->addSql("UPDATE allowance_transactions
            SET context = json_build_object('goal', substring(description from 'Put aside for (.*)')), description = ''
            WHERE type = 'goal_allocation' AND description LIKE 'Put aside for %'");

        $this->addSql("UPDATE allowance_transactions
            SET context = json_build_object('goal', substring(description from 'Taken back from (.*)')), description = ''
            WHERE type = 'goal_release' AND description LIKE 'Taken back from %'");

        $this->addSql("UPDATE allowance_transactions
            SET context = json_build_object('goal', substring(description from '(.*) was given up on')), description = ''
            WHERE type = 'goal_release' AND description LIKE '% was given up on'");

        $this->addSql("UPDATE allowance_transactions
            SET description = ''
            WHERE type = 'payout' AND description = 'Allowance paid out'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE allowance_transactions DROP context');
    }
}
