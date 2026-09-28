<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep legacy order item attribute snapshots nullable for orders created before POS attributes existed.';
    }

    public function up(Schema $schema): void
    {
        // Version20260928090010 introduces both columns. This migration only
        // hardens the historical snapshot to remain nullable for legacy rows.
        $this->addSql("ALTER TABLE order_items MODIFY selected_attributes JSON NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE order_items MODIFY selected_attributes JSON NOT NULL");
    }
}
