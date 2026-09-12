<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store identifiers the way the mapping reads them: varchar, not the native uuid type';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DO $$
            DECLARE
                constraint_record record;
                column_record record;
                restore_statement text;
                restore_statements text[] := '{}';
            BEGIN
                FOR constraint_record IN
                    SELECT conrelid::regclass AS table_name, conname, pg_get_constraintdef(oid) AS definition
                    FROM pg_constraint
                    WHERE contype = 'f' AND connamespace = 'public'::regnamespace
                LOOP
                    restore_statements := restore_statements || format(
                        'ALTER TABLE %s ADD CONSTRAINT %I %s',
                        constraint_record.table_name,
                        constraint_record.conname,
                        constraint_record.definition
                    );
                    EXECUTE format(
                        'ALTER TABLE %s DROP CONSTRAINT %I',
                        constraint_record.table_name,
                        constraint_record.conname
                    );
                END LOOP;

                FOR column_record IN
                    SELECT table_name, column_name
                    FROM information_schema.columns
                    WHERE table_schema = 'public' AND data_type = 'uuid'
                LOOP
                    EXECUTE format(
                        'ALTER TABLE %I ALTER COLUMN %I TYPE VARCHAR(36) USING %I::text',
                        column_record.table_name,
                        column_record.column_name,
                        column_record.column_name
                    );
                END LOOP;

                FOREACH restore_statement IN ARRAY restore_statements
                LOOP
                    EXECUTE restore_statement;
                END LOOP;
            END $$;
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Identifiers are varchar because the mapping reads them that way');
    }
}
