<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260922180410 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audit_logs (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, action VARCHAR(100) NOT NULL, entity_type VARCHAR(100) DEFAULT NULL, entity_id VARCHAR(100) DEFAULT NULL, old_values JSON DEFAULT NULL, new_values JSON DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, user_id BIGINT UNSIGNED DEFAULT NULL, session_id BIGINT UNSIGNED DEFAULT NULL, INDEX idx_audit_user_created (user_id, created_at), INDEX idx_audit_entity (entity_type, entity_id), INDEX idx_audit_action_created (action, created_at), INDEX idx_audit_created (created_at), INDEX IDX_D62F2858A76ED395 (user_id), INDEX IDX_D62F2858613FECDF (session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE checkout_payment_sessions (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, cart_snapshot JSON NOT NULL, amount NUMERIC(15, 2) NOT NULL, note LONGTEXT DEFAULT NULL, active_key VARCHAR(64) DEFAULT NULL, status VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id BIGINT UNSIGNED NOT NULL, customer_id BIGINT UNSIGNED DEFAULT NULL, payment_bank_account_id BIGINT UNSIGNED NOT NULL, order_id BIGINT UNSIGNED DEFAULT NULL, INDEX idx_checkout_session_user_status (user_id, status), UNIQUE INDEX UNIQ_CHECKOUT_SESSION_ACTIVE_KEY (active_key), INDEX IDX_6ADD4013A76ED395 (user_id), INDEX IDX_6ADD40139395C3F3 (customer_id), INDEX IDX_6ADD4013154375AF (payment_bank_account_id), INDEX IDX_6ADD40138D9F6D38 (order_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE customers (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, phone VARCHAR(30) DEFAULT NULL, note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_customer_name (name), INDEX idx_customer_phone (phone), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE debt_payments (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, amount NUMERIC(15, 2) NOT NULL, created_at DATETIME NOT NULL, debt_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, INDEX idx_debt_payment_debt_created (debt_id, created_at), INDEX idx_debt_payment_user_created (user_id, created_at), INDEX IDX_2BCE2DB5240326A5 (debt_id), INDEX IDX_2BCE2DB5A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE debts (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, original_amount NUMERIC(15, 2) NOT NULL, status VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, customer_id BIGINT UNSIGNED NOT NULL, order_id BIGINT UNSIGNED NOT NULL, created_by BIGINT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_6F64A29B8D9F6D38 (order_id), INDEX idx_debt_customer_status (customer_id, status), INDEX idx_debt_order (order_id), INDEX idx_debt_status_created (status, created_at), INDEX idx_debt_created (created_at), INDEX IDX_6F64A29B9395C3F3 (customer_id), INDEX IDX_6F64A29BDE12AB56 (created_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE external_payment_transactions (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, provider VARCHAR(30) NOT NULL, external_transaction_id VARCHAR(120) NOT NULL, amount NUMERIC(15, 2) NOT NULL, description VARCHAR(500) NOT NULL, transaction_reference VARCHAR(120) DEFAULT NULL, occurred_at DATETIME NOT NULL, status VARCHAR(30) NOT NULL, matched_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, payment_bank_account_id BIGINT UNSIGNED NOT NULL, matched_order_id BIGINT UNSIGNED DEFAULT NULL, matched_payment_id BIGINT UNSIGNED DEFAULT NULL, INDEX idx_external_payment_status (status), INDEX idx_external_payment_account (payment_bank_account_id), INDEX idx_external_payment_order (matched_order_id), INDEX IDX_EXTERNAL_PAYMENT_PAYMENT (matched_payment_id), UNIQUE INDEX uniq_external_payment_provider_tx (provider, external_transaction_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE idempotency_records (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, idempotency_key VARCHAR(255) NOT NULL, operation VARCHAR(100) NOT NULL, status VARCHAR(30) NOT NULL, request_hash VARCHAR(64) DEFAULT NULL, response_status INT DEFAULT NULL, response_body JSON DEFAULT NULL, created_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, user_id BIGINT UNSIGNED NOT NULL, INDEX idx_idempotency_user_created (user_id, created_at), INDEX idx_idempotency_status_created (status, created_at), UNIQUE INDEX uq_idempotency_user_key (user_id, idempotency_key), INDEX IDX_CBC8586CA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_financial_reversals (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, type VARCHAR(30) NOT NULL, amount NUMERIC(15, 2) NOT NULL, reason VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, order_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_C99FA1D8D9F6D38 (order_id), INDEX idx_order_financial_reversal_type_created (type, created_at), INDEX IDX_C99FA1DA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_items (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, product_name VARCHAR(255) NOT NULL, sku VARCHAR(100) DEFAULT NULL, unit_price NUMERIC(15, 2) NOT NULL, quantity INT UNSIGNED NOT NULL, subtotal NUMERIC(15, 2) NOT NULL, order_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NOT NULL, INDEX idx_order_item_order (order_id), INDEX idx_order_item_product (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE orders (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, order_number VARCHAR(50) NOT NULL, status VARCHAR(30) NOT NULL, subtotal NUMERIC(15, 2) NOT NULL, total NUMERIC(15, 2) NOT NULL, note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, user_id BIGINT UNSIGNED NOT NULL, customer_id BIGINT UNSIGNED DEFAULT NULL, UNIQUE INDEX UNIQ_E52FFDEE551F0F81 (order_number), INDEX idx_order_customer_created (customer_id, created_at), INDEX idx_order_user_created (user_id, created_at), INDEX idx_order_status_created (status, created_at), INDEX idx_order_created (created_at), INDEX IDX_E52FFDEEA76ED395 (user_id), INDEX IDX_E52FFDEE9395C3F3 (customer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE payment_bank_accounts (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, bank_bin VARCHAR(20) NOT NULL, bank_name VARCHAR(120) NOT NULL, account_number VARCHAR(80) NOT NULL, account_name VARCHAR(160) NOT NULL, qr_template VARCHAR(30) NOT NULL, transfer_content_template VARCHAR(255) NOT NULL, casso_sub_account_id VARCHAR(80) DEFAULT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_payment_bank_account_active (is_active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE payment_references (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, reference VARCHAR(40) NOT NULL, amount NUMERIC(15, 2) NOT NULL, status VARCHAR(20) NOT NULL, expires_at DATETIME NOT NULL, matched_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, order_id BIGINT UNSIGNED DEFAULT NULL, checkout_payment_session_id BIGINT UNSIGNED NOT NULL, payment_id BIGINT UNSIGNED DEFAULT NULL, payment_bank_account_id BIGINT UNSIGNED NOT NULL, INDEX idx_payment_reference_order (order_id), INDEX idx_payment_reference_session (checkout_payment_session_id), INDEX idx_payment_reference_status_expires (status, expires_at), UNIQUE INDEX UNIQ_PAYMENT_REFERENCES_REFERENCE (reference), INDEX IDX_AAE8AC494C3A3BB (payment_id), INDEX IDX_AAE8AC49154375AF (payment_bank_account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE payments (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, amount NUMERIC(15, 2) NOT NULL, method VARCHAR(30) NOT NULL, reference VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, order_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, payment_bank_account_id BIGINT UNSIGNED DEFAULT NULL, INDEX idx_payment_order_created (order_id, created_at), INDEX idx_payment_user_created (user_id, created_at), INDEX idx_payment_method_created (method, created_at), INDEX idx_payment_bank_account (payment_bank_account_id), UNIQUE INDEX UNIQ_PAYMENTS_REFERENCE (reference), INDEX IDX_65D29B328D9F6D38 (order_id), INDEX IDX_65D29B32A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_categories (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_product_category_active_name (is_active, name), UNIQUE INDEX UNIQ_PRODUCT_CATEGORY_NAME (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_images (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, storage_key VARCHAR(500) NOT NULL, original_filename VARCHAR(255) DEFAULT NULL, mime_type VARCHAR(100) NOT NULL, size BIGINT UNSIGNED NOT NULL, width INT UNSIGNED DEFAULT NULL, height INT UNSIGNED DEFAULT NULL, status VARCHAR(30) NOT NULL, sort_order INT UNSIGNED NOT NULL, is_primary TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, product_id BIGINT UNSIGNED NOT NULL, INDEX idx_product_image_product_sort (product_id, sort_order), INDEX idx_product_image_product_primary (product_id, is_primary), INDEX idx_product_image_status (status), INDEX IDX_8263FFCE4584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE products (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, sku VARCHAR(100) DEFAULT NULL, name VARCHAR(255) NOT NULL, unit VARCHAR(50) DEFAULT NULL, selling_price NUMERIC(15, 2) NOT NULL, cost_price NUMERIC(15, 2) DEFAULT NULL, stock_quantity INT UNSIGNED NOT NULL, low_stock_threshold INT UNSIGNED NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, category_id BIGINT UNSIGNED DEFAULT NULL, UNIQUE INDEX UNIQ_B3BA5A5AF9038C4 (sku), INDEX idx_product_category (category_id), INDEX idx_product_name (name), INDEX idx_product_active_name (is_active, name), INDEX idx_product_stock (stock_quantity), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE stock_movements (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, type VARCHAR(30) NOT NULL, quantity_before INT UNSIGNED NOT NULL, quantity_change INT NOT NULL, quantity_after INT UNSIGNED NOT NULL, reason VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, product_id BIGINT UNSIGNED NOT NULL, order_id BIGINT UNSIGNED DEFAULT NULL, user_id BIGINT UNSIGNED NOT NULL, session_id BIGINT UNSIGNED DEFAULT NULL, INDEX idx_stock_movement_product_created (product_id, created_at), INDEX idx_stock_movement_order (order_id), INDEX idx_stock_movement_user_created (user_id, created_at), INDEX idx_stock_movement_type_created (type, created_at), INDEX IDX_A0BE93C94584665A (product_id), INDEX IDX_A0BE93C9A76ED395 (user_id), INDEX IDX_A0BE93C9613FECDF (session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_sessions (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, session_identifier VARCHAR(255) NOT NULL, login_at DATETIME NOT NULL, last_activity_at DATETIME NOT NULL, logout_at DATETIME DEFAULT NULL, revoked_at DATETIME DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(512) DEFAULT NULL, device VARCHAR(100) DEFAULT NULL, status VARCHAR(30) NOT NULL, user_id BIGINT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_7AED791397B88831 (session_identifier), INDEX idx_user_session_status_activity (status, last_activity_at), INDEX idx_user_session_user_status (user_id, status), INDEX IDX_7AED7913A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, password_hash VARCHAR(255) DEFAULT NULL, roles JSON NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, font_size VARCHAR(10) DEFAULT \'medium\' NOT NULL, appearance VARCHAR(10) DEFAULT \'system\' NOT NULL, ui_density VARCHAR(12) DEFAULT \'comfortable\' NOT NULL, high_contrast TINYINT DEFAULT 0 NOT NULL, reduce_motion TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_1483A5E9F85E0677 (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT FK_D62F2858A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT FK_D62F2858613FECDF FOREIGN KEY (session_id) REFERENCES user_sessions (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE checkout_payment_sessions ADD CONSTRAINT FK_6ADD4013A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE checkout_payment_sessions ADD CONSTRAINT FK_6ADD40139395C3F3 FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE checkout_payment_sessions ADD CONSTRAINT FK_6ADD4013154375AF FOREIGN KEY (payment_bank_account_id) REFERENCES payment_bank_accounts (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE checkout_payment_sessions ADD CONSTRAINT FK_6ADD40138D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE debt_payments ADD CONSTRAINT FK_2BCE2DB5240326A5 FOREIGN KEY (debt_id) REFERENCES debts (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE debt_payments ADD CONSTRAINT FK_2BCE2DB5A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE debts ADD CONSTRAINT FK_6F64A29B9395C3F3 FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE debts ADD CONSTRAINT FK_6F64A29B8D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE debts ADD CONSTRAINT FK_6F64A29BDE12AB56 FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE external_payment_transactions ADD CONSTRAINT FK_471FF844154375AF FOREIGN KEY (payment_bank_account_id) REFERENCES payment_bank_accounts (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE external_payment_transactions ADD CONSTRAINT FK_471FF844F508283E FOREIGN KEY (matched_order_id) REFERENCES orders (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE external_payment_transactions ADD CONSTRAINT FK_471FF844542FC9B5 FOREIGN KEY (matched_payment_id) REFERENCES payments (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE idempotency_records ADD CONSTRAINT FK_CBC8586CA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE order_financial_reversals ADD CONSTRAINT FK_C99FA1D8D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE order_financial_reversals ADD CONSTRAINT FK_C99FA1DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB08D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB04584665A FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEEA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE9395C3F3 FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE payment_references ADD CONSTRAINT FK_AAE8AC498D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE payment_references ADD CONSTRAINT FK_AAE8AC49995DB20B FOREIGN KEY (checkout_payment_session_id) REFERENCES checkout_payment_sessions (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE payment_references ADD CONSTRAINT FK_AAE8AC494C3A3BB FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE payment_references ADD CONSTRAINT FK_AAE8AC49154375AF FOREIGN KEY (payment_bank_account_id) REFERENCES payment_bank_accounts (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT FK_65D29B328D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT FK_65D29B32A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT FK_65D29B32154375AF FOREIGN KEY (payment_bank_account_id) REFERENCES payment_bank_accounts (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE product_images ADD CONSTRAINT FK_8263FFCE4584665A FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT FK_B3BA5A5A12469DE2 FOREIGN KEY (category_id) REFERENCES product_categories (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE stock_movements ADD CONSTRAINT FK_A0BE93C94584665A FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE stock_movements ADD CONSTRAINT FK_A0BE93C98D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE stock_movements ADD CONSTRAINT FK_A0BE93C9A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE stock_movements ADD CONSTRAINT FK_A0BE93C9613FECDF FOREIGN KEY (session_id) REFERENCES user_sessions (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE user_sessions ADD CONSTRAINT FK_7AED7913A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audit_logs DROP FOREIGN KEY FK_D62F2858A76ED395');
        $this->addSql('ALTER TABLE audit_logs DROP FOREIGN KEY FK_D62F2858613FECDF');
        $this->addSql('ALTER TABLE checkout_payment_sessions DROP FOREIGN KEY FK_6ADD4013A76ED395');
        $this->addSql('ALTER TABLE checkout_payment_sessions DROP FOREIGN KEY FK_6ADD40139395C3F3');
        $this->addSql('ALTER TABLE checkout_payment_sessions DROP FOREIGN KEY FK_6ADD4013154375AF');
        $this->addSql('ALTER TABLE checkout_payment_sessions DROP FOREIGN KEY FK_6ADD40138D9F6D38');
        $this->addSql('ALTER TABLE debt_payments DROP FOREIGN KEY FK_2BCE2DB5240326A5');
        $this->addSql('ALTER TABLE debt_payments DROP FOREIGN KEY FK_2BCE2DB5A76ED395');
        $this->addSql('ALTER TABLE debts DROP FOREIGN KEY FK_6F64A29B9395C3F3');
        $this->addSql('ALTER TABLE debts DROP FOREIGN KEY FK_6F64A29B8D9F6D38');
        $this->addSql('ALTER TABLE debts DROP FOREIGN KEY FK_6F64A29BDE12AB56');
        $this->addSql('ALTER TABLE external_payment_transactions DROP FOREIGN KEY FK_471FF844154375AF');
        $this->addSql('ALTER TABLE external_payment_transactions DROP FOREIGN KEY FK_471FF844F508283E');
        $this->addSql('ALTER TABLE external_payment_transactions DROP FOREIGN KEY FK_471FF844542FC9B5');
        $this->addSql('ALTER TABLE idempotency_records DROP FOREIGN KEY FK_CBC8586CA76ED395');
        $this->addSql('ALTER TABLE order_financial_reversals DROP FOREIGN KEY FK_C99FA1D8D9F6D38');
        $this->addSql('ALTER TABLE order_financial_reversals DROP FOREIGN KEY FK_C99FA1DA76ED395');
        $this->addSql('ALTER TABLE order_items DROP FOREIGN KEY FK_62809DB08D9F6D38');
        $this->addSql('ALTER TABLE order_items DROP FOREIGN KEY FK_62809DB04584665A');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEEA76ED395');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEE9395C3F3');
        $this->addSql('ALTER TABLE payment_references DROP FOREIGN KEY FK_AAE8AC498D9F6D38');
        $this->addSql('ALTER TABLE payment_references DROP FOREIGN KEY FK_AAE8AC49995DB20B');
        $this->addSql('ALTER TABLE payment_references DROP FOREIGN KEY FK_AAE8AC494C3A3BB');
        $this->addSql('ALTER TABLE payment_references DROP FOREIGN KEY FK_AAE8AC49154375AF');
        $this->addSql('ALTER TABLE payments DROP FOREIGN KEY FK_65D29B328D9F6D38');
        $this->addSql('ALTER TABLE payments DROP FOREIGN KEY FK_65D29B32A76ED395');
        $this->addSql('ALTER TABLE payments DROP FOREIGN KEY FK_65D29B32154375AF');
        $this->addSql('ALTER TABLE product_images DROP FOREIGN KEY FK_8263FFCE4584665A');
        $this->addSql('ALTER TABLE products DROP FOREIGN KEY FK_B3BA5A5A12469DE2');
        $this->addSql('ALTER TABLE stock_movements DROP FOREIGN KEY FK_A0BE93C94584665A');
        $this->addSql('ALTER TABLE stock_movements DROP FOREIGN KEY FK_A0BE93C98D9F6D38');
        $this->addSql('ALTER TABLE stock_movements DROP FOREIGN KEY FK_A0BE93C9A76ED395');
        $this->addSql('ALTER TABLE stock_movements DROP FOREIGN KEY FK_A0BE93C9613FECDF');
        $this->addSql('ALTER TABLE user_sessions DROP FOREIGN KEY FK_7AED7913A76ED395');
        $this->addSql('DROP TABLE audit_logs');
        $this->addSql('DROP TABLE checkout_payment_sessions');
        $this->addSql('DROP TABLE customers');
        $this->addSql('DROP TABLE debt_payments');
        $this->addSql('DROP TABLE debts');
        $this->addSql('DROP TABLE external_payment_transactions');
        $this->addSql('DROP TABLE idempotency_records');
        $this->addSql('DROP TABLE order_financial_reversals');
        $this->addSql('DROP TABLE order_items');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE payment_bank_accounts');
        $this->addSql('DROP TABLE payment_references');
        $this->addSql('DROP TABLE payments');
        $this->addSql('DROP TABLE product_categories');
        $this->addSql('DROP TABLE product_images');
        $this->addSql('DROP TABLE products');
        $this->addSql('DROP TABLE stock_movements');
        $this->addSql('DROP TABLE user_sessions');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
