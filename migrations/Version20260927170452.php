<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260927170452 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE checkout_payment_sessions CHANGE manual_discount manual_discount NUMERIC(15, 2) NOT NULL');
        $this->addSql('ALTER TABLE import_batches CHANGE created_at created_at DATETIME NOT NULL, CHANGE started_at started_at DATETIME DEFAULT NULL, CHANGE finished_at finished_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE import_row_states CHANGE started_at started_at DATETIME DEFAULT NULL, CHANGE finished_at finished_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE orders CHANGE manual_discount manual_discount NUMERIC(15, 2) NOT NULL');
        $this->addSql('DROP INDEX UNIQ_PAYMENT_WEBHOOK_SETTINGS_TOKEN ON payment_webhook_settings');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE checkout_payment_sessions CHANGE manual_discount manual_discount NUMERIC(15, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE import_batches CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE started_at started_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE finished_at finished_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE import_row_states CHANGE started_at started_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE finished_at finished_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE orders CHANGE manual_discount manual_discount NUMERIC(15, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PAYMENT_WEBHOOK_SETTINGS_TOKEN ON payment_webhook_settings (webhook_token)');
    }
}
