<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist editable import preview row payloads.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE import_row_states ADD payload_json JSON DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_row_states DROP payload_json');
    }
}
