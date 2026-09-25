<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924121500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reusable sales points/groups and attach them to orders and checkout payment sessions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE sales_point_groups (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, code VARCHAR(60) NOT NULL, name VARCHAR(120) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE', sort_order INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_SALES_POINT_GROUP_CODE (code), INDEX idx_sales_point_group_status_sort (status, sort_order), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE sales_points (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, group_id BIGINT UNSIGNED DEFAULT NULL, code VARCHAR(60) NOT NULL, name VARCHAR(160) NOT NULL, type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_SALES_POINT_CODE (code), INDEX idx_sales_point_group_status (group_id, status), INDEX idx_sales_point_type_status (type, status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE sales_points ADD CONSTRAINT FK_SALES_POINT_GROUP FOREIGN KEY (group_id) REFERENCES sales_point_groups (id) ON DELETE RESTRICT');

        $this->addSql('ALTER TABLE orders ADD sales_point_id BIGINT UNSIGNED DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD INDEX idx_order_sales_point_created (sales_point_id, created_at)');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_ORDER_SALES_POINT FOREIGN KEY (sales_point_id) REFERENCES sales_points (id) ON DELETE RESTRICT');

        $this->addSql('ALTER TABLE checkout_payment_sessions ADD sales_point_id BIGINT UNSIGNED DEFAULT NULL');
        $this->addSql('ALTER TABLE checkout_payment_sessions ADD INDEX idx_checkout_session_sales_point (sales_point_id)');
        $this->addSql('ALTER TABLE checkout_payment_sessions ADD CONSTRAINT FK_CHECKOUT_SESSION_SALES_POINT FOREIGN KEY (sales_point_id) REFERENCES sales_points (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE checkout_payment_sessions DROP FOREIGN KEY FK_CHECKOUT_SESSION_SALES_POINT');
        $this->addSql('ALTER TABLE checkout_payment_sessions DROP INDEX idx_checkout_session_sales_point');
        $this->addSql('ALTER TABLE checkout_payment_sessions DROP sales_point_id');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_ORDER_SALES_POINT');
        $this->addSql('ALTER TABLE orders DROP INDEX idx_order_sales_point_created');
        $this->addSql('ALTER TABLE orders DROP sales_point_id');
        $this->addSql('ALTER TABLE sales_points DROP FOREIGN KEY FK_SALES_POINT_GROUP');
        $this->addSql('DROP TABLE sales_points');
        $this->addSql('DROP TABLE sales_point_groups');
    }
}
