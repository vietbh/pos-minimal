<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add customer default discount and order/payment-session discount snapshots.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE customers ADD default_discount_percent SMALLINT UNSIGNED NOT NULL DEFAULT 0");
        $this->addSql("ALTER TABLE orders ADD discount NUMERIC(15, 2) NOT NULL DEFAULT 0, ADD discount_percent SMALLINT UNSIGNED NOT NULL DEFAULT 0");
        $this->addSql("ALTER TABLE checkout_payment_sessions ADD discount_percent SMALLINT UNSIGNED NOT NULL DEFAULT 0");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE checkout_payment_sessions DROP discount_percent');
        $this->addSql('ALTER TABLE orders DROP discount, DROP discount_percent');
        $this->addSql('ALTER TABLE customers DROP default_discount_percent');
    }
}
