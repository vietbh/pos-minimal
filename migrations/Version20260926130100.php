<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926130100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fixed manual discount snapshots to orders and bank checkout sessions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD manual_discount NUMERIC(15, 2) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE checkout_payment_sessions ADD manual_discount NUMERIC(15, 2) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE checkout_payment_sessions DROP manual_discount');
        $this->addSql('ALTER TABLE orders DROP manual_discount');
    }
}
