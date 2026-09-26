<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260926044454 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE checkout_payment_sessions ADD discount_percent SMALLINT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE customers ADD default_discount_percent SMALLINT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE orders ADD discount NUMERIC(15, 2) NOT NULL, ADD discount_percent SMALLINT UNSIGNED NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE checkout_payment_sessions DROP discount_percent');
        $this->addSql('ALTER TABLE customers DROP default_discount_percent');
        $this->addSql('ALTER TABLE orders DROP discount, DROP discount_percent');
    }
}
