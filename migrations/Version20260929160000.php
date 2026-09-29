<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-user payment sound and usage guide preferences (payment sound enabled by default).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD payment_sound_enabled TINYINT(1) DEFAULT 1 NOT NULL, ADD usage_guide_enabled TINYINT(1) DEFAULT 1 NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP payment_sound_enabled, DROP usage_guide_enabled');
    }
}
