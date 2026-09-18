<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915203410 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Theme mode and language join the rest of what someone sets about their own look, language empty until someone picks one';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE personalisations ADD theme_mode VARCHAR(10) NOT NULL DEFAULT 'system'");
        $this->addSql('ALTER TABLE personalisations ADD language VARCHAR(5) DEFAULT NULL');
        $this->addSql('ALTER TABLE personalisations ALTER theme_mode DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE personalisations DROP theme_mode');
        $this->addSql('ALTER TABLE personalisations DROP language');
    }
}
