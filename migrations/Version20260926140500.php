<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926140500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Use one global webhook token for all receiving bank accounts.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('payment_webhook_settings')) {
            $this->addSql("CREATE TABLE payment_webhook_settings (id SMALLINT UNSIGNED NOT NULL, webhook_token VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id), UNIQUE INDEX UNIQ_PAYMENT_WEBHOOK_SETTINGS_TOKEN (webhook_token)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB");
        }

        $accounts = $schema->getTable('payment_bank_accounts');
        if ($accounts->hasColumn('webhook_token')) {
            $this->addSql("INSERT INTO payment_webhook_settings (id, webhook_token, created_at, updated_at) SELECT 1, COALESCE(MIN(webhook_token), SHA2(CONCAT(UUID(), '-', RAND()), 256)), NOW(), NOW() FROM payment_bank_accounts ON DUPLICATE KEY UPDATE webhook_token = webhook_token");
            if ($accounts->hasIndex('UNIQ_PAYMENT_BANK_ACCOUNT_WEBHOOK_TOKEN')) {
                $this->addSql('DROP INDEX UNIQ_PAYMENT_BANK_ACCOUNT_WEBHOOK_TOKEN ON payment_bank_accounts');
            }
            $this->addSql('ALTER TABLE payment_bank_accounts DROP webhook_token');
        } else {
            $this->addSql("INSERT INTO payment_webhook_settings (id, webhook_token, created_at, updated_at) SELECT 1, SHA2(CONCAT(UUID(), '-', RAND()), 256), NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM payment_webhook_settings WHERE id = 1)");
        }
    }

    public function down(Schema $schema): void
    {
        $accounts = $schema->getTable('payment_bank_accounts');
        if (!$accounts->hasColumn('webhook_token')) {
            $this->addSql('ALTER TABLE payment_bank_accounts ADD webhook_token VARCHAR(64) DEFAULT NULL');
            $this->addSql("UPDATE payment_bank_accounts SET webhook_token = SHA2(CONCAT(UUID(), '-', id, '-', RAND()), 256) WHERE webhook_token IS NULL OR webhook_token = ''");
            if (!$accounts->hasIndex('UNIQ_PAYMENT_BANK_ACCOUNT_WEBHOOK_TOKEN')) {
                $this->addSql('CREATE UNIQUE INDEX UNIQ_PAYMENT_BANK_ACCOUNT_WEBHOOK_TOKEN ON payment_bank_accounts (webhook_token)');
            }
            $this->addSql('ALTER TABLE payment_bank_accounts MODIFY webhook_token VARCHAR(64) NOT NULL');
        }

        if ($schema->hasTable('payment_webhook_settings')) {
            $this->addSql('DROP TABLE payment_webhook_settings');
        }
    }
}
