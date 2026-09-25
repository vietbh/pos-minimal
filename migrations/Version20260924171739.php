<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260924171739 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sales_point_groups (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, code VARCHAR(60) NOT NULL, name VARCHAR(120) NOT NULL, status VARCHAR(20) DEFAULT \'ACTIVE\' NOT NULL, sort_order INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_sales_point_group_status_sort (status, sort_order), UNIQUE INDEX UNIQ_SALES_POINT_GROUP_CODE (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE sales_points (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, code VARCHAR(60) NOT NULL, name VARCHAR(160) NOT NULL, type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, group_id BIGINT UNSIGNED DEFAULT NULL, INDEX idx_sales_point_group_status (group_id, status), INDEX idx_sales_point_type_status (type, status), UNIQUE INDEX UNIQ_SALES_POINT_CODE (code), INDEX IDX_54EDC21FE54D947 (group_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE sales_points ADD CONSTRAINT FK_54EDC21FE54D947 FOREIGN KEY (group_id) REFERENCES sales_point_groups (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE checkout_payment_sessions ADD sales_point_id BIGINT UNSIGNED DEFAULT NULL');
        $this->addSql('ALTER TABLE checkout_payment_sessions ADD CONSTRAINT FK_6ADD40138D945686 FOREIGN KEY (sales_point_id) REFERENCES sales_points (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_6ADD40138D945686 ON checkout_payment_sessions (sales_point_id)');
        $this->addSql('ALTER TABLE orders ADD sales_point_id BIGINT UNSIGNED DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE8D945686 FOREIGN KEY (sales_point_id) REFERENCES sales_points (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX idx_order_sales_point_created ON orders (sales_point_id, created_at)');
        $this->addSql('CREATE INDEX IDX_E52FFDEE8D945686 ON orders (sales_point_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sales_points DROP FOREIGN KEY FK_54EDC21FE54D947');
        $this->addSql('DROP TABLE sales_point_groups');
        $this->addSql('DROP TABLE sales_points');
        $this->addSql('ALTER TABLE checkout_payment_sessions DROP FOREIGN KEY FK_6ADD40138D945686');
        $this->addSql('DROP INDEX IDX_6ADD40138D945686 ON checkout_payment_sessions');
        $this->addSql('ALTER TABLE checkout_payment_sessions DROP sales_point_id');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEE8D945686');
        $this->addSql('DROP INDEX idx_order_sales_point_created ON orders');
        $this->addSql('DROP INDEX IDX_E52FFDEE8D945686 ON orders');
        $this->addSql('ALTER TABLE orders DROP sales_point_id');
    }
}
