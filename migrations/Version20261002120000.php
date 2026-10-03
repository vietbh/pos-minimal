<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add durable statistics outbox, daily sales/product/customer projections, and Mercure notification outbox.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE statistics_outbox (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            event_id CHAR(36) NOT NULL,
            event_type VARCHAR(40) NOT NULL,
            aggregate_id BIGINT UNSIGNED NOT NULL,
            payload JSON NOT NULL,
            occurred_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            dispatched_at DATETIME DEFAULT NULL,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_statistics_outbox_pending (dispatched_at, id),
            UNIQUE INDEX uq_statistics_outbox_event (event_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE statistics_processed_events (
            event_id CHAR(36) NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(40) NOT NULL,
            processed_at DATETIME NOT NULL,
            INDEX idx_statistics_processed_order (order_id, processed_at),
            PRIMARY KEY(event_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE statistics_projection_locks (
            lock_key VARCHAR(32) NOT NULL,
            lock_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY(lock_key)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE daily_sales_statistics (
            business_date DATE NOT NULL,
            sales_point_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            completed_order_count INT UNSIGNED NOT NULL DEFAULT 0,
            gross_sales NUMERIC(15, 2) NOT NULL DEFAULT 0,
            discount_amount NUMERIC(15, 2) NOT NULL DEFAULT 0,
            net_sales NUMERIC(15, 2) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY(business_date, sales_point_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE daily_product_sales_statistics (
            business_date DATE NOT NULL,
            sales_point_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            product_id BIGINT UNSIGNED NOT NULL,
            product_name_snapshot VARCHAR(255) NOT NULL,
            quantity_sold BIGINT NOT NULL DEFAULT 0,
            gross_sales NUMERIC(15, 2) NOT NULL DEFAULT 0,
            completed_order_count INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            INDEX idx_daily_product_top (business_date, sales_point_id, quantity_sold),
            PRIMARY KEY(business_date, sales_point_id, product_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE daily_customer_sales_statistics (
            business_date DATE NOT NULL,
            sales_point_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            customer_id BIGINT UNSIGNED NOT NULL,
            order_count INT UNSIGNED NOT NULL DEFAULT 0,
            gross_spend NUMERIC(15, 2) NOT NULL DEFAULT 0,
            discount_amount NUMERIC(15, 2) NOT NULL DEFAULT 0,
            net_spend NUMERIC(15, 2) NOT NULL DEFAULT 0,
            last_order_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_daily_customer_top (business_date, sales_point_id, net_spend),
            PRIMARY KEY(business_date, sales_point_id, customer_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE statistics_notification_outbox (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            event_id CHAR(36) NOT NULL,
            scope_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            topic VARCHAR(512) NOT NULL,
            created_at DATETIME NOT NULL,
            published_at DATETIME DEFAULT NULL,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            last_error VARCHAR(1000) DEFAULT NULL,
            UNIQUE INDEX uq_statistics_notification_event_scope (event_id, scope_id),
            INDEX idx_statistics_notification_pending (published_at, id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql('CREATE INDEX idx_order_statistics_completed ON orders (status, completed_at, sales_point_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_order_statistics_completed ON orders');
        $this->addSql('DROP TABLE statistics_notification_outbox');
        $this->addSql('DROP TABLE daily_customer_sales_statistics');
        $this->addSql('DROP TABLE daily_product_sales_statistics');
        $this->addSql('DROP TABLE daily_sales_statistics');
        $this->addSql('DROP TABLE statistics_projection_locks');
        $this->addSql('DROP TABLE statistics_processed_events');
        $this->addSql('DROP TABLE statistics_outbox');
    }
}
