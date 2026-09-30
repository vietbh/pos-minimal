<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-user Dashboard default tab preference.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD dashboard_default_tab VARCHAR(32) DEFAULT 'auto' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP dashboard_default_tab');
    }
}
