<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260830165943 extends AbstractMigration
{

    public function getDescription(): string
    {
        return 'Add performance and query indexes required by current entity mappings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE INDEX idx_user_session_status_activity
             ON user_sessions (status, last_activity_at)',
        );

        $this->addSql(
            'CREATE INDEX idx_user_session_user_status
             ON user_sessions (user_id, status)',
        );

        $this->addSql(
            'CREATE INDEX idx_payment_order_created
             ON payments (order_id, created_at)',
        );

        $this->addSql(
            'CREATE INDEX idx_payment_user_created
             ON payments (user_id, created_at)',
        );

        $this->addSql(
            'CREATE INDEX idx_payment_method_created
             ON payments (method, created_at)',
        );

        $this->addSql(
            'ALTER TABLE order_items
             RENAME INDEX idx_62809db08d9f6d38 TO idx_order_item_order',
        );

        $this->addSql(
            'ALTER TABLE order_items
             RENAME INDEX idx_62809db04584665a TO idx_order_item_product',
        );

        $this->addSql(
            'CREATE INDEX idx_order_customer_created
             ON orders (customer_id, created_at)',
        );

        $this->addSql(
            'CREATE INDEX idx_order_user_created
             ON orders (user_id, created_at)',
        );

        $this->addSql(
            'CREATE INDEX idx_order_status_created
             ON orders (status, created_at)',
        );

        $this->addSql(
            'CREATE INDEX idx_order_created
             ON orders (created_at)',
        );

        $this->addSql(
            'CREATE INDEX idx_idempotency_user_created
             ON idempotency_records (user_id, created_at)',
        );

        $this->addSql(
            'CREATE INDEX idx_idempotency_status_created
             ON idempotency_records (status, created_at)',
        );

        $this->addSql(
            'CREATE INDEX idx_debt_customer_status
             ON debts (customer_id, status)',
        );

        $this->addSql(
            'CREATE INDEX idx_debt_order
             ON debts (order_id)',
        );

        $this->addSql(
            'CREATE INDEX idx_debt_status_created
             ON debts (status, created_at)',
        );

        $this->addSql(
            'CREATE INDEX idx_debt_created
             ON debts (created_at)',
        );

        $this->addSql(
            'CREATE INDEX idx_debt_payment_debt_created
             ON debt_payments (debt_id, created_at)',
        );

        $this->addSql(
            'CREATE INDEX idx_debt_payment_user_created
             ON debt_payments (user_id, created_at)',
        );

        $this->addSql(
            'CREATE INDEX idx_stock_movement_product_created
             ON stock_movements (product_id, created_at)',
        );

        $this->addSql(
            'CREATE INDEX idx_stock_movement_user_created
             ON stock_movements (user_id, created_at)',
        );

        $this->addSql(
            'CREATE INDEX idx_stock_movement_type_created
             ON stock_movements (type, created_at)',
        );

        $this->addSql(
            'ALTER TABLE stock_movements
             RENAME INDEX idx_a0be93c98d9f6d38 TO idx_stock_movement_order',
        );

        $this->addSql(
            'CREATE INDEX idx_customer_name
             ON customers (name)',
        );

        $this->addSql(
            'CREATE INDEX idx_customer_phone
             ON customers (phone)',
        );

        $this->addSql(
            'CREATE INDEX idx_product_image_product_sort
             ON product_images (product_id, sort_order)',
        );

        $this->addSql(
            'CREATE INDEX idx_product_image_product_primary
             ON product_images (product_id, is_primary)',
        );

        $this->addSql(
            'CREATE INDEX idx_product_image_status
             ON product_images (status)',
        );

        $this->addSql(
            'CREATE INDEX idx_product_name
             ON products (name)',
        );

        $this->addSql(
            'CREATE INDEX idx_product_active_name
             ON products (is_active, name)',
        );

        $this->addSql(
            'CREATE INDEX idx_product_stock
             ON products (stock_quantity)',
        );

        $this->addSql(
            'CREATE INDEX idx_audit_user_created
             ON audit_logs (user_id, created_at)',
        );

        $this->addSql(
            'CREATE INDEX idx_audit_entity
             ON audit_logs (entity_type, entity_id)',
        );

        $this->addSql(
            'CREATE INDEX idx_audit_action_created
             ON audit_logs (action, created_at)',
        );

        $this->addSql(
            'CREATE INDEX idx_audit_created
             ON audit_logs (created_at)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP INDEX idx_user_session_status_activity
             ON user_sessions',
        );

        $this->addSql(
            'DROP INDEX idx_user_session_user_status
             ON user_sessions',
        );

        $this->addSql(
            'DROP INDEX idx_payment_order_created
             ON payments',
        );

        $this->addSql(
            'DROP INDEX idx_payment_user_created
             ON payments',
        );

        $this->addSql(
            'DROP INDEX idx_payment_method_created
             ON payments',
        );

        $this->addSql(
            'ALTER TABLE order_items
             RENAME INDEX idx_order_item_order TO idx_62809db08d9f6d38',
        );

        $this->addSql(
            'ALTER TABLE order_items
             RENAME INDEX idx_order_item_product TO idx_62809db04584665a',
        );

        $this->addSql(
            'DROP INDEX idx_order_customer_created
             ON orders',
        );

        $this->addSql(
            'DROP INDEX idx_order_user_created
             ON orders',
        );

        $this->addSql(
            'DROP INDEX idx_order_status_created
             ON orders',
        );

        $this->addSql(
            'DROP INDEX idx_order_created
             ON orders',
        );

        $this->addSql(
            'DROP INDEX idx_idempotency_user_created
             ON idempotency_records',
        );

        $this->addSql(
            'DROP INDEX idx_idempotency_status_created
             ON idempotency_records',
        );

        $this->addSql(
            'DROP INDEX idx_debt_customer_status
             ON debts',
        );

        $this->addSql(
            'DROP INDEX idx_debt_order
             ON debts',
        );

        $this->addSql(
            'DROP INDEX idx_debt_status_created
             ON debts',
        );

        $this->addSql(
            'DROP INDEX idx_debt_created
             ON debts',
        );

        $this->addSql(
            'DROP INDEX idx_debt_payment_debt_created
             ON debt_payments',
        );

        $this->addSql(
            'DROP INDEX idx_debt_payment_user_created
             ON debt_payments',
        );

        $this->addSql(
            'DROP INDEX idx_stock_movement_product_created
             ON stock_movements',
        );

        $this->addSql(
            'DROP INDEX idx_stock_movement_user_created
             ON stock_movements',
        );

        $this->addSql(
            'DROP INDEX idx_stock_movement_type_created
             ON stock_movements',
        );

        $this->addSql(
            'ALTER TABLE stock_movements
             RENAME INDEX idx_stock_movement_order TO idx_a0be93c98d9f6d38',
        );

        $this->addSql(
            'DROP INDEX idx_customer_name
             ON customers',
        );

        $this->addSql(
            'DROP INDEX idx_customer_phone
             ON customers',
        );

        $this->addSql(
            'DROP INDEX idx_product_image_product_sort
             ON product_images',
        );

        $this->addSql(
            'DROP INDEX idx_product_image_product_primary
             ON product_images',
        );

        $this->addSql(
            'DROP INDEX idx_product_image_status
             ON product_images',
        );

        $this->addSql(
            'DROP INDEX idx_product_name
             ON products',
        );

        $this->addSql(
            'DROP INDEX idx_product_active_name
             ON products',
        );

        $this->addSql(
            'DROP INDEX idx_product_stock
             ON products',
        );

        $this->addSql(
            'DROP INDEX idx_audit_user_created
             ON audit_logs',
        );

        $this->addSql(
            'DROP INDEX idx_audit_entity
             ON audit_logs',
        );

        $this->addSql(
            'DROP INDEX idx_audit_action_created
             ON audit_logs',
        );

        $this->addSql(
            'DROP INDEX idx_audit_created
             ON audit_logs',
        );
    }
}
