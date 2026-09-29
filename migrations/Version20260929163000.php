<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929163000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enable payment sound by default for existing users and keep the database default enabled.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN payment_sound_enabled SET DEFAULT 1');
        $this->addSql('UPDATE users SET payment_sound_enabled = 1 WHERE payment_sound_enabled = 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN payment_sound_enabled SET DEFAULT 0');
    }
}
