<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Everyone makes the app their own: a face, a colour, a backdrop and their own arrangement of it';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE personalisations (
            id VARCHAR(36) NOT NULL,
            user_id VARCHAR(36) NOT NULL,
            nickname VARCHAR(40) DEFAULT NULL,
            theme VARCHAR(7) NOT NULL,
            avatar JSON NOT NULL,
            backdrop JSON NOT NULL,
            home JSON NOT NULL,
            navigation JSON NOT NULL,
            celebrates BOOLEAN NOT NULL,
            makes_sound BOOLEAN NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql('CREATE UNIQUE INDEX uniq_personalisation_user ON personalisations (user_id)');

        $this->addSql("CREATE TABLE own_pictures (
            id VARCHAR(36) NOT NULL,
            user_id VARCHAR(36) NOT NULL,
            purpose VARCHAR(20) NOT NULL,
            mime_type VARCHAR(30) NOT NULL,
            width INT NOT NULL,
            height INT NOT NULL,
            byte_size INT NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql('CREATE INDEX idx_own_picture_user_purpose ON own_pictures (user_id, purpose)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE own_pictures');
        $this->addSql('DROP TABLE personalisations');
    }
}
