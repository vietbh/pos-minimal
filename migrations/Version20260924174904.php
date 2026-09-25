<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260924174904 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_order_sales_point_created ON orders');
        $this->addSql('ALTER TABLE payment_bank_accounts ADD webhook_token VARCHAR(64) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PAYMENT_BANK_ACCOUNT_WEBHOOK_TOKEN ON payment_bank_accounts (webhook_token)');
        $this->addSql('DROP INDEX idx_sales_point_group_status_sort ON sales_point_groups');
        $this->addSql('ALTER TABLE sales_point_groups CHANGE code code VARCHAR(50) NOT NULL, CHANGE status status VARCHAR(20) NOT NULL, CHANGE sort_order sort_order INT NOT NULL');
        $this->addSql('ALTER TABLE sales_points DROP FOREIGN KEY `FK_54EDC21FE54D947`');
        $this->addSql('DROP INDEX idx_sales_point_group_status ON sales_points');
        $this->addSql('DROP INDEX idx_sales_point_type_status ON sales_points');
        $this->addSql('ALTER TABLE sales_points CHANGE code code VARCHAR(50) NOT NULL, CHANGE name name VARCHAR(120) NOT NULL');
        $this->addSql('ALTER TABLE sales_points ADD CONSTRAINT FK_54EDC21FE54D947 FOREIGN KEY (group_id) REFERENCES sales_point_groups (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_sales_point_active ON sales_points (status)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_order_sales_point_created ON orders (sales_point_id, created_at)');
        $this->addSql('DROP INDEX UNIQ_PAYMENT_BANK_ACCOUNT_WEBHOOK_TOKEN ON payment_bank_accounts');
        $this->addSql('ALTER TABLE payment_bank_accounts DROP webhook_token');
        $this->addSql('ALTER TABLE sales_point_groups CHANGE code code VARCHAR(60) NOT NULL, CHANGE sort_order sort_order INT DEFAULT 0 NOT NULL, CHANGE status status VARCHAR(20) DEFAULT \'ACTIVE\' NOT NULL');
        $this->addSql('CREATE INDEX idx_sales_point_group_status_sort ON sales_point_groups (status, sort_order)');
        $this->addSql('ALTER TABLE sales_points DROP FOREIGN KEY FK_54EDC21FE54D947');
        $this->addSql('DROP INDEX idx_sales_point_active ON sales_points');
        $this->addSql('ALTER TABLE sales_points CHANGE code code VARCHAR(60) NOT NULL, CHANGE name name VARCHAR(160) NOT NULL');
        $this->addSql('ALTER TABLE sales_points ADD CONSTRAINT `FK_54EDC21FE54D947` FOREIGN KEY (group_id) REFERENCES sales_point_groups (id) ON UPDATE NO ACTION');
        $this->addSql('CREATE INDEX idx_sales_point_group_status ON sales_points (group_id, status)');
        $this->addSql('CREATE INDEX idx_sales_point_type_status ON sales_points (type, status)');
    }
}
