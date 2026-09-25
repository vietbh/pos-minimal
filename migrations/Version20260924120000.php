<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260924120000 extends AbstractMigration {
 public function getDescription(): string { return 'Add SalesPoint operations/current point persistence and persist per-bank webhook tokens.'; }
 public function up(Schema $schema): void {
  if (!$schema->hasTable('sales_point_groups')) {
   $this->addSql("CREATE TABLE sales_point_groups (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, name VARCHAR(120) NOT NULL, sort_order INT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_SALES_POINT_GROUP_CODE (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB");
  }
  if (!$schema->hasTable('sales_points')) {
   $this->addSql("CREATE TABLE sales_points (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, name VARCHAR(120) NOT NULL, type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, group_id BIGINT UNSIGNED DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_SALES_POINT_CODE (code), INDEX idx_sales_point_active (status), INDEX IDX_SALES_POINT_GROUP (group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB");
   $this->addSql('ALTER TABLE sales_points ADD CONSTRAINT FK_SALES_POINT_GROUP FOREIGN KEY (group_id) REFERENCES sales_point_groups (id) ON DELETE SET NULL');
  }
  $orders=$schema->getTable('orders');
  if (!$orders->hasColumn('sales_point_id')) { $this->addSql('ALTER TABLE orders ADD sales_point_id BIGINT UNSIGNED DEFAULT NULL'); }
  if (!$orders->hasIndex('idx_order_sales_point_created')) { $this->addSql('CREATE INDEX idx_order_sales_point_created ON orders (sales_point_id, created_at)'); }
  if (!$this->foreignKeyExists($orders,'FK_ORDER_SALES_POINT')) { $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_ORDER_SALES_POINT FOREIGN KEY (sales_point_id) REFERENCES sales_points (id) ON DELETE RESTRICT'); }
  $sessions=$schema->getTable('checkout_payment_sessions');
  if (!$sessions->hasColumn('sales_point_id')) { $this->addSql('ALTER TABLE checkout_payment_sessions ADD sales_point_id BIGINT UNSIGNED DEFAULT NULL'); }
  if (!$sessions->hasIndex('idx_checkout_session_sales_point')) { $this->addSql('CREATE INDEX idx_checkout_session_sales_point ON checkout_payment_sessions (sales_point_id)'); }
  if (!$this->foreignKeyExists($sessions,'FK_CHECKOUT_SESSION_SALES_POINT')) { $this->addSql('ALTER TABLE checkout_payment_sessions ADD CONSTRAINT FK_CHECKOUT_SESSION_SALES_POINT FOREIGN KEY (sales_point_id) REFERENCES sales_points (id) ON DELETE RESTRICT'); }
  $accounts=$schema->getTable('payment_bank_accounts');
  if (!$accounts->hasColumn('webhook_token')) { $this->addSql('ALTER TABLE payment_bank_accounts ADD webhook_token VARCHAR(64) DEFAULT NULL'); }
  $this->addSql("UPDATE payment_bank_accounts SET webhook_token = SHA2(CONCAT(UUID(), '-', id, '-', RAND()), 256) WHERE webhook_token IS NULL OR webhook_token = ''");
  if (!$accounts->hasIndex('UNIQ_PAYMENT_BANK_ACCOUNT_WEBHOOK_TOKEN')) { $this->addSql('CREATE UNIQUE INDEX UNIQ_PAYMENT_BANK_ACCOUNT_WEBHOOK_TOKEN ON payment_bank_accounts (webhook_token)'); }
  $this->addSql('ALTER TABLE payment_bank_accounts MODIFY webhook_token VARCHAR(64) NOT NULL');
  $this->addSql("INSERT INTO sales_points (code,name,type,status,group_id,created_at,updated_at) SELECT 'POS-01','POS 1','POS','ACTIVE',NULL,NOW(),NOW() WHERE NOT EXISTS (SELECT 1 FROM sales_points)");
 }
 public function down(Schema $schema): void {
  if ($schema->hasTable('payment_bank_accounts')) { $this->addSql('ALTER TABLE payment_bank_accounts DROP INDEX UNIQ_PAYMENT_BANK_ACCOUNT_WEBHOOK_TOKEN'); $this->addSql('ALTER TABLE payment_bank_accounts DROP webhook_token'); }
  if ($schema->hasTable('checkout_payment_sessions')) { $t=$schema->getTable('checkout_payment_sessions'); if($this->foreignKeyExists($t,'FK_CHECKOUT_SESSION_SALES_POINT'))$this->addSql('ALTER TABLE checkout_payment_sessions DROP FOREIGN KEY FK_CHECKOUT_SESSION_SALES_POINT'); if($t->hasIndex('idx_checkout_session_sales_point'))$this->addSql('DROP INDEX idx_checkout_session_sales_point ON checkout_payment_sessions'); if($t->hasColumn('sales_point_id'))$this->addSql('ALTER TABLE checkout_payment_sessions DROP sales_point_id'); }
  if ($schema->hasTable('orders')) { $t=$schema->getTable('orders'); if($this->foreignKeyExists($t,'FK_ORDER_SALES_POINT'))$this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_ORDER_SALES_POINT'); if($t->hasIndex('idx_order_sales_point_created'))$this->addSql('DROP INDEX idx_order_sales_point_created ON orders'); if($t->hasColumn('sales_point_id'))$this->addSql('ALTER TABLE orders DROP sales_point_id'); }
  if ($schema->hasTable('sales_points')) { $this->addSql('ALTER TABLE sales_points DROP FOREIGN KEY FK_SALES_POINT_GROUP'); $this->addSql('DROP TABLE sales_points'); }
  if ($schema->hasTable('sales_point_groups')) $this->addSql('DROP TABLE sales_point_groups');
 }
 private function foreignKeyExists(\Doctrine\DBAL\Schema\Table $table,string $name):bool { foreach($table->getForeignKeys() as $fk) if($fk->getName()===$name)return true; return false; }
}
