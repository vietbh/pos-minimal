<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260926071044 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE payment_webhook_settings (id SMALLINT UNSIGNED NOT NULL, webhook_token VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE checkout_payment_sessions CHANGE manual_discount manual_discount NUMERIC(15, 2) NOT NULL');
        $this->addSql('ALTER TABLE orders CHANGE manual_discount manual_discount NUMERIC(15, 2) NOT NULL');
        $this->addSql('DROP INDEX UNIQ_PAYMENT_BANK_ACCOUNT_WEBHOOK_TOKEN ON payment_bank_accounts');
        $this->addSql('ALTER TABLE payment_bank_accounts DROP webhook_token');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE payment_webhook_settings');
        $this->addSql('ALTER TABLE checkout_payment_sessions CHANGE manual_discount manual_discount NUMERIC(15, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE orders CHANGE manual_discount manual_discount NUMERIC(15, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE payment_bank_accounts ADD webhook_token VARCHAR(64) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PAYMENT_BANK_ACCOUNT_WEBHOOK_TOKEN ON payment_bank_accounts (webhook_token)');
    }
}
